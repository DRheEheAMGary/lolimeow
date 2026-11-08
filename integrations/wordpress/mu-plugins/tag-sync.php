<?php
/**
 * Plugin Name: DRheEheAM SSO Tag 同步
 * Description: 接收 SSO 的 Tag 变更，写入 WordPress 的 boxmoe_contrib_tag meta。
 * Version:     1.0.0
 * Author:      DRheEheAM_Gary
 * License:     Proprietary
 *
 * 安装：放到 wp-content/mu-plugins/tag-sync.php
 * 配置：wp-config.php 增加 define('SSO_TAG_SYNC_SECRET', '<随机长串>');
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

    $tag = sanitize_text_field($tag);
    $tag = mb_substr($tag, 0, 32);
    if ($tag === '') {
        delete_user_meta($userId, 'boxmoe_contrib_tag');
        clean_user_cache($userId);
        return ['ok' => true, 'user_id' => $userId, 'tag' => ''];
    }

    update_user_meta($userId, 'boxmoe_contrib_tag', $tag);
    clean_user_cache($userId);
    return ['ok' => true, 'user_id' => $userId, 'tag' => $tag];
}

/**
 * Tag 已迁移到 SSO：WordPress 后台的 Tag 管理只保留查看，禁止再写入，
 * 否则会和 SSO 的双写产生冲突。
 */
add_action('admin_init', function () {
    if (!is_admin() || !current_user_can('manage_options')) {
        return;
    }
    $page = isset($_GET['page']) ? (string) $_GET['page'] : '';
    if ($page !== 'boxmoe-contrib-tags') {
        return;
    }
    if (isset($_POST['save_tags'])) {
        wp_die(
            'Tag 已迁移到 SSO，请在 SSO 或活动站后台管理 Tag。',
            'Tag 管理已迁移',
            ['response' => 403, 'back_link' => true]
        );
    }
});

// Tag 管理页的迁移提示改到页面标题下方，避免和标题挤在同一行。
