<?php
/**
 * 用户主页功能模块
 * - 注册 /user/username 路由
 * - 提供用户数据获取函数
 * - 隐私设置支持
 *
 * @link https://www.boxmoe.com
 * @package lolimeow
 */
if (!defined('ABSPATH')) { echo 'Look your sister'; exit; }

// ==================== 路由注册 ====================

/**
 * 注册查询变量
 */
function boxmoe_user_profile_query_vars($vars) {
    $vars[] = 'username';
    $vars[] = 'boxmoe_user_profile'; // 标记为用户主页请求
    return $vars;
}
add_filter('query_vars', 'boxmoe_user_profile_query_vars');

/**
 * 注册重写规则：/user/username → index.php?boxmoe_user_profile=1&username=xxx
 */
function boxmoe_user_profile_rewrite_rules() {
    add_rewrite_rule(
        '^user/([^/]+)/?$',
        'index.php?boxmoe_user_profile=1&username=$matches[1]',
        'top'
    );
    add_rewrite_rule(
        '^user/([^/]+)/page/([0-9]+)/?$',
        'index.php?boxmoe_user_profile=1&username=$matches[1]&paged=$matches[2]',
        'top'
    );
}
add_action('init', 'boxmoe_user_profile_rewrite_rules');

/**
 * 拦截请求并加载用户主页模板
 */
function boxmoe_user_profile_template($template) {
    if (get_query_var('boxmoe_user_profile')) {
        $profile_template = locate_template('page/p-user.php');
        if ($profile_template) {
            return $profile_template;
        }
    }
    return $template;
}
add_filter('template_include', 'boxmoe_user_profile_template', 99);

/**
 * 阻止 WordPress 将 /user/xxx 视为 404
 */
function boxmoe_user_profile_parse_query($query) {
    if ($query->is_main_query() && $query->get('boxmoe_user_profile')) {
        $query->is_404 = false;
        $query->is_page = true;
        $query->is_singular = false;
        $query->is_home = false;
        $query->is_archive = false;
        $query->is_author = false;
    }
}
add_action('parse_query', 'boxmoe_user_profile_parse_query');

/**
 * 主题激活时刷新重写规则
 */
function boxmoe_user_profile_flush_rewrite() {
    boxmoe_user_profile_rewrite_rules();
    flush_rewrite_rules();
}

/**
 * 一次性刷新：下次加载时自动写入重写规则（无需手动进后台）
 */
function boxmoe_user_profile_flush_on_load() {
    if (get_option('boxmoe_user_profile_flush_needed', false)) {
        delete_option('boxmoe_user_profile_flush_needed');
        flush_rewrite_rules(true);
    }
}
add_action('init', 'boxmoe_user_profile_flush_on_load', 1);

// 标记需要刷新（模块首次加载时执行一次）
if (!get_option('boxmoe_user_profile_flush_done')) {
    update_option('boxmoe_user_profile_flush_needed', true);
    update_option('boxmoe_user_profile_flush_done', true);
}

/**
 * 通过用户名获取用户对象
 */
function boxmoe_get_profile_user($username) {
    if (empty($username)) return null;
    $user = get_user_by('slug', $username);
    if (!$user) {
        // 兼容用户名中存在的大小写问题
        $user = get_user_by('login', $username);
    }
    return $user;
}

// ==================== 用户数据聚合 ====================

/**
 * 获取用户公开统计数据
 */
function boxmoe_get_user_stats($user_id) {
    // 文章数
    $post_count = count_user_posts($user_id, 'post', true);

    // 说说数（shuoshuo 分类下的文章数）
    $shuoshuo_cat = get_category_by_slug('shuoshuo');
    $shuoshuo_count = 0;
    if ($shuoshuo_cat) {
        $shuoshuo_count = count(get_posts(array(
            'author'         => $user_id,
            'category'       => $shuoshuo_cat->term_id,
            'posts_per_page' => -1,
            'fields'         => 'ids',
            'post_status'    => 'publish',
        )));
    }

    // 评论数
    $comment_count = get_comments(array(
        'user_id' => $user_id,
        'count'   => true,
        'status'  => 'approve',
    ));

    return array(
        'post_count'     => intval($post_count),
        'shuoshuo_count' => intval($shuoshuo_count),
        'comment_count'  => intval($comment_count),
    );
}

/**
 * 获取用户文章列表（分页）
 */
function boxmoe_get_user_posts($user_id, $paged = 1, $per_page = 10) {
    return new WP_Query(array(
        'author'         => $user_id,
        'posts_per_page' => $per_page,
        'paged'          => $paged,
        'post_status'    => 'publish',
        'ignore_sticky_posts' => true,
    ));
}

/**
 * 获取用户说说列表（分页）
 */
function boxmoe_get_user_shuoshuo($user_id, $paged = 1, $per_page = 10) {
    $shuoshuo_cat = get_category_by_slug('shuoshuo');
    if (!$shuoshuo_cat) {
        return new WP_Query(); // 空查询
    }
    return new WP_Query(array(
        'author'         => $user_id,
        'category_name'  => 'shuoshuo',
        'posts_per_page' => $per_page,
        'paged'          => $paged,
        'post_status'    => 'publish',
        'ignore_sticky_posts' => true,
    ));
}

/**
 * 获取用户评论列表（分页）
 */
function boxmoe_get_user_comments($user_id, $paged = 1, $per_page = 10) {
    return get_comments(array(
        'user_id' => $user_id,
        'status'  => 'approve',
        'number'  => $per_page,
        'offset'  => ($paged - 1) * $per_page,
        'orderby' => 'comment_date',
        'order'   => 'DESC',
    ));
}

/**
 * 获取用户评论总数（用于分页）
 */
function boxmoe_get_user_comment_count($user_id) {
    return intval(get_comments(array(
        'user_id' => $user_id,
        'count'   => true,
        'status'  => 'approve',
    )));
}

// ==================== 隐私控制 ====================

/**
 * 检查用户主页是否公开
 * 默认公开，用户可以设置为仅登录可见
 */
function boxmoe_is_profile_public($profile_user_id) {
    // 如果用户设置了"仅登录可见"，且当前访客未登录 → 不可见
    $privacy = get_user_meta($profile_user_id, 'boxmoe_profile_privacy', true);
    if ($privacy === 'logged_in' && !is_user_logged_in()) {
        return false;
    }
    return true;
}

/**
 * WordPress 个人资料页增加"主页隐私"字段
 */
function boxmoe_profile_privacy_field($user) {
    $privacy = get_user_meta($user->ID, 'boxmoe_profile_privacy', true);
    ?>
    <h3>用户主页设置</h3>
    <table class="form-table">
        <tr>
            <th><label for="boxmoe_profile_privacy">主页可见性</label></th>
            <td>
                <select name="boxmoe_profile_privacy" id="boxmoe_profile_privacy">
                    <option value="public" <?php selected($privacy, 'public'); ?>>所有人可见</option>
                    <option value="logged_in" <?php selected($privacy, 'logged_in'); ?>>仅登录用户可见</option>
                </select>
                <p class="description">设置你的用户主页对外的可见范围</p>
            </td>
        </tr>
    </table>
    <?php
}
add_action('show_user_profile', 'boxmoe_profile_privacy_field');
add_action('edit_user_profile', 'boxmoe_profile_privacy_field');

/**
 * 保存主页隐私字段
 */
function boxmoe_profile_privacy_save($user_id) {
    if (!current_user_can('edit_user', $user_id)) return;
    $privacy = isset($_POST['boxmoe_profile_privacy']) ? $_POST['boxmoe_profile_privacy'] : 'public';
    update_user_meta($user_id, 'boxmoe_profile_privacy', $privacy);
}
add_action('personal_options_update', 'boxmoe_profile_privacy_save');
add_action('edit_user_profile_update', 'boxmoe_profile_privacy_save');

// ==================== 链接生成 ====================

/**
 * 生成用户主页 URL
 */
function boxmoe_get_user_profile_url($user_id) {
    $user = get_user_by('id', $user_id);
    if (!$user) return home_url();
    return home_url('/user/' . $user->user_nicename);
}

// ==================== 头像编辑 AJAX ====================

/**
 * 用户主页头像编辑 AJAX
 * POST /wp-admin/admin-ajax.php?action=boxmoe_upload_avatar
 */
function boxmoe_ajax_upload_avatar() {
    try {
        $user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;
        if (!$user_id) throw new Exception('缺少参数');

        if (get_current_user_id() !== $user_id) throw new Exception('无权限');

        if (empty($_FILES['file'])) throw new Exception('未收到文件');

        $file = $_FILES['file'];
        if ($file['error'] !== UPLOAD_ERR_OK) throw new Exception('上传错误: ' . $file['error']);

        // 校验图片类型
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $allowed_ext = array('jpg', 'jpeg', 'png', 'gif', 'webp');
        if (!in_array($ext, $allowed_ext)) throw new Exception('不支持的文件类型: .' . $ext);

        // 生成唯一文件名并移动到 uploads/avatars/
        $upload_dir = wp_upload_dir();
        $avatar_dir = $upload_dir['basedir'] . '/avatars';
        if (!is_dir($avatar_dir)) wp_mkdir_p($avatar_dir);

        $filename = 'avatar-' . $user_id . '-' . time() . '.' . $ext;
        $dest = $avatar_dir . '/' . $filename;
        if (!move_uploaded_file($file['tmp_name'], $dest)) throw new Exception('文件保存失败');

        $file_url = $upload_dir['baseurl'] . '/avatars/' . $filename;

        // 同时更新两个头像存储位置：
        // 1) SLA 插件用的 simple_local_avatar
        if (class_exists('Simple_Local_Avatars') && method_exists('Simple_Local_Avatars', 'get_instance')) {
            $sla = Simple_Local_Avatars::get_instance();
            if (method_exists($sla, 'assign_new_user_avatar')) {
                $sla->assign_new_user_avatar($file_url, $user_id);
            }
        }
        // 2) 本主题 boxmoe_get_avatar_url() 读取的 user_avatar
        update_user_meta($user_id, 'user_avatar', $file_url);

        $avatar_url = add_query_arg('t', time(), $file_url);

        wp_send_json_success(array('avatar_url' => $avatar_url));
    } catch (Exception $e) {
        wp_send_json_error(array('message' => $e->getMessage()));
    }
}
add_action('wp_ajax_boxmoe_upload_avatar', 'boxmoe_ajax_upload_avatar');

// ==================== 禁用的用户名 ====================

/**
 * 禁止注册 "none" 等保留用户名
 */
function boxmoe_ban_reserved_usernames($errors, $sanitized_user_login, $user_email) {
    $reserved = array('none');
    if (in_array(strtolower($sanitized_user_login), $reserved)) {
        $errors->add('reserved_username', '此用户名已被系统保留，请使用其他用户名。');
    }
    return $errors;
}
add_filter('registration_errors', 'boxmoe_ban_reserved_usernames', 10, 3);

/**
 * 禁止在后台创建 "none" 用户名
 */
function boxmoe_validate_reserved_usernames($valid, $username) {
    $reserved = array('none');
    if (in_array(strtolower($username), $reserved)) {
        return false;
    }
    return $valid;
}
add_filter('validate_username', 'boxmoe_validate_reserved_usernames', 10, 2);

// ==================== 简介编辑 AJAX ====================

/**
 * 保存用户简介（Markdown 内容）
 * POST /wp-admin/admin-ajax.php?action=boxmoe_save_about
 */
function boxmoe_ajax_save_about() {
    $user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;
    $content = isset($_POST['content']) ? wp_kses_post($_POST['content']) : '';

    if (!$user_id) {
        wp_send_json_error(array('message' => '缺少参数'));
    }

    if (get_current_user_id() !== $user_id) {
        wp_send_json_error(array('message' => '无权限'));
    }

    // 保存到 WordPress 用户描述字段
    update_user_meta($user_id, 'description', $content);

    // 同时更新 user_description（wp_update_user 兼容）
    wp_update_user(array(
        'ID'          => $user_id,
        'description' => $content,
    ));

    wp_send_json_success(array('message' => '保存成功'));
}
add_action('wp_ajax_boxmoe_save_about', 'boxmoe_ajax_save_about');
