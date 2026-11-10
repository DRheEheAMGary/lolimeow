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
    $vars[] = 'boxmoe_user_settings'; // 标记为个人设置请求
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
    add_rewrite_rule(
        '^usersettings/?$',
        'index.php?boxmoe_user_settings=1',
        'top'
    );
}
add_action('init', 'boxmoe_user_profile_rewrite_rules');

/**
 * 拦截请求并加载用户主页模板
 */
function boxmoe_user_profile_template($template) {
    if (get_query_var('boxmoe_user_settings')) {
        if (!is_user_logged_in()) {
            wp_safe_redirect(home_url('/sign-in'));
            exit;
        }
        $t = locate_template('page/p-user-settings.php');
        if ($t) return $t;
    }
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
    if ($query->is_main_query() && (get_query_var('boxmoe_user_profile') || get_query_var('boxmoe_user_settings'))) {
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
    // 说说数
    $shuoshuo_cat = get_category_by_slug('shuoshuo');
    $shuoshuo_count = 0;
    $shuoshuo_ids = array();
    if ($shuoshuo_cat) {
        $shuoshuo_ids = get_posts(array(
            'author'         => $user_id,
            'category'       => $shuoshuo_cat->term_id,
            'posts_per_page' => -1,
            'fields'         => 'ids',
            'post_status'    => 'publish',
        ));
        $shuoshuo_count = count($shuoshuo_ids);
    }

    // 文章数（总数减去说说）
    $total_posts = count_user_posts($user_id, 'post', true);
    $post_count = max(0, $total_posts - $shuoshuo_count);

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
    $shuoshuo_cat = get_category_by_slug('shuoshuo');
    $args = array(
        'author'         => $user_id,
        'posts_per_page' => $per_page,
        'paged'          => $paged,
        'post_status'    => 'publish',
        'ignore_sticky_posts' => true,
    );
    if ($shuoshuo_cat) {
        $args['tax_query'] = array(
            array(
                'taxonomy' => 'category',
                'field'    => 'term_id',
                'terms'    => $shuoshuo_cat->term_id,
                'operator' => 'NOT IN',
            ),
        );
    }
    return new WP_Query($args);
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

        if (get_current_user_id() !== $user_id && !current_user_can('manage_options')) throw new Exception('无权限');

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
    $reserved = array('none', 'banned');
    if (in_array(strtolower($sanitized_user_login), $reserved)) {
        $errors->add('reserved_username', '此用户名已被系统保留，请使用其他用户名。');
    }
    return $errors;
}
add_filter('registration_errors', 'boxmoe_ban_reserved_usernames', 10, 3);

/**
 * 禁止在后台创建保留用户名
 */
function boxmoe_validate_reserved_usernames($valid, $username) {
    $reserved = array('none', 'banned');
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
    $content = isset($_POST['content']) ? sanitize_textarea_field($_POST['content']) : '';

    if (!$user_id) {
        wp_send_json_error(array('message' => '缺少参数'));
    }

    if (get_current_user_id() !== $user_id) {
        wp_send_json_error(array('message' => '无权限'));
    }

    // 保存到 user_about（Markdown 简介，与 description 签名字段独立）
    update_user_meta($user_id, 'user_about', $content);

    wp_send_json_success(array('message' => '保存成功'));
}
add_action('wp_ajax_boxmoe_save_about', 'boxmoe_ajax_save_about');

// ==================== 隐私检查 ====================

/**
 * 检查当前访客是否可以查看用户的某项内容
 * @param int $profile_user_id 被查看的用户 ID
 * @param string $meta_key 隐私设置 meta key（如 posts_visibility）
 * @return bool
 */
function boxmoe_can_view_content($profile_user_id, $meta_key) {
    // 管理员无视隐私设置
    if (current_user_can('manage_options')) return true;
    // 本人无视隐私设置
    if (get_current_user_id() === $profile_user_id) return true;

    $privacy = get_user_meta($profile_user_id, $meta_key, true) ?: 'public';

    switch ($privacy) {
        case 'public':
            return true;
        case 'members':
            return is_user_logged_in();
        case 'private':
            return false;
        default:
            return true;
    }
}

/**
 * 获取用户收藏文章 ID 列表
 * @param int $user_id
 * @return array
 */
function boxmoe_get_user_favorites($user_id) {
    $favorites = get_user_meta($user_id, 'user_favorites', true);
    return is_array($favorites) ? array_reverse($favorites) : array();
}

/**
 * 获取用户收藏文章（分页）
 */
function boxmoe_get_user_favorites_query($user_id, $paged = 1, $per_page = 10) {
    $all_ids = boxmoe_get_user_favorites($user_id);
    if (empty($all_ids)) {
        return array('posts' => array(), 'total' => 0, 'pages' => 0);
    }

    // 过滤掉非公开状态的文章
    $valid_ids = array();
    foreach ($all_ids as $pid) {
        $p = get_post($pid);
        if ($p && $p->post_status === 'publish') {
            $valid_ids[] = $pid;
        }
    }

    $total = count($valid_ids);
    $pages = ceil($total / $per_page);
    $offset = ($paged - 1) * $per_page;
    $page_ids = array_slice($valid_ids, $offset, $per_page);
    $posts = array_map('get_post', $page_ids);

    return array(
        'posts' => $posts,
        'total' => $total,
        'pages' => $pages,
    );
}

// ==================== 签名编辑 AJAX ====================

/**
 * 保存用户签名（短简介，纯文本）
 * POST /wp-admin/admin-ajax.php?action=boxmoe_save_description
 */
function boxmoe_ajax_save_description() {
    $user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;
    $content = isset($_POST['content']) ? sanitize_textarea_field($_POST['content']) : '';

    if (!$user_id) {
        wp_send_json_error(array('message' => '缺少参数'));
    }

    if (get_current_user_id() !== $user_id) {
        wp_send_json_error(array('message' => '无权限'));
    }

    update_user_meta($user_id, 'description', $content);
    wp_update_user(array('ID' => $user_id, 'description' => $content));

    wp_send_json_success(array('message' => '保存成功', 'content' => $content));
}
add_action('wp_ajax_boxmoe_save_description', 'boxmoe_ajax_save_description');

// ==================== 评论删除 AJAX ====================

/**
 * 删除评论
 * POST /wp-admin/admin-ajax.php?action=boxmoe_delete_comment
 */
function boxmoe_ajax_delete_comment() {
    $comment_id = isset($_POST['comment_id']) ? intval($_POST['comment_id']) : 0;
    if (!$comment_id) {
        wp_send_json_error(array('message' => '缺少评论 ID'));
    }

    $comment = get_comment($comment_id);
    if (!$comment) {
        wp_send_json_error(array('message' => '评论不存在'));
    }

    $post_author_id = get_post_field('post_author', $comment->comment_post_ID);
    $user_id = get_current_user_id();

    // 权限：文章作者 / 评论作者 / 管理员
    if (!$user_id || ($user_id != $post_author_id && $user_id != $comment->user_id && !current_user_can('manage_options'))) {
        wp_send_json_error(array('message' => '无权限'));
    }

    $result = wp_delete_comment($comment_id, true);
    if ($result) {
        wp_send_json_success(array('message' => '评论已删除'));
    } else {
        wp_send_json_error(array('message' => '删除失败'));
    }
}
add_action('wp_ajax_boxmoe_delete_comment', 'boxmoe_ajax_delete_comment');

// ==================== 用户封禁系统（主页封禁） ====================

/**
 * 检查用户是否被封禁
 */
function boxmoe_is_user_banned($user_id) {
    if (!$user_id) return false;
    $until = get_user_meta($user_id, 'boxmoe_ban_until', true);
    if (empty($until)) return false;
    if ($until === '-1') return '永久';
    if (time() > (int)$until) {
        delete_user_meta($user_id, 'boxmoe_ban_until');
        return false;
    }
    return date('Y-m-d H:i', (int)$until);
}

/**
 * 封禁用户主页拦截 + 强制下线
 * 管理员不受影响
 */
function boxmoe_ban_redirect() {
    if (current_user_can('manage_options')) return;

    // 1. 已登录被封用户：强制下线
    if (is_user_logged_in()) {
        $banned = boxmoe_is_user_banned(get_current_user_id());
        if ($banned) {
            $uri = $_SERVER['REQUEST_URI'];
            if (strpos($uri, '/user/banned') === false) {
                wp_logout();
                wp_redirect(home_url('/user/banned'));
                exit;
            }
            return;
        }
    }

    // 2. 别人访问被封用户主页
    if (get_query_var('boxmoe_user_profile')) {
        $username = get_query_var('username', '');
        if ($username === 'banned') return;
        $pu = boxmoe_get_profile_user($username);
        if ($pu && boxmoe_is_user_banned($pu->ID)) {
            wp_safe_redirect(home_url('/user/banned'));
            exit;
        }
    }
}
add_action('template_redirect', 'boxmoe_ban_redirect', 1);

/**
 * 阻止被封用户登录
 */
function boxmoe_ban_block_login($user, $password) {
    if (!$user || !($user instanceof WP_User)) return $user;
    // 管理员不受封禁限制（检查被认证用户是否有管理员权限）
    if (user_can($user, 'manage_options')) return $user;
    $banned = boxmoe_is_user_banned($user->ID);
    if ($banned) {
        return new WP_Error('user_banned', '账号已被封禁' . ($banned === '永久' ? '' : '，至 ' . $banned) . '。如有疑问请联系管理员。');
    }
    return $user;
}
add_filter('wp_authenticate_user', 'boxmoe_ban_block_login', 10, 2);

/**
 * 处理 /user/banned 页面
 */
function boxmoe_banned_page_template($template) {
    if (get_query_var('boxmoe_user_profile') && get_query_var('username') === 'banned') {
        $t = locate_template('page/p-banned.php');
        if ($t) return $t;
    }
    return $template;
}
add_filter('template_include', 'boxmoe_banned_page_template', 100);

/**
 * 管理员用户编辑页：封禁设置
 */
function boxmoe_ban_admin_fields($user) {
    if (!current_user_can('manage_options')) return;
    $until = get_user_meta($user->ID, 'boxmoe_ban_until', true);
    $status = boxmoe_is_user_banned($user->ID);
    ?>
    <h3>封禁设置</h3>
    <table class="form-table">
        <tr>
            <th><label>封禁状态</label></th>
            <td>
                <?php if ($status): ?>
                <span style="color:#dc3232;font-weight:600;">用户已封禁至 <?php echo esc_html($status); ?></span>
                <br>
                <button type="button" class="button" id="boxmoe-unban-btn" data-uid="<?php echo $user->ID; ?>">解除封禁</button>
                <?php else: ?>
                <span style="color:#46b450;">正常</span>
                <?php endif; ?>
            </td>
        </tr>
        <tr>
            <th><label for="boxmoe_ban_days">封禁天数</label></th>
            <td>
                <select id="boxmoe_ban_days">
                    <option value="1">1 天</option>
                    <option value="3">3 天</option>
                    <option value="7" selected>7 天</option>
                    <option value="14">14 天</option>
                    <option value="30">30 天</option>
                    <option value="-1">永久封禁</option>
                </select>
                <input type="text" id="boxmoe_ban_reason" placeholder="封禁理由（可选）" style="width:260px;margin-left:6px;">
                <button type="button" class="button button-primary" id="boxmoe-ban-btn" data-uid="<?php echo $user->ID; ?>">执行封禁</button>
                <p class="description">封禁后该用户将无法登录和使用网站（管理员除外）</p>
            </td>
        </tr>
        <tr>
            <th><label>禁言状态</label></th>
            <td>
                <?php $mute = function_exists('boxmoe_get_user_mute') ? boxmoe_get_user_mute($user->ID) : false; ?>
                <?php if ($mute): ?>
                <span style="color:#dc3232;font-weight:600;">已禁言至 <?php echo esc_html($mute['until']); ?> （<?php echo $mute['type'] === 'all' ? '全部' : ($mute['type'] === 'comments' ? '评论' : '文章'); ?>）</span>
                <br>
                <button type="button" class="button" id="boxmoe-unmute-btn" data-uid="<?php echo $user->ID; ?>">解除禁言</button>
                <?php else: ?>
                <span style="color:#46b450;">正常</span>
                <?php endif; ?>
            </td>
        </tr>
        <tr>
            <th><label>禁言范围</label></th>
            <td>
                <select id="boxmoe_mute_type" style="width:140px;">
                    <option value="all">全部</option>
                    <option value="comments">仅评论</option>
                    <option value="posts">仅文章</option>
                </select>
                <select id="boxmoe_mute_days" style="margin-left:4px;">
                    <option value="1">1 天</option>
                    <option value="3">3 天</option>
                    <option value="7" selected>7 天</option>
                    <option value="14">14 天</option>
                    <option value="30">30 天</option>
                    <option value="-1">永久</option>
                </select>
                <button type="button" class="button button-primary" id="boxmoe-mute-btn" data-uid="<?php echo $user->ID; ?>">执行禁言</button>
            </td>
        </tr>
    </table>
    <script>
    jQuery(function($) {
        $('#boxmoe-ban-btn').on('click', function() {
            if (!confirm('确定封禁该用户？')) return;
            var btn = $(this).prop('disabled', true).text('处理中...');
            $.post(ajaxurl, {
                action: 'boxmoe_ban_user',
                user_id: btn.data('uid'),
                days: $('#boxmoe_ban_days').val(),
                reason: $('#boxmoe_ban_reason').val(),
                _nonce: '<?php echo wp_create_nonce("boxmoe_ban"); ?>'
            }, function(res) {
                if (res.success) location.reload();
                else alert(res.data.message);
            });
        });
        $('#boxmoe-unban-btn').on('click', function() {
            if (!confirm('确定解除封禁？')) return;
            var btn = $(this).prop('disabled', true).text('处理中...');
            $.post(ajaxurl, {
                action: 'boxmoe_unban_user',
                user_id: btn.data('uid'),
                _nonce: '<?php echo wp_create_nonce("boxmoe_ban"); ?>'
            }, function(res) {
                if (res.success) location.reload();
                else alert(res.data.message);
            });
        });
        // 禁言
        $('#boxmoe-mute-btn').on('click', function() {
            if (!confirm('确定禁言该用户？')) return;
            var btn = $(this).prop('disabled', true).text('处理中...');
            $.post(ajaxurl, {
                action: 'boxmoe_mute_user',
                user_id: btn.data('uid'),
                days: $('#boxmoe_mute_days').val(),
                mute_type: $('#boxmoe_mute_type').val()
            }, function(res) {
                if (res.success) location.reload();
                else alert(res.data.message);
            });
        });
        $('#boxmoe-unmute-btn').on('click', function() {
            if (!confirm('确定解除禁言？')) return;
            var btn = $(this).prop('disabled', true).text('处理中...');
            $.post(ajaxurl, {
                action: 'boxmoe_unmute_user',
                user_id: btn.data('uid')
            }, function(res) {
                if (res.success) location.reload();
                else alert(res.data.message);
            });
        });
    });
    </script>
    <?php
}
add_action('show_user_profile', 'boxmoe_ban_admin_fields');
add_action('edit_user_profile', 'boxmoe_ban_admin_fields');

/**
 * AJAX 封禁
 */
function boxmoe_ajax_ban_user() {
    if (!current_user_can('manage_options') || !wp_verify_nonce($_POST['_nonce'] ?? '', 'boxmoe_ban')) {
        wp_die('无权限');
    }
    $uid = (int)$_POST['user_id'];
    $days = $_POST['days'];
    $reason = isset($_POST['reason']) ? sanitize_text_field($_POST['reason']) : '';
    if ($days === '-1') {
        update_user_meta($uid, 'boxmoe_ban_until', '-1');
    } else {
        update_user_meta($uid, 'boxmoe_ban_until', time() + (int)$days * 86400);
    }
    if ($reason) update_user_meta($uid, 'boxmoe_ban_reason', $reason);
    wp_send_json_success();
}
add_action('wp_ajax_boxmoe_ban_user', 'boxmoe_ajax_ban_user');

/**
 * AJAX 解封
 */
function boxmoe_ajax_unban_user() {
    if (!current_user_can('manage_options') || !wp_verify_nonce($_POST['_nonce'] ?? '', 'boxmoe_ban')) {
        wp_die('无权限');
    }
    delete_user_meta((int)$_POST['user_id'], 'boxmoe_ban_until');
    wp_send_json_success();
}
add_action('wp_ajax_boxmoe_unban_user', 'boxmoe_ajax_unban_user');

/**
 * 确保用户编辑页加载媒体库（修复 Simple Local Avatars 不显示上传按钮）
 */
function boxmoe_enqueue_media_on_profile() {
    $screen = get_current_screen();
    if ($screen && in_array($screen->base, ['profile', 'user-edit'])) {
        wp_enqueue_media();
    }
}
add_action('admin_enqueue_scripts', 'boxmoe_enqueue_media_on_profile');

// ==================== 禁言系统 ====================

/**
 * 检查用户是否被禁言
 * @return false|array ['until'=>'...', 'type'=>'comments'|'posts'|'all']
 */
function boxmoe_get_user_mute($user_id) {
    if (!$user_id) return false;
    $until = get_user_meta($user_id, 'boxmoe_mute_until', true);
    if (empty($until)) return false;
    if ($until !== '-1' && time() > (int)$until) {
        delete_user_meta($user_id, 'boxmoe_mute_until');
        delete_user_meta($user_id, 'boxmoe_mute_type');
        return false;
    }
    return [
        'until' => $until === '-1' ? '永久' : date('Y-m-d H:i', (int)$until),
        'type'  => get_user_meta($user_id, 'boxmoe_mute_type', true) ?: 'all',
    ];
}

/**
 * 检查当前用户是否可以发表评论
 */
function boxmoe_can_comment($user_id) {
    $mute = boxmoe_get_user_mute($user_id);
    if (!$mute) return true;
    return !in_array($mute['type'], ['comments', 'all']);
}

/**
 * 检查当前用户是否可以发布文章/说说
 */
function boxmoe_can_post($user_id) {
    $mute = boxmoe_get_user_mute($user_id);
    if (!$mute) return true;
    return !in_array($mute['type'], ['posts', 'all']);
}

/**
 * 拦截禁言用户评论
 */
function boxmoe_mute_block_comment($comment_data) {
    $uid = isset($comment_data['user_ID']) ? (int)$comment_data['user_ID'] : 0;
    if ($uid && !boxmoe_can_comment($uid)) {
        $mute = boxmoe_get_user_mute($uid);
        wp_die('你已被禁言至 ' . $mute['until'] . '，无法发表评论。', '禁言', ['response' => 403, 'back_link' => true]);
    }
    return $comment_data;
}
add_filter('preprocess_comment', 'boxmoe_mute_block_comment', 1);

/**
 * 拦截禁言用户发布文章/说说
 */
function boxmoe_mute_block_post($post_id, $post, $update) {
    if ($update) return;
    if (wp_is_post_revision($post_id) || wp_is_post_autosave($post_id)) return;
    $uid = (int)$post->post_author;
    if ($uid && !boxmoe_can_post($uid)) {
        $mute = boxmoe_get_user_mute($uid);
        wp_die('你已被禁言至 ' . $mute['until'] . '，无法发布内容。', '禁言', ['response' => 403, 'back_link' => true]);
    }
}
add_action('save_post', 'boxmoe_mute_block_post', 1, 3);

// ==================== 禁言管理 ====================

/**
 * AJAX 禁言
 */
function boxmoe_ajax_mute_user() {
    if (!current_user_can('manage_options')) wp_die('无权限');
    $uid = (int)$_POST['user_id'];
    $days = $_POST['days'];
    $type = isset($_POST['mute_type']) ? sanitize_text_field($_POST['mute_type']) : 'all';
    if (!in_array($type, ['comments', 'posts', 'all'])) $type = 'all';
    if ($days === '-1') {
        update_user_meta($uid, 'boxmoe_mute_until', '-1');
    } else {
        update_user_meta($uid, 'boxmoe_mute_until', time() + (int)$days * 86400);
    }
    update_user_meta($uid, 'boxmoe_mute_type', $type);
    wp_send_json_success();
}
add_action('wp_ajax_boxmoe_mute_user', 'boxmoe_ajax_mute_user');

/**
 * AJAX 解除禁言
 */
function boxmoe_ajax_unmute_user() {
    if (!current_user_can('manage_options')) wp_die('无权限');
    $uid = (int)$_POST['user_id'];
    delete_user_meta($uid, 'boxmoe_mute_until');
    delete_user_meta($uid, 'boxmoe_mute_type');
    wp_send_json_success();
}
add_action('wp_ajax_boxmoe_unmute_user', 'boxmoe_ajax_unmute_user');

/**
 * 全站屏蔽被封用户显示名
 */
function boxmoe_ban_filter_display_name($display_name, $user_id = null) {
    if (!$user_id) return $display_name;
    if (function_exists('boxmoe_is_user_banned') && boxmoe_is_user_banned($user_id) && !current_user_can('manage_options')) {
        return '封禁用户';
    }
    return $display_name;
}
add_filter('the_author', 'boxmoe_ban_filter_display_name', 10, 2);

/**
 * 评论区也屏蔽被封用户
 */
function boxmoe_ban_filter_comment_author($author) {
    if (empty($author)) return $author;
    $user = get_user_by('login', $author);
    if (!$user) $user = get_user_by('slug', $author);
    if (!$user) return $author;
    if (function_exists('boxmoe_is_user_banned') && boxmoe_is_user_banned($user->ID) && !current_user_can('manage_options')) {
        return '封禁用户';
    }
    return $author;
}
add_filter('get_comment_author', 'boxmoe_ban_filter_comment_author', 10);
add_filter('comment_author', 'boxmoe_ban_filter_comment_author', 10);

/**
 * 评论区屏蔽被封用户链接
 */
function boxmoe_ban_filter_comment_author_url($url, $comment_id) {
    $comment = get_comment($comment_id);
    if (!$comment || !$comment->user_id) return $url;
    if (function_exists('boxmoe_is_user_banned') && boxmoe_is_user_banned($comment->user_id) && !current_user_can('manage_options')) {
        return '';
    }
    return $url;
}
add_filter('get_comment_author_url', 'boxmoe_ban_filter_comment_author_url', 10, 2);

/**
 * 全站屏蔽被封用户头像
 */
function boxmoe_ban_filter_avatar($url, $id_or_email, $args) {
    $user_id = 0;
    if (is_numeric($id_or_email)) {
        $user_id = (int)$id_or_email;
    } elseif ($id_or_email instanceof WP_User) {
        $user_id = $id_or_email->ID;
    } elseif (is_string($id_or_email) && is_email($id_or_email)) {
        $user = get_user_by('email', $id_or_email);
        if ($user) $user_id = $user->ID;
    } elseif ($id_or_email instanceof WP_Comment) {
        if ($id_or_email->user_id) $user_id = $id_or_email->user_id;
    }
    if ($user_id && function_exists('boxmoe_is_user_banned') && boxmoe_is_user_banned($user_id) && !current_user_can('manage_options')) {
        return get_avatar_url(0, $args);
    }
    return $url;
}
add_filter('get_avatar_url', 'boxmoe_ban_filter_avatar', 10, 3);

