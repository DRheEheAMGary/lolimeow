<?php
/**
 * Plugin Name: DRheEheAM SSO Tag 同步
 * Description: 接收 SSO 的 Tag 变更（label + 可选样式），写入 boxmoe_sso_tag（label + style），并同步旧 boxmoe_contrib_tag（仅 label）以兼容历史调用。
 * Version:     2.0.0
 * Author:      DRheEheAM_Gary
 * License:     Proprietary
 *
 * 安装：放到 wp-content/mu-plugins/tag-sync.php
 * 配置：wp-config.php 增加 define('SSO_TAG_SYNC_SECRET', '<随机长串>');
 *
 * 请求体：{ sub, tag, style?: { border?, fill?, text?,
 *            fill_gradient?: {stops:[2..4],angle?}, text_gradient?: {stops:[2..4],angle?} } }
 * 颜色仅接受 #RGB / #RRGGBB；angle 0–360。style 缺失或任一项非法时只存 label，
 * 渲染侧使用默认样式；所有值都会再次校验，任何字符串都不会被直接当 CSS 输出。
 */

defined('ABSPATH') or die();

add_action('rest_api_init', function () {
    register_rest_route('gary/v1', '/tag-sync', [
        'methods'             => 'POST',
        'callback'            => 'sso_tag_sync_callback',
        'permission_callback' => 'sso_tag_sync_permission',
    ]);
});

function sso_tag_sync_permission(WP_REST_Request $request)
{
    $secret = defined('SSO_TAG_SYNC_SECRET') ? (string) SSO_TAG_SYNC_SECRET : '';
    $given  = (string) $request->get_header('x-sso-tag-sync');
    if ($secret === '' || $given === '' || !hash_equals($secret, $given)) {
        return new WP_Error('sso_tag_forbidden', '同步密钥无效', ['status' => 401]);
    }
    return true;
}

/**
 * 严格校验单色：仅接受 #RGB / #RRGGBB，返回规范化 #rrggbb；非法返回空串。
 */
function sso_tag_sync_color($value)
{
    if (!is_string($value)) {
        return '';
    }
    $value = trim($value);
    if (!preg_match('/^#(?:[0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/', $value)) {
        return '';
    }
    if (strlen($value) === 4) {
        $value = '#' . $value[1] . $value[1] . $value[2] . $value[2] . $value[3] . $value[3];
    }
    return strtolower($value);
}

/**
 * 严格校验渐变：支持 2–4 个色标（stops），angle 可选（默认 90，范围 0–360）。
 * 兼容旧的 {from,to,angle} 写法（会归一化成 stops）。
 * 非法返回 null。
 */
function sso_tag_sync_gradient($value)
{
    if (!is_array($value)) {
        return null;
    }

    if (isset($value['stops'])) {
        if (!is_array($value['stops'])) {
            return null;
        }
        $rawStops = array_values($value['stops']);
    } elseif (isset($value['from'], $value['to'])) {
        $rawStops = [$value['from'], $value['to']];
    } else {
        return null;
    }

    if (count($rawStops) < 2 || count($rawStops) > 4) {
        return null;
    }
    $stops = [];
    foreach ($rawStops as $stop) {
        $color = sso_tag_sync_color($stop);
        if ($color === '') {
            return null;
        }
        $stops[] = $color;
    }

    $angle = 90;
    if (isset($value['angle']) && $value['angle'] !== '') {
        if (!is_numeric($value['angle'])) {
            return null;
        }
        $angle = (int) round((float) $value['angle']);
        if ($angle < 0 || $angle > 360) {
            return null;
        }
    }
    return ['stops' => $stops, 'angle' => $angle];
}

/**
 * 校验整体 style。
 * 返回：[] = 无样式；数组 = 合法样式；null = 校验失败（调用方只存 label）。
 */
function sso_tag_sync_validate_style($style)
{
    if ($style === null || $style === '' || $style === []) {
        return [];
    }
    if (!is_array($style)) {
        return null;
    }

    $allowed = ['border', 'fill', 'text', 'fill_gradient', 'text_gradient'];
    $out = [];
    foreach ($style as $key => $value) {
        if (!in_array($key, $allowed, true)) {
            return null; // 严格白名单：出现未知字段视为非法
        }
        if ($value === null || $value === '') {
            continue;
        }
        if (in_array($key, ['border', 'fill', 'text'], true)) {
            $color = sso_tag_sync_color($value);
            if ($color === '') {
                return null;
            }
            $out[$key] = $color;
        } else {
            $gradient = sso_tag_sync_gradient($value);
            if ($gradient === null) {
                return null;
            }
            $out[$key] = $gradient;
        }
    }
    return $out;
}

function sso_tag_sync_callback(WP_REST_Request $request)
{
    $sub = trim((string) $request->get_param('sub'));
    $tag = trim((string) $request->get_param('tag'));

    if ($sub === '' || !ctype_digit($sub)) {
        return new WP_Error('sso_tag_bad_sub', '缺少 SSO 用户标识', ['status' => 400]);
    }

    $ids = get_users([
        'meta_key'   => 'sso_sub',
        'meta_value' => $sub,
        'number'     => 1,
        'fields'     => 'ID',
    ]);
    if (empty($ids)) {
        return new WP_Error('sso_tag_user_not_found', '找不到对应的 WordPress 用户', ['status' => 404]);
    }
    $userId = (int) $ids[0];

    $style = sso_tag_sync_validate_style($request->get_param('style'));
    $styleValid = ($style !== null);
    if ($style === null) {
        $style = []; // 校验失败：只存 label，渲染走默认样式
    }

    $tag = sanitize_text_field($tag);
    $tag = mb_substr($tag, 0, 32);

    if ($tag === '') {
        delete_user_meta($userId, 'boxmoe_sso_tag');
        delete_user_meta($userId, 'boxmoe_contrib_tag');
        clean_user_cache($userId);
        return ['ok' => true, 'user_id' => $userId, 'tag' => '', 'style' => [], 'style_valid' => $styleValid];
    }

    $data = ['label' => $tag];
    if (!empty($style)) {
        $data['style'] = $style;
    }
    update_user_meta($userId, 'boxmoe_sso_tag', $data);
    // 兼容历史调用：旧 key 只维护 label
    update_user_meta($userId, 'boxmoe_contrib_tag', $tag);
    clean_user_cache($userId);

    return ['ok' => true, 'user_id' => $userId, 'tag' => $tag, 'style' => $style, 'style_valid' => $styleValid];
}
