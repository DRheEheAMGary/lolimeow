<?php
/**
 * Feedback, reports and author applications.
 *
 * @package lolimeow
 */
if (!defined('ABSPATH')) { echo 'Look your sister'; exit; }

function boxmoe_feedback_types() {
    return [
        'issue' => '问题反馈',
        'suggestion' => '意见建议',
        'report_user' => '举报用户',
        'report_content' => '举报内容',
        'author_application' => '申请作者权限',
        'other' => '其他反馈',
    ];
}

function boxmoe_feedback_login_required_types() {
    return ['report_user', 'report_content', 'author_application'];
}

function boxmoe_feedback_register_post_type() {
    register_post_type('boxmoe_feedback', [
        'labels' => [
            'name' => '反馈管理',
            'singular_name' => '反馈记录',
            'menu_name' => '反馈管理',
            'all_items' => '全部反馈',
            'view_item' => '查看反馈',
            'search_items' => '搜索反馈',
            'not_found' => '暂无反馈',
        ],
        'public' => false,
        'show_ui' => true,
        'show_in_menu' => true,
        'menu_icon' => 'dashicons-feedback',
        'supports' => ['title'],
        'capability_type' => 'post',
        'capabilities' => [
            'edit_post' => 'manage_options',
            'read_post' => 'manage_options',
            'delete_post' => 'manage_options',
            'edit_posts' => 'manage_options',
            'edit_others_posts' => 'manage_options',
            'delete_posts' => 'manage_options',
            'publish_posts' => 'manage_options',
            'read_private_posts' => 'manage_options',
            'create_posts' => 'do_not_allow',
        ],
        'map_meta_cap' => false,
    ]);
}
add_action('init', 'boxmoe_feedback_register_post_type');

function boxmoe_feedback_settings_menu() {
    add_submenu_page(
        'edit.php?post_type=boxmoe_feedback',
        '反馈设置',
        '反馈设置',
        'manage_options',
        'boxmoe-feedback-settings',
        'boxmoe_feedback_settings_page'
    );
}
add_action('admin_menu', 'boxmoe_feedback_settings_menu');

function boxmoe_feedback_settings_page() {
    if (isset($_POST['boxmoe_feedback_settings_save'])) {
        check_admin_referer('boxmoe_feedback_settings');
        $email = sanitize_email(wp_unslash($_POST['boxmoe_feedback_email'] ?? ''));
        if ($email && is_email($email)) {
            update_option('boxmoe_feedback_email', $email);
            echo '<div class="notice notice-success"><p>反馈设置已保存。</p></div>';
        } else {
            echo '<div class="notice notice-error"><p>请输入有效的收件邮箱。</p></div>';
        }
    }
    ?>
    <div class="wrap">
        <h1>反馈设置</h1>
        <form method="post">
            <?php wp_nonce_field('boxmoe_feedback_settings'); ?>
            <table class="form-table">
                <tr>
                    <th scope="row"><label for="boxmoe-feedback-email">反馈收件邮箱</label></th>
                    <td>
                        <input id="boxmoe-feedback-email" type="email" name="boxmoe_feedback_email" class="regular-text" required
                               value="<?php echo esc_attr(get_option('boxmoe_feedback_email', get_option('admin_email'))); ?>">
                        <p class="description">所有反馈通过当前 WordPress SMTP 配置发送到此邮箱。</p>
                    </td>
                </tr>
            </table>
            <p class="submit"><button type="submit" name="boxmoe_feedback_settings_save" class="button button-primary">保存设置</button></p>
        </form>
    </div>
    <?php
}

function boxmoe_feedback_add_meta_boxes() {
    add_meta_box('boxmoe-feedback-detail', '反馈详情', 'boxmoe_feedback_meta_box', 'boxmoe_feedback', 'normal', 'high');
}
add_action('add_meta_boxes', 'boxmoe_feedback_add_meta_boxes');

function boxmoe_feedback_meta_box($post) {
    $data = get_post_meta($post->ID, '_boxmoe_feedback_data', true);
    if (!is_array($data)) $data = [];
    $types = boxmoe_feedback_types();
    $rows = [
        '反馈类型' => $types[$data['type'] ?? ''] ?? '',
        '提交用户' => $data['user_label'] ?? '游客',
        '联系邮箱' => $data['email'] ?? '',
        '举报对象' => $data['target_user'] ?? '',
        '相关地址' => $data['related_url'] ?? '',
        '擅长领域' => $data['expertise'] ?? '',
        '作品链接' => $data['portfolio_url'] ?? '',
        '提交 IP' => $data['ip'] ?? '',
        '提交时间' => $data['submitted_at'] ?? '',
    ];
    echo '<table class="widefat striped"><tbody>';
    foreach ($rows as $label => $value) {
        if ($value === '') continue;
        echo '<tr><th style="width:120px">' . esc_html($label) . '</th><td>';
        if (in_array($label, ['相关地址', '作品链接'], true)) {
            echo '<a href="' . esc_url($value) . '" target="_blank" rel="noopener noreferrer">' . esc_html($value) . '</a>';
        } else {
            echo nl2br(esc_html($value));
        }
        echo '</td></tr>';
    }
    echo '<tr><th>详细说明</th><td style="white-space:pre-wrap">' . esc_html($data['message'] ?? '') . '</td></tr>';
    echo '</tbody></table>';
}

function boxmoe_feedback_admin_columns($columns) {
    return [
        'cb' => $columns['cb'],
        'title' => '主题',
        'feedback_type' => '类型',
        'feedback_user' => '提交人',
        'date' => '提交时间',
    ];
}
add_filter('manage_boxmoe_feedback_posts_columns', 'boxmoe_feedback_admin_columns');

function boxmoe_feedback_admin_column($column, $post_id) {
    $data = get_post_meta($post_id, '_boxmoe_feedback_data', true);
    if (!is_array($data)) return;
    if ($column === 'feedback_type') {
        $types = boxmoe_feedback_types();
        echo esc_html($types[$data['type'] ?? ''] ?? '');
    }
    if ($column === 'feedback_user') echo esc_html($data['user_label'] ?? '游客');
}
add_action('manage_boxmoe_feedback_posts_custom_column', 'boxmoe_feedback_admin_column', 10, 2);

function boxmoe_feedback_assets() {
    if (!is_page_template('page/p-feedback.php')) return;
    wp_enqueue_style(
        'boxmoe-feedback',
        boxmoe_theme_url() . '/assets/css/feedback.css',
        [],
        filemtime(get_template_directory() . '/assets/css/feedback.css')
    );
    wp_enqueue_script(
        'boxmoe-feedback',
        boxmoe_theme_url() . '/assets/js/feedback.js',
        [],
        filemtime(get_template_directory() . '/assets/js/feedback.js'),
        true
    );
}
add_action('wp_enqueue_scripts', 'boxmoe_feedback_assets');

function boxmoe_feedback_client_ip() {
    if (function_exists('cfturnstile_get_ip')) return sanitize_text_field(cfturnstile_get_ip());
    return sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
}

function boxmoe_feedback_submit() {
    if (!check_ajax_referer('boxmoe_feedback', 'security', false)) {
        wp_send_json_error(['message' => '页面已过期，请刷新后重试'], 403);
    }

    $types = boxmoe_feedback_types();
    $type = sanitize_key(wp_unslash($_POST['type'] ?? ''));
    if (!isset($types[$type])) wp_send_json_error(['message' => '请选择有效的反馈类型'], 400);
    if (in_array($type, boxmoe_feedback_login_required_types(), true) && !is_user_logged_in()) {
        wp_send_json_error(['message' => '此类反馈需要登录后提交'], 403);
    }

    if (!function_exists('cfturnstile_check')) {
        wp_send_json_error(['message' => '人机验证服务暂不可用'], 503);
    }
    $turnstile = cfturnstile_check('', 'boxmoe-feedback');
    if (empty($turnstile['success'])) {
        wp_send_json_error(['message' => function_exists('cfturnstile_failed_message') ? wp_strip_all_tags(cfturnstile_failed_message()) : '人机验证失败'], 400);
    }

    $user_id = get_current_user_id();
    $ip = boxmoe_feedback_client_ip();
    $rate_key = 'boxmoe_feedback_rate_' . md5($user_id ? 'user:' . $user_id : 'ip:' . $ip);
    if (get_transient($rate_key)) {
        wp_send_json_error(['message' => '提交过于频繁，请稍后再试'], 429);
    }

    $subject = sanitize_text_field(wp_unslash($_POST['subject'] ?? ''));
    $message = sanitize_textarea_field(wp_unslash($_POST['message'] ?? ''));
    $email = sanitize_email(wp_unslash($_POST['email'] ?? ''));
    $target_user = sanitize_text_field(wp_unslash($_POST['target_user'] ?? ''));
    $related_url = esc_url_raw(wp_unslash($_POST['related_url'] ?? ''));
    $expertise = sanitize_text_field(wp_unslash($_POST['expertise'] ?? ''));
    $portfolio_url = esc_url_raw(wp_unslash($_POST['portfolio_url'] ?? ''));

    if (mb_strlen($subject) < 4 || mb_strlen($subject) > 100) {
        wp_send_json_error(['message' => '主题长度需要在 4 到 100 个字符之间'], 400);
    }
    if (mb_strlen($message) < 20 || mb_strlen($message) > 5000) {
        wp_send_json_error(['message' => '详细说明长度需要在 20 到 5000 个字符之间'], 400);
    }
    if (!$email || !is_email($email)) wp_send_json_error(['message' => '请填写有效的联系邮箱'], 400);
    if ($type === 'report_user' && $target_user === '') wp_send_json_error(['message' => '请填写被举报用户'], 400);
    if ($type === 'report_content' && $related_url === '') wp_send_json_error(['message' => '请填写被举报内容地址'], 400);
    if ($type === 'author_application' && ($expertise === '' || $portfolio_url === '')) {
        wp_send_json_error(['message' => '请填写擅长领域和作品链接'], 400);
    }

    $current_user = wp_get_current_user();
    $user_label = $user_id ? $current_user->display_name . ' (#' . $user_id . ')' : '游客';
    $data = compact('type', 'email', 'target_user', 'related_url', 'expertise', 'portfolio_url', 'message', 'ip', 'user_label');
    $data['user_id'] = $user_id;
    $data['submitted_at'] = current_time('mysql');

    $post_id = wp_insert_post([
        'post_type' => 'boxmoe_feedback',
        'post_status' => 'private',
        'post_title' => '[' . $types[$type] . '] ' . $subject,
        'post_author' => $user_id,
    ], true);
    if (is_wp_error($post_id)) wp_send_json_error(['message' => '反馈保存失败，请稍后重试'], 500);
    update_post_meta($post_id, '_boxmoe_feedback_data', $data);

    $recipient = sanitize_email(get_option('boxmoe_feedback_email', get_option('admin_email')));
    $admin_url = admin_url('post.php?post=' . $post_id . '&action=edit');
    $mail_subject = '[' . get_bloginfo('name') . '][' . $types[$type] . '] ' . $subject;
    $mail_rows = [
        '反馈编号' => esc_html('#' . $post_id),
        '反馈类型' => esc_html($types[$type]),
        '提交人' => esc_html($user_label),
        '联系邮箱' => esc_html($email),
        '举报对象' => esc_html($target_user),
        '相关地址' => $related_url ? '<a href="' . esc_url($related_url) . '">' . esc_html($related_url) . '</a>' : '',
        '擅长领域' => esc_html($expertise),
        '作品链接' => $portfolio_url ? '<a href="' . esc_url($portfolio_url) . '">' . esc_html($portfolio_url) . '</a>' : '',
        '详细说明' => nl2br(esc_html($message)),
        '后台查看' => '<a href="' . esc_url($admin_url) . '">' . esc_html($admin_url) . '</a>',
    ];
    $mail_body = '<div style="max-width:680px;margin:auto;font-family:Arial,sans-serif;color:#222"><h2>' . esc_html($types[$type]) . '</h2><table style="width:100%;border-collapse:collapse">';
    foreach ($mail_rows as $label => $value) {
        if ($value === '') continue;
        $mail_body .= '<tr><th style="width:110px;padding:10px;border:1px solid #ddd;text-align:left;vertical-align:top">' . esc_html($label) . '</th><td style="padding:10px;border:1px solid #ddd">' . $value . '</td></tr>';
    }
    $mail_body .= '</table></div>';

    $sent = $recipient && wp_mail($recipient, $mail_subject, $mail_body, ['Content-Type: text/html; charset=UTF-8']);
    update_post_meta($post_id, '_boxmoe_feedback_mail_sent', $sent ? 1 : 0);
    set_transient($rate_key, 1, 5 * MINUTE_IN_SECONDS);

    if (!$sent) {
        wp_send_json_success(['message' => '反馈已保存，但邮件通知发送失败，站主仍可在后台查看']);
    }
    wp_send_json_success(['message' => '反馈已提交，感谢你的帮助']);
}
add_action('wp_ajax_boxmoe_submit_feedback', 'boxmoe_feedback_submit');
add_action('wp_ajax_nopriv_boxmoe_submit_feedback', 'boxmoe_feedback_submit');
