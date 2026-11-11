<?php
/**
 * SSO Tag 徽章（只读展示）
 *
 * 数据来源：mu-plugins/tag-sync.php 写入 user meta `boxmoe_sso_tag`（label + 可选 style）。
 * 只按 SSO 下发的 label + 样式渲染，不依赖其它模块。
 *
 * @package lolimeow
 */

if (!defined('ABSPATH')) {
    echo 'Look your sister';
    exit;
}

/**
 * 严格校验单色：仅接受 #RGB / #RRGGBB；返回规范化的 #rrggbb，非法返回空串。
 */
function boxmoe_sso_tag_color($value) {
    if (!is_string($value)) return '';
    $value = trim($value);
    if (!preg_match('/^#(?:[0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/', $value)) return '';
    if (strlen($value) === 4) {
        $value = '#' . $value[1] . $value[1] . $value[2] . $value[2] . $value[3] . $value[3];
    }
    return strtolower($value);
}

/**
 * 校验渐变：支持 2–4 个色标（stops），angle 可选（默认 90，范围 0–360）。
 * 兼容旧 {from,to,angle} 写法；非法返回 null。
 */
function boxmoe_sso_tag_gradient($value) {
    if (!is_array($value)) return null;

    if (isset($value['stops'])) {
        if (!is_array($value['stops'])) return null;
        $rawStops = array_values($value['stops']);
    } elseif (isset($value['from'], $value['to'])) {
        $rawStops = array($value['from'], $value['to']);
    } else {
        return null;
    }

    if (count($rawStops) < 2 || count($rawStops) > 4) return null;
    $stops = array();
    foreach ($rawStops as $stop) {
        $color = boxmoe_sso_tag_color($stop);
        if ($color === '') return null;
        $stops[] = $color;
    }

    $angle = 90;
    if (isset($value['angle']) && $value['angle'] !== '') {
        if (!is_numeric($value['angle'])) return null;
        $angle = (int) round((float) $value['angle']);
        if ($angle < 0 || $angle > 360) return null;
    }
    return array('stops' => $stops, 'angle' => $angle);
}

/**
 * 把校验后的渐变拼成 CSS linear-gradient(...)；非法返回空串。
 */
function boxmoe_sso_tag_gradient_css($gradient) {
    if (!is_array($gradient) || empty($gradient['stops']) || !is_array($gradient['stops'])) return '';
    $stops = array();
    foreach ($gradient['stops'] as $stop) {
        $color = boxmoe_sso_tag_color($stop);
        if ($color !== '') $stops[] = $color;
    }
    if (count($stops) < 2 || count($stops) > 4) return '';
    $angle = isset($gradient['angle']) ? (int) $gradient['angle'] : 90;
    if ($angle < 0 || $angle > 360) $angle = 90;
    return 'linear-gradient(' . $angle . 'deg, ' . implode(', ', $stops) . ')';
}

/**
 * 读取用户的 Tag（label + 清洗后的 style）。
 * 优先 boxmoe_sso_tag；兼容历史 boxmoe_contrib_tag（只取 label）。
 * 没有任何 Tag 返回 null。
 */
function boxmoe_sso_tag_data($user_id) {
    $user_id = (int) $user_id;
    if ($user_id <= 0) return null;

    $data  = get_user_meta($user_id, 'boxmoe_sso_tag', true);
    $label = '';
    $style = array();
    if (is_array($data)) {
        $label = isset($data['label']) ? (string) $data['label'] : '';
        if (isset($data['style']) && is_array($data['style'])) {
            $style = $data['style'];
        }
    }
    if ($label === '') {
        $legacy = get_user_meta($user_id, 'boxmoe_contrib_tag', true);
        if (is_string($legacy)) {
            $label = $legacy;
        }
    }

    $label = trim(wp_strip_all_tags($label));
    if ($label === '') return null;
    $label = mb_substr($label, 0, 32);

    // 输出前二次校验：即便 meta 被直接改写，也不会把任意字符串当 CSS 输出
    $clean = array();
    foreach (array('border', 'fill', 'text') as $key) {
        if (!empty($style[$key])) {
            $color = boxmoe_sso_tag_color($style[$key]);
            if ($color !== '') $clean[$key] = $color;
        }
    }
    if (!empty($style['fill_gradient'])) {
        $gradient = boxmoe_sso_tag_gradient($style['fill_gradient']);
        if ($gradient) $clean['fill_gradient'] = $gradient;
    }
    if (!empty($style['text_gradient'])) {
        $gradient = boxmoe_sso_tag_gradient($style['text_gradient']);
        if ($gradient) $clean['text_gradient'] = $gradient;
    }

    return array('label' => $label, 'style' => $clean);
}

/**
 * 输出 SSO Tag 徽章 HTML。
 * 无 Tag、封禁用户（非管理员视角）时返回空字符串。
 */
function boxmoe_get_sso_tag_badge($user_id) {
    $user_id = (int) $user_id;
    if ($user_id <= 0) return '';

    if (function_exists('boxmoe_is_user_banned') && boxmoe_is_user_banned($user_id) && !current_user_can('manage_options')) {
        return '';
    }

    $data = boxmoe_sso_tag_data($user_id);
    if (!$data) return '';

    $label = $data['label'];
    $style = $data['style'];

    $classes = array('sso-tag-badge');
    $label_classes = array('sso-tag-label');
    $vars = array();

    if (!empty($style['border'])) {
        $vars[] = '--sso-tag-border:' . $style['border'];
    }
    if (!empty($style['fill'])) {
        $vars[] = '--sso-tag-fill:' . $style['fill'];
    }
    if (!empty($style['fill_gradient'])) {
        $fill_css = boxmoe_sso_tag_gradient_css($style['fill_gradient']);
        if ($fill_css !== '') {
            $classes[] = 'has-fill-gradient';
            $vars[] = '--sso-tag-fill-image:' . $fill_css;
        }
    }
    if (!empty($style['text'])) {
        $vars[] = '--sso-tag-text:' . $style['text'];
    }
    if (!empty($style['text_gradient'])) {
        $text_css = boxmoe_sso_tag_gradient_css($style['text_gradient']);
        if ($text_css !== '') {
            $label_classes[] = 'has-text-gradient';
            $vars[] = '--sso-tag-text-image:' . $text_css;
        }
    }

    $style_attr = !empty($vars) ? ' style="' . esc_attr(implode(';', $vars)) . '"' : '';

    return '<span class="' . esc_attr(implode(' ', $classes)) . '"' . $style_attr . '>'
         . '<span class="' . esc_attr(implode(' ', $label_classes)) . '">' . esc_html($label) . '</span>'
         . '</span>';
}

/**
 * 徽章样式（基础布局 + 深色模式默认值 + 渐变实现）
 */
add_action('wp_enqueue_scripts', 'boxmoe_sso_tag_assets');
function boxmoe_sso_tag_assets() {
    $path = get_template_directory() . '/assets/css/sso-tag.css';
    wp_enqueue_style(
        'boxmoe-sso-tag',
        get_template_directory_uri() . '/assets/css/sso-tag.css',
        array(),
        (string) (filemtime($path) ?: '20261110')
    );
}
