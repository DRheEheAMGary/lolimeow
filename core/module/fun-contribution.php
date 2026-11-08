<?php
/**
 * 贡献值系统模块
 * @link https://www.boxmoe.com
 * @package lolimeow
 */

// 安全设置
if (!defined('ABSPATH')) {
    echo 'Look your sister';
    exit;
}

// ========== 每日定时重算 ==========

/**
 * 注册每日凌晨重算贡献值的 Cron 任务
 */
function boxmoe_contrib_cron_register($schedules) {
    $schedules['daily_2am'] = [
        'interval' => 86400,
        'display'  => '每天凌晨2点',
    ];
    return $schedules;
}
add_filter('cron_schedules', 'boxmoe_contrib_cron_register');

function boxmoe_contrib_cron_activate() {
    if (!wp_next_scheduled('boxmoe_contrib_daily_recalc')) {
        wp_schedule_event(strtotime('tomorrow 02:00:00'), 'daily_2am', 'boxmoe_contrib_daily_recalc');
    }
}
add_action('init', 'boxmoe_contrib_cron_activate');

function boxmoe_contrib_cron_deactivate() {
    wp_clear_scheduled_hook('boxmoe_contrib_daily_recalc');
}

/**
 * Cron 回调：重算全站贡献值
 */
function boxmoe_contrib_cron_handler() {
    boxmoe_contrib_recalculate_all();
}
add_action('boxmoe_contrib_daily_recalc', 'boxmoe_contrib_cron_handler');

// ========== 常量定义 ==========
// 文章贡献基数
define('CONTRIB_ARTICLE_BASE', 20);       // 发布文章基础贡献
define('CONTRIB_ARTICLE_VIEW', 0.05);     // 每浏览贡献
define('CONTRIB_ARTICLE_LIKE', 2);        // 每被赞贡献（文章作者获得）
define('CONTRIB_ARTICLE_COMMENTED', 3);   // 每被评论贡献（文章作者获得）
define('CONTRIB_COMMENT_MAKE', 2);        // 每发表评论贡献（评论者获得）

// 说说折半系数
define('CONTRIB_SHUOSHUO_RATIO', 0.5);

// 用户 meta key
define('CONTRIB_META_KEY', 'boxmoe_contribution');
define('CONTRIB_LOG_KEY', 'boxmoe_contribution_log');
define('CONTRIB_LOG_MAX', 200);           // 日志最大保留条数

// ========== 工具函数 ==========

/**
 * 判断文章是否为说说
 */
function boxmoe_is_shuoshuo($post_id) {
    // 已发布：直接查分类
    if (get_post_status($post_id) === 'publish') {
        return has_category('shuoshuo', $post_id);
    }
    // 发布中：从 POST 数据预检（分类尚未保存）
    if (isset($_POST['post_category']) && is_array($_POST['post_category'])) {
        $cat_ids = array_map('intval', $_POST['post_category']);
        $shuoshuo = get_category_by_slug('shuoshuo');
        if ($shuoshuo && in_array($shuoshuo->term_id, $cat_ids)) return true;
    }
    if (isset($_POST['tax_input']['category']) && is_array($_POST['tax_input']['category'])) {
        $shuoshuo = get_category_by_slug('shuoshuo');
        if ($shuoshuo && in_array($shuoshuo->term_id, $_POST['tax_input']['category'])) return true;
    }
    return false;
}

/**
 * 获取贡献值倍数（说说折半）
 */
function boxmoe_get_contrib_multiplier($post_id) {
    return boxmoe_is_shuoshuo($post_id) ? CONTRIB_SHUOSHUO_RATIO : 1.0;
}

/**
 * 获取用户贡献总值
 */
function boxmoe_get_contribution($user_id = null) {
    if (!$user_id) {
        $user_id = get_current_user_id();
    }
    if (!$user_id) return 0;
    return (float) get_user_meta($user_id, CONTRIB_META_KEY, true);
}

/**
 * 增加用户贡献值
 */
function boxmoe_add_contribution($user_id, $amount, $reason = '', $post_id = 0) {
    if (!$user_id || $amount == 0) return false;

    $current = (float) get_user_meta($user_id, CONTRIB_META_KEY, true);
    $new_value = round($current + $amount, 4);
    update_user_meta($user_id, CONTRIB_META_KEY, $new_value);

    // 记录日志
    boxmoe_log_contribution($user_id, $amount, $reason, $post_id);

    return $new_value;
}

/**
 * 记录贡献值变动日志
 */
function boxmoe_log_contribution($user_id, $amount, $reason = '', $post_id = 0) {
    $log = get_user_meta($user_id, CONTRIB_LOG_KEY, true);
    if (!is_array($log)) {
        $log = array();
    }

    $entry = array(
        'time'    => current_time('mysql'),
        'amount'  => round($amount, 4),
        'reason'  => $reason,
        'post_id' => (int) $post_id,
    );

    array_unshift($log, $entry);

    // 限制日志条数
    if (count($log) > CONTRIB_LOG_MAX) {
        $log = array_slice($log, 0, CONTRIB_LOG_MAX);
    }

    update_user_meta($user_id, CONTRIB_LOG_KEY, $log);
}

/**
 * 获取贡献值变动历史
 */
function boxmoe_get_contribution_log($user_id = null, $limit = 20) {
    if (!$user_id) {
        $user_id = get_current_user_id();
    }
    if (!$user_id) return array();

    $log = get_user_meta($user_id, CONTRIB_LOG_KEY, true);
    if (!is_array($log)) return array();

    return array_slice($log, 0, $limit);
}

// ========== 1. 每日统计重算 ==========

/**
 * 全量重算贡献值（每日 cron 调用，也可在后台手动触发）
 * 基于文章、浏览、点赞、评论历史数据精确计算
 * 说说自动折半
 */
function boxmoe_contrib_recalculate_all() {
    global $wpdb;
    set_time_limit(300);

    // 1. 备份管理员赋值记录（不清除）
    $admin_logs = [];
    $all_logs = $wpdb->get_results(
        $wpdb->prepare("SELECT user_id, meta_value FROM $wpdb->usermeta WHERE meta_key = %s", CONTRIB_LOG_KEY)
    );
    foreach ($all_logs as $row) {
        $entries = maybe_unserialize($row->meta_value);
        if (!is_array($entries)) continue;
        foreach ($entries as $entry) {
            $reason = isset($entry['reason']) ? $entry['reason'] : '';
            if (strpos($reason, '管理员调整') === 0) {
                if (!isset($admin_logs[$row->user_id])) $admin_logs[$row->user_id] = [];
                $admin_logs[$row->user_id][] = $entry;
            }
        }
    }

    // 2. 清空
    $wpdb->delete($wpdb->usermeta, ['meta_key' => CONTRIB_META_KEY]);
    $wpdb->delete($wpdb->usermeta, ['meta_key' => CONTRIB_LOG_KEY]);

    $stats = ['posts' => 0, 'shuoshuo' => 0, 'comments' => 0, 'views' => 0, 'likes' => 0, 'users' => []];

    // 2. 一次性取出所有发布文章
    $posts = $wpdb->get_results(
        "SELECT ID, post_author FROM $wpdb->posts WHERE post_type = 'post' AND post_status = 'publish'"
    );
    $shuoshuo_cat = get_category_by_slug('shuoshuo');

    foreach ($posts as $p) {
        $pid = (int) $p->ID;
        $uid = (int) $p->post_author;
        if (!$uid) continue;

        $is_shuoshuo = $shuoshuo_cat && has_category($shuoshuo_cat->term_id, $pid);
        $mult = $is_shuoshuo ? CONTRIB_SHUOSHUO_RATIO : 1.0;

        $stats['users'][$uid] = true;
        if ($is_shuoshuo) $stats['shuoshuo']++; else $stats['posts']++;

        // 发布基础分
        boxmoe_add_contribution($uid, CONTRIB_ARTICLE_BASE * $mult, $is_shuoshuo ? '发布说说' : '发布文章', $pid);

        // 浏览贡献
        $views = (int) get_post_meta($pid, 'post_views_count', true);
        if ($views > 0) {
            boxmoe_add_contribution($uid, CONTRIB_ARTICLE_VIEW * $mult * $views, '文章浏览', $pid);
            $stats['views'] += $views;
        }

        // 点赞贡献
        $likes = (int) get_post_meta($pid, 'post_likes_count', true);
        if ($likes > 0) {
            boxmoe_add_contribution($uid, CONTRIB_ARTICLE_LIKE * $mult * $likes, '文章被点赞', $pid);
            $stats['likes'] += $likes;
        }
    }

    // 3. 评论贡献（批量查）
    $comments = $wpdb->get_results(
        "SELECT c.comment_ID, c.comment_post_ID, c.user_id, p.post_author
         FROM $wpdb->comments c
         JOIN $wpdb->posts p ON p.ID = c.comment_post_ID
         WHERE c.comment_approved = '1' AND p.post_status = 'publish'"
    );
    foreach ($comments as $c) {
        $cid     = (int) $c->comment_post_ID;
        $commenter = (int) $c->user_id;
        $author  = (int) $c->post_author;
        if (!$author) continue;

        $is_s = $shuoshuo_cat && has_category($shuoshuo_cat->term_id, $cid);
        $mult = $is_s ? CONTRIB_SHUOSHUO_RATIO : 1.0;

        // 文章作者得被评论分（自评除外）
        if ($commenter !== $author) {
            boxmoe_add_contribution($author, CONTRIB_ARTICLE_COMMENTED * $mult, '文章收到评论', $cid);
            $stats['users'][$author] = true;
        }
        // 评论者得分
        if ($commenter) {
            boxmoe_add_contribution($commenter, CONTRIB_COMMENT_MAKE * $mult, $is_s ? '评论说说' : '发表评论', $cid);
            $stats['users'][$commenter] = true;
        }
        $stats['comments']++;
    }

    // 4. 恢复管理员赋值记录
    if (!empty($admin_logs)) {
        foreach ($admin_logs as $uid => $entries) {
            foreach ($entries as $entry) {
                boxmoe_add_contribution(
                    $uid,
                    $entry['amount'],
                    $entry['reason'],
                    isset($entry['post_id']) ? $entry['post_id'] : 0
                );
            }
        }
    }

    return $stats;
}

// ========== 5. 工具：手动修正贡献值（管理员专用） ==========

/**
 * 管理员手动调整用户贡献值
 */
function boxmoe_admin_adjust_contribution($user_id, $amount, $reason = '管理员手动调整') {
    if (!current_user_can('manage_options')) return false;
    return boxmoe_add_contribution($user_id, $amount, $reason);
}

// ========== 6. 短代码：用户贡献值显示 ==========

/**
 * [contribution] 短代码 - 显示当前用户贡献值
 * [contribution user_id="1"] - 显示指定用户贡献值
 */
function boxmoe_sc_contribution($atts) {
    $atts = shortcode_atts(array(
        'user_id' => 0,
    ), $atts);

    $user_id = (int)$atts['user_id'] ?: get_current_user_id();
    if (!$user_id) return '0';

    return number_format(boxmoe_get_contribution($user_id), 2);
}
add_shortcode('contribution', 'boxmoe_sc_contribution');

/**
 * [contribution_tag] — 显示当前用户等级Tag
 * [contribution_tag user_id="1"] — 显示指定用户
 */
function boxmoe_sc_contribution_tag($atts) {
    $atts = shortcode_atts(['user_id' => 0], $atts);
    $uid = (int) $atts['user_id'] ?: get_current_user_id();
    if (!$uid) return '';
    return esc_html(boxmoe_get_user_tag_display($uid));
}
add_shortcode('contribution_tag', 'boxmoe_sc_contribution_tag');

/**
 * [contribution_rank] — 贡献值排行榜
 * [contribution_rank count="10"] — 显示前10名
 */
function boxmoe_sc_contribution_rank($atts) {
    $atts = shortcode_atts(['count' => 10], $atts);
    $limit = min(50, max(1, (int)$atts['count']));

    global $wpdb;
    $rows = $wpdb->get_results($wpdb->prepare(
        "SELECT user_id, meta_value FROM $wpdb->usermeta WHERE meta_key = %s ORDER BY CAST(meta_value AS DECIMAL(20,2)) DESC LIMIT %d",
        'boxmoe_contribution', $limit
    ));

    if (empty($rows)) return '<p class="text-muted">暂无排行数据</p>';

    $out = '<div class="contrib-rank-list">';
    $out .= '<ol class="list-unstyled">';
    $rank = 1;
    $colors = [1 => '#fbbf24', 2 => '#94a3b8', 3 => '#d97706'];
    foreach ($rows as $row) {
        $uid   = (int)$row->user_id;
        $val   = number_format((float)$row->meta_value, 2);
        $name  = get_userdata($uid) ? get_userdata($uid)->display_name : '用户#'.$uid;
        $rc    = isset($colors[$rank]) ? $colors[$rank] : 'var(--bs-secondary-color)';
        $out .= '<li class="d-flex align-items-center gap-3 py-2 border-bottom" style="font-size:0.9rem;">';
        $out .= '<span class="fw-bold" style="width:24px;text-align:center;color:'.$rc.';">'.$rank.'</span>';
        $out .= '<span class="flex-fill"><a href="'.esc_url(boxmoe_get_user_profile_url($uid)).'" class="text-reset text-decoration-none">'.esc_html($name).'</a> '.boxmoe_get_user_tag_badge($uid).'</span>';
        $out .= '<span style="color:var(--bs-primary);font-weight:600;">'.$val.'</span>';
        $out .= '</li>';
        $rank++;
    }
    $out .= '</ol></div>';
    return $out;
}
add_shortcode('contribution_rank', 'boxmoe_sc_contribution_rank');

// ========== 7. 后台管理页面 ==========

/**
 * 注册后台管理菜单
 */
add_action('admin_menu', 'boxmoe_contrib_admin_menu');
function boxmoe_contrib_admin_menu() {
    add_menu_page(
        '贡献值管理',
        '贡献值管理',
        'manage_options',
        'boxmoe-contribution',
        'boxmoe_contrib_admin_page',
        'dashicons-awards',
        30
    );

    add_submenu_page(
        'boxmoe-contribution',
        '贡献排行榜',
        '贡献排行榜',
        'manage_options',
        'boxmoe-contribution',
        'boxmoe_contrib_admin_page'
    );

    // 用户详情页：null 父菜单 = 注册路由但不在侧栏显示，仅通过排行榜链接访问
    add_submenu_page(
        null,
        '用户贡献详情',
        '用户详情',
        'manage_options',
        'boxmoe-contrib-user',
        'boxmoe_contrib_user_detail_page'
    );
}

/**
 * 后台管理页面CSS
 */
add_action('admin_head', 'boxmoe_contrib_admin_css');
function boxmoe_contrib_admin_css() {
    $screen = get_current_screen();
    if (!$screen || strpos($screen->id, 'boxmoe-contrib') === false) return;
    ?>
<style>
.contrib-wrap { max-width: 1200px; margin: 20px auto 20px 0; padding-left: 20px; }
.contrib-wrap .postbox-header h2 { padding: 8px 12px !important; margin: 0 !important; font-size: 15px !important; }
.contrib-header { display: flex; align-items: center; gap: 12px; margin-bottom: 20px; }
.contrib-header h1 { margin: 0; font-size: 23px; }
.contrib-stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 16px; margin-bottom: 24px; }
.contrib-stat-card { background: #fff; border: 1px solid #e2e8f0; border-radius: 10px; padding: 18px 20px; box-shadow: 0 1px 3px rgba(0,0,0,.04); }
.contrib-stat-card .stat-label { font-size: 13px; color: #64748b; margin-bottom: 4px; }
.contrib-stat-card .stat-value { font-size: 26px; font-weight: 700; color: #1e293b; }
.contrib-stat-card .stat-sub { font-size: 12px; color: #94a3b8; margin-top: 2px; }
.contrib-rank-badge { display: inline-flex; align-items: center; justify-content: center; width: 28px; height: 28px; border-radius: 50%; font-weight: 700; font-size: 13px; }
.contrib-rank-1 { background: #fbbf24; color: #92400e; }
.contrib-rank-2 { background: #cbd5e1; color: #475569; }
.contrib-rank-3 { background: #fed7aa; color: #9a3412; }
.contrib-rank-n { background: #f1f5f9; color: #64748b; }
.contrib-user-cell { display: flex; align-items: center; gap: 10px; }
.contrib-user-cell img { width: 32px; height: 32px; border-radius: 50%; }
.contrib-user-cell a { font-weight: 600; }
.contrib-change-plus { color: #10b981; font-weight: 600; }
.contrib-change-minus { color: #ef4444; font-weight: 600; }
.contrib-empty { text-align: center; padding: 60px 20px; color: #94a3b8; }
.contrib-empty .dashicons { font-size: 48px; width: 48px; height: 48px; margin-bottom: 12px; }
/* 弹窗 */
.contrib-modal-overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,.45); z-index: 9999; justify-content: center; align-items: center; }
.contrib-modal-overlay.active { display: flex; }
.contrib-modal { background: #fff; border-radius: 12px; width: 700px; max-height: 80vh; overflow-y: auto; box-shadow: 0 20px 60px rgba(0,0,0,.15); }
.contrib-modal-header { padding: 16px 24px; border-bottom: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center; position: sticky; top: 0; background: #fff; z-index: 1; border-radius: 12px 12px 0 0; }
.contrib-modal-header h2 { margin: 0; font-size: 16px; }
.contrib-modal-close { background: none; border: none; font-size: 20px; cursor: pointer; color: #64748b; padding: 4px 8px; border-radius: 4px; }
.contrib-modal-close:hover { background: #f1f5f9; color: #1e293b; }
.contrib-modal-body { padding: 20px 24px; }
.contrib-log-list { list-style: none; margin: 0; padding: 0; }
.contrib-log-item { display: flex; align-items: center; gap: 12px; padding: 10px 0; border-bottom: 1px solid #f1f5f9; }
.contrib-log-item:last-child { border-bottom: none; }
.contrib-log-icon { width: 36px; height: 36px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 14px; flex-shrink: 0; }
.contrib-log-icon.publish { background: #dbeafe; color: #2563eb; }
.contrib-log-icon.view { background: #f0fdf4; color: #16a34a; }
.contrib-log-icon.like { background: #fef2f2; color: #dc2626; }
.contrib-log-icon.comment { background: #fefce8; color: #ca8a04; }
.contrib-log-icon.admin { background: #f3e8ff; color: #9333ea; }
.contrib-log-info { flex: 1; min-width: 0; }
.contrib-log-info .log-reason { font-weight: 500; }
.contrib-log-info .log-time { font-size: 12px; color: #94a3b8; }
.contrib-log-amount { font-weight: 700; font-size: 15px; white-space: nowrap; }
/* 用户详情页 */
.contrib-user-header { background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 28px; margin-bottom: 24px; display: flex; gap: 24px; align-items: center; box-shadow: 0 1px 3px rgba(0,0,0,.04); }
.contrib-user-header img { width: 72px; height: 72px; border-radius: 50%; }
.contrib-user-header .user-info { flex: 1; }
.contrib-user-header .user-name { font-size: 20px; font-weight: 700; margin-bottom: 4px; }
.contrib-user-header .user-meta { font-size: 13px; color: #64748b; }
.contrib-user-header .user-score { text-align: center; }
.contrib-user-header .user-score .score-value { font-size: 36px; font-weight: 800; color: #1e293b; }
.contrib-user-header .user-score .score-label { font-size: 12px; color: #94a3b8; }
.contrib-back-link { margin-bottom: 16px; }
.contrib-back-link a { text-decoration: none; }
/* 调整弹窗 */
.contrib-adjust-form { display: flex; gap: 8px; align-items: flex-end; flex-wrap: wrap; }
.contrib-adjust-form input[type="number"] { width: 120px; }
.contrib-adjust-form .button { margin-bottom: 0; }
</style>
    <?php
}

/**
 * 贡献排行榜主页
 */
function boxmoe_contrib_admin_page() {
    // 显示调整结果消息
    if (isset($_GET['adjusted'])) {
        $adj_user = isset($_GET['u']) ? get_userdata((int)$_GET['u']) : null;
        $adj_name = $adj_user ? $adj_user->user_login : '用户';
        echo '<div class="notice notice-success is-dismissible"><p>已成功调整 <strong>' . esc_html($adj_name) . '</strong> 的贡献值。</p></div>';
    }

    // 显示重算结果
    if (isset($_GET['recalculated'])) {
        $rc = $_GET;
        $msg = sprintf(
            '重算完成！共处理 <strong>%d</strong> 篇文章、<strong>%d</strong> 篇说说、<strong>%d</strong> 条评论。',
            (int)($rc['posts'] ?? 0),
            (int)($rc['shuoshuo'] ?? 0),
            (int)($rc['comments'] ?? 0)
        );
        echo '<div class="notice notice-success is-dismissible"><p>' . $msg . '</p></div>';
    }

    $paged = max(1, isset($_GET['cp']) ? (int)$_GET['cp'] : 1);
    $per_page = 20;
    $offset = ($paged - 1) * $per_page;

    // 获取所有用户（含贡献值为0的）
    global $wpdb;
    $meta_key = CONTRIB_META_KEY;

    // 统计总数（全部用户）
    $total_users = $wpdb->get_var("SELECT COUNT(ID) FROM $wpdb->users");

    $total_pages = ceil($total_users / $per_page);

    // 获取用户列表按贡献值降序（LEFT JOIN 包含无贡献值用户）
    $users = $wpdb->get_results($wpdb->prepare(
        "SELECT u.ID, u.user_login, u.user_email, u.user_registered,
                COALESCE(um.meta_value, '0') as contribution
         FROM $wpdb->users u
         LEFT JOIN $wpdb->usermeta um ON u.ID = um.user_id AND um.meta_key = %s
         ORDER BY CAST(COALESCE(um.meta_value, '0') AS DECIMAL(20,4)) DESC
         LIMIT %d OFFSET %d",
        $meta_key, $per_page, $offset
    ));

    // 统计数据
    $total_contrib = $wpdb->get_var($wpdb->prepare(
        "SELECT SUM(CAST(meta_value AS DECIMAL(20,4))) FROM $wpdb->usermeta WHERE meta_key = %s",
        $meta_key
    ));
    $total_contrib = $total_contrib ?: 0;

    // 扣除管理员赋值部分（从日志中统计）
    $admin_contrib = 0;
    $log_rows = $wpdb->get_results($wpdb->prepare(
        "SELECT user_id, meta_value FROM $wpdb->usermeta WHERE meta_key = %s",
        CONTRIB_LOG_KEY
    ));
    foreach ($log_rows as $lr) {
        $entries = maybe_unserialize($lr->meta_value);
        if (!is_array($entries)) continue;
        foreach ($entries as $e) {
            if (isset($e['reason']) && strpos($e['reason'], '管理员调整') === 0) {
                $admin_contrib += (float)$e['amount'];
            }
        }
    }
    $display_contrib = max(0, $total_contrib - $admin_contrib);

    // 有贡献值的用户数
    $active_contributors = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(DISTINCT user_id) FROM $wpdb->usermeta WHERE meta_key = %s AND CAST(meta_value AS DECIMAL(20,4)) > 0",
        $meta_key
    ));

    // 最高贡献者
    $top_user = $wpdb->get_row($wpdb->prepare(
        "SELECT u.ID, u.user_login, COALESCE(um.meta_value, '0') as contribution
         FROM $wpdb->users u
         LEFT JOIN $wpdb->usermeta um ON u.ID = um.user_id AND um.meta_key = %s
         ORDER BY CAST(COALESCE(um.meta_value, '0') AS DECIMAL(20,4)) DESC LIMIT 1",
        $meta_key
    ));
    ?>
    <div class="wrap contrib-wrap">
        <div class="contrib-header">
            <h1>贡献值管理</h1>
            <form method="post" style="margin-left:auto;" onsubmit="return confirm('确定重新统计所有用户的贡献值？\n\n这将清空现有数据并根据所有文章、浏览、点赞、评论重新计算。');">
                <?php wp_nonce_field('boxmoe_contrib_recalc', '_recalc_nonce'); ?>
                <input type="hidden" name="page" value="boxmoe-contribution">
                <button type="submit" name="contrib_recalc_submit" class="button button-secondary">
                    重新统计
                </button>
            </form>
            <form method="post" style="display:inline;margin-left:8px;"
                  onsubmit="return confirm('确定清空全站所有用户的贡献值？\n\n贡献值将归零，日志将被清空。\n等级标签不受影响。\n此操作不可恢复！');">
                <?php wp_nonce_field('boxmoe_contrib_clearall', '_clearall_nonce'); ?>
                <input type="hidden" name="page" value="boxmoe-contribution">
                <button type="submit" name="contrib_clearall_submit" class="button button-small" style="color:#dc3232;border-color:#dc3232;">
                    全站清空
                </button>
            </form>
        </div>

        <!-- 统计卡片 -->
        <div class="contrib-stats">
            <div class="contrib-stat-card">
                <div class="stat-label">总贡献值</div>
                <div class="stat-value"><?php echo number_format($display_contrib, 2); ?></div>
                <div class="stat-sub">全站累计（不含管理员赋值）</div>
            </div>
            <div class="contrib-stat-card">
                <div class="stat-label">用户总数</div>
                <div class="stat-value"><?php echo (int)$total_users; ?></div>
                <div class="stat-sub"><?php echo (int)$active_contributors; ?> 人拥有贡献值</div>
            </div>
            <div class="contrib-stat-card">
                <div class="stat-label">榜首</div>
                <div class="stat-value"><?php echo ($top_user && (float)$top_user->contribution > 0) ? esc_html($top_user->user_login) : '—'; ?></div>
                <div class="stat-sub">
                    <?php echo ($top_user && (float)$top_user->contribution > 0) ? number_format((float)$top_user->contribution, 2) . ' 分' : '暂无数据'; ?>
                </div>
            </div>
            <div class="contrib-stat-card">
                <div class="stat-label">人均贡献</div>
                <div class="stat-value">
                    <?php echo $total_users > 0 ? number_format($display_contrib / $total_users, 2) : '0.00'; ?>
                </div>
                <div class="stat-sub">总贡献 / 全部用户</div>
            </div>
        </div>

        <!-- 排行榜表格 -->
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th style="width:50px;">排名</th>
                    <th>用户</th>
                    <th style="width:120px;">注册时间</th>
                    <th style="width:100px;">贡献值</th>
                    <th style="width:200px;">操作</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($users)): ?>
                <tr>
                    <td colspan="5">
                        <div class="contrib-empty">
                            <div class="dashicons dashicons-chart-area"></div>
                            <p>还没有用户获得贡献值，等待网站活动的发生。</p>
                        </div>
                    </td>
                </tr>
                <?php else: ?>
                    <?php foreach ($users as $index => $user):
                        $rank = $offset + $index + 1;
                        $rank_class = 'contrib-rank-n';
                        if ($rank === 1) $rank_class = 'contrib-rank-1';
                        elseif ($rank === 2) $rank_class = 'contrib-rank-2';
                        elseif ($rank === 3) $rank_class = 'contrib-rank-3';

                        // 获取最近一次贡献变动
                        $log = boxmoe_get_contribution_log($user->ID, 1);
                        $last_change = !empty($log) ? $log[0] : null;
                    ?>
                <tr>
                    <td><span class="contrib-rank-badge <?php echo $rank_class; ?>"><?php echo $rank; ?></span></td>
                    <td>
                        <div class="contrib-user-cell">
                            <?php echo get_avatar($user->ID, 32, '', '', array('class' => 'avatar')); ?>
                            <div>
                                <a href="<?php echo admin_url('admin.php?page=boxmoe-contrib-user&user_id=' . $user->ID); ?>">
                                    <?php echo esc_html($user->user_login); ?>
                                </a>
                                <div style="font-size:12px;color:#94a3b8;"><?php echo esc_html($user->user_email); ?></div>
                            </div>
                        </div>
                    </td>
                    <td><?php echo date('Y-m-d', strtotime($user->user_registered)); ?></td>
                    <td>
                        <strong style="font-size:15px;"><?php echo number_format((float)$user->contribution, 2); ?></strong>
                    </td>
                    <td>
                        <a href="<?php echo admin_url('admin.php?page=boxmoe-contrib-user&user_id=' . $user->ID); ?>"
                           class="button button-small">查看详情</a>
                        <button type="button" class="button button-small"
                                onclick="boxmoeContribAdjust(<?php echo $user->ID; ?>, '<?php echo esc_js($user->user_login); ?>')">
                            调整
                        </button>
                    </td>
                </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>

        <?php if ($total_pages > 1): ?>
        <div class="tablenav" style="margin-top:16px;">
            <div class="tablenav-pages">
                <span class="displaying-num">共 <?php echo (int)$total_users; ?> 位用户</span>
                <span class="pagination-links">
                    <?php
                    $base = admin_url('admin.php?page=boxmoe-contribution&cp=%#%');
                    echo paginate_links(array(
                        'base'    => $base,
                        'format'  => '',
                        'current' => $paged,
                        'total'   => $total_pages,
                        'prev_text' => '&laquo;',
                        'next_text' => '&raquo;',
                    ));
                    ?>
                </span>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- 调整贡献值弹窗 -->
    <div id="contrib-adjust-modal" class="contrib-modal-overlay">
        <div class="contrib-modal">
            <div class="contrib-modal-header">
                <h2>调整贡献值 — <span id="adjust-user-name"></span></h2>
                <button class="contrib-modal-close" onclick="boxmoeContribModalClose()">&times;</button>
            </div>
            <div class="contrib-modal-body">
                <form method="post" action="">
                    <?php wp_nonce_field('boxmoe_contrib_adjust', '_contrib_nonce'); ?>
                    <input type="hidden" name="adjust_user_id" id="adjust-user-id" value="">
                    <input type="hidden" name="page" value="boxmoe-contribution">
                    <div class="contrib-adjust-form">
                        <input type="number" name="adjust_amount" id="adjust-amount"
                               step="0.01" placeholder="±数值" style="width:120px;" required>
                        <input type="text" name="adjust_reason" id="adjust-reason"
                               placeholder="变动原因" style="width:200px;" required>
                        <button type="submit" name="contrib_adjust_submit" class="button button-primary">
                            确认调整
                        </button>
                    </div>
                    <p class="description" style="margin-top:8px;">输入正数增加，负数扣减。例如：50 或 -10</p>
                </form>
            </div>
        </div>
    </div>
    <script>
    function boxmoeContribAdjust(userId, userName) {
        document.getElementById('adjust-user-id').value = userId;
        document.getElementById('adjust-user-name').textContent = userName;
        document.getElementById('adjust-amount').value = '';
        document.getElementById('adjust-reason').value = '';
        document.getElementById('contrib-adjust-modal').classList.add('active');
    }
    function boxmoeContribModalClose() {
        document.getElementById('contrib-adjust-modal').classList.remove('active');
    }
    document.getElementById('contrib-adjust-modal').addEventListener('click', function(e) {
        if (e.target === this) boxmoeContribModalClose();
    });
    </script>

    <!-- 贡献值计算规则总览 -->
    <div class="postbox" id="contrib-rules" style="margin-top:24px; border-radius:10px; overflow:hidden;">
        <div class="postbox-header"><h2 style="padding:8px 12px;margin:0;font-size:15px;">贡献值计算规则</h2></div>
        <div class="inside" style="margin:0; padding:0;">
            <table class="wp-list-table widefat fixed" style="border:none;">
                <thead>
                    <tr><th>事件</th><th style="width:100px;">贡献值（文章）</th><th style="width:100px;">贡献值（说说）</th><th>说明</th></tr>
                </thead>
                <tbody>
                    <tr>
                        <td>发布内容</td>
                        <td><strong>+20</strong></td>
                        <td><strong>+10</strong></td>
                        <td>文章首次发布时一次性获得</td>
                    </tr>
                    <tr>
                        <td>浏览</td>
                        <td><strong>+0.05</strong></td>
                        <td><strong>+0.025</strong></td>
                        <td>每IP每10分钟每文章限计1次，作者自览不计</td>
                    </tr>
                    <tr>
                        <td>被点赞</td>
                        <td><strong>+2</strong></td>
                        <td><strong>+1</strong></td>
                        <td>每赞计入文章作者</td>
                    </tr>
                    <tr>
                        <td>收到评论</td>
                        <td><strong>+3</strong></td>
                        <td><strong>+1.5</strong></td>
                        <td>计入文章作者，作者自己的评论不计</td>
                    </tr>
                    <tr>
                        <td>发表评论</td>
                        <td><strong>+2</strong></td>
                        <td><strong>+1</strong></td>
                        <td>计入评论者</td>
                    </tr>
                    <tr>
                        <td>管理员调整</td>
                        <td colspan="2">±任意值</td>
                        <td>通过"调整"按钮手动增减</td>
                    </tr>
                </tbody>
            </table>
            <p style="padding:12px 16px; margin:0; background:#f8fafc; color:#64748b; font-size:12px;">
                说说 = 分类为「shuoshuo」的文章，所有贡献值折半。&nbsp;
                <a href="<?php echo admin_url('admin.php?page=boxmoe-contrib-tags'); ?>">查看等级与Tag规则 →</a>
            </p>
        </div>
    </div>
    <?php
}

/**
 * 处理贡献值手动调整
 */
add_action('admin_init', 'boxmoe_contrib_handle_adjust');
function boxmoe_contrib_handle_adjust() {
    if (!isset($_POST['contrib_adjust_submit'])) return;
    if (!current_user_can('manage_options')) return;
    if (!wp_verify_nonce($_POST['_contrib_nonce'] ?? '', 'boxmoe_contrib_adjust')) {
        wp_die('安全验证失败，请刷新页面重试。');
    }

    $user_id = (int)($_POST['adjust_user_id'] ?? 0);
    $amount = (float)($_POST['adjust_amount'] ?? 0);
    $reason = sanitize_text_field($_POST['adjust_reason'] ?? '');

    if ($user_id && $amount != 0 && !empty($reason)) {
        boxmoe_add_contribution($user_id, $amount, '管理员调整: ' . $reason);
    }

    // 重定向回排行榜，去掉 POST 数据
    wp_redirect(admin_url('admin.php?page=boxmoe-contribution&adjusted=1&u=' . $user_id));
    exit;
}

/**
 * 重新统计所有贡献值
 */
add_action('admin_init', 'boxmoe_contrib_handle_recalc');
function boxmoe_contrib_handle_recalc() {
    if (!isset($_POST['contrib_recalc_submit'])) return;
    if (!current_user_can('manage_options')) return;
    if (!wp_verify_nonce($_POST['_recalc_nonce'] ?? '', 'boxmoe_contrib_recalc')) {
        wp_die('安全验证失败，请刷新页面重试。');
    }

    $stats = boxmoe_contrib_recalculate_all();

    $query = http_build_query([
        'page'         => 'boxmoe-contribution',
        'recalculated' => '1',
        'posts'        => $stats['posts'],
        'shuoshuo'     => $stats['shuoshuo'],
        'comments'     => $stats['comments'],
    ]);
    wp_redirect(admin_url('admin.php?' . $query));
    exit;
}

/**
 * 全站清空贡献值（保留等级标签）
 */
add_action('admin_init', 'boxmoe_contrib_handle_clearall');
function boxmoe_contrib_handle_clearall() {
    if (!isset($_POST['contrib_clearall_submit'])) return;
    if (!current_user_can('manage_options')) return;
    if (!wp_verify_nonce($_POST['_clearall_nonce'] ?? '', 'boxmoe_contrib_clearall')) {
        wp_die('安全验证失败');
    }

    global $wpdb;
    // 清空贡献日志
    $wpdb->delete($wpdb->usermeta, ['meta_key' => 'boxmoe_contribution_log']);
    // 贡献值归零（保留 tag）
    $wpdb->query($wpdb->prepare(
        "UPDATE $wpdb->usermeta SET meta_value = '0' WHERE meta_key = %s",
        'boxmoe_contribution'
    ));

    wp_redirect(admin_url('admin.php?page=boxmoe-contribution&cleared=1'));
    exit;
}

function boxmoe_contrib_user_detail_page() {
    $user_id = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;

    // 未指定用户：显示用户选择列表
    if (!$user_id) {
        boxmoe_contrib_user_selector_page();
        return;
    }

    $user = get_userdata($user_id);

    if (!$user) {
        echo '<div class="wrap"><h1>用户不存在</h1>';
        echo '<p>用户 ID <code>' . esc_html($user_id) . '</code> 不存在或已被删除。</p>';
        echo '<p><a href="' . admin_url('admin.php?page=boxmoe-contribution') . '" class="button">← 返回排行榜</a></p>';
        echo '<br><br>';
        boxmoe_contrib_user_selector_page();
        return;
    }

    $contribution = boxmoe_get_contribution($user_id);
    $log = boxmoe_get_contribution_log($user_id, 50);

    // 贡献来源统计
    $breakdown = array(
        '发布文章'   => 0,
        '发布说说'   => 0,
        '文章浏览'   => 0,
        '文章被点赞' => 0,
        '文章收到评论' => 0,
        '发表评论'   => 0,
        '评论说说'   => 0,
    );
    $other_total = 0;
    foreach ($log as $idx => $entry) {
        $reason = $entry['reason'];
        if (isset($breakdown[$reason])) {
            $breakdown[$reason] += $entry['amount'];
        } else {
            $other_total += $entry['amount'];
        }
    }
    if ($other_total != 0) {
        $breakdown['其他调整'] = $other_total;
    }
    ?>
    <div class="wrap contrib-wrap">
        <div class="contrib-back-link">
            <a href="<?php echo admin_url('admin.php?page=boxmoe-contribution'); ?>" class="button">
                ← 返回排行榜
            </a>
        </div>

        <!-- 用户概览卡片 -->
        <div class="contrib-user-header">
            <?php echo get_avatar($user_id, 72, '', '', array('class' => 'avatar')); ?>
            <div class="user-info">
                <div class="user-name"><?php echo esc_html($user->display_name); ?></div>
                <div class="user-meta">
                    @<?php echo esc_html($user->user_login); ?> &middot;
                    <?php echo esc_html($user->user_email); ?> &middot;
                    注册于 <?php echo date('Y-m-d', strtotime($user->user_registered)); ?>
                </div>
                <div class="user-meta">
                    角色：
                    <?php
                    global $wp_roles;
                    $role_names = array_map(function($r) use ($wp_roles) {
                        return isset($wp_roles->roles[$r]['name']) ? $wp_roles->roles[$r]['name'] : $r;
                    }, $user->roles);
                    echo esc_html(implode(', ', $role_names));
                    ?>
                </div>
            </div>
            <div class="user-score">
                <div class="score-value"><?php echo number_format($contribution, 2); ?></div>
                <div class="score-label">贡献值总分</div>
            </div>
        </div>

        <div style="display:grid; grid-template-columns: 1fr 1fr; gap: 24px;">

            <!-- 贡献构成 -->
            <div>
                <div class="postbox" style="border-radius:10px; overflow:hidden;">
                    <div class="postbox-header">
                        <h2 style="padding:8px 12px;margin:0;font-size:15px;">贡献构成</h2>
                    </div>
                    <div class="inside" style="margin:0; padding:0;">
                        <table class="wp-list-table widefat fixed" style="border:none;">
                            <tbody>
                                <?php foreach ($breakdown as $reason => $total):
                                    if ($total == 0) continue;
                                    $pct = $contribution > 0 ? ($total / $contribution * 100) : 0;
                                ?>
                                <tr>
                                    <td style="border:none;"><?php echo esc_html($reason); ?></td>
                                    <td style="border:none; text-align:right; font-weight:600;">
                                        <?php echo number_format($total, 2); ?>
                                    </td>
                                    <td style="border:none; width:80px; text-align:right; color:#94a3b8; font-size:12px;">
                                        <?php echo number_format($pct, 1); ?>%
                                    </td>
                                </tr>
                                <?php endforeach;
                                if (empty(array_filter($breakdown))): ?>
                                <tr><td colspan="3" style="text-align:center;color:#94a3b8;padding:20px;">
                                    暂无贡献记录
                                </td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- 近期变动 -->
            <div>
                <div class="postbox" style="border-radius:10px; overflow:hidden;">
                    <div class="postbox-header" style="display:flex;align-items:center;justify-content:space-between;">
                        <h2 style="padding:8px 12px;margin:0;font-size:15px;">近期变动记录</h2>
                        <button id="clear-all-log" class="button button-small" style="color:#dc3232;border-color:#dc3232;margin-right:8px;">清空全部记录</button>
                    </div>
                    <div class="inside" style="margin:0; padding:0; max-height:420px; overflow-y:auto;">
                        <?php if (empty($log)): ?>
                            <p style="text-align:center;color:#94a3b8;padding:30px;">暂无变动记录</p>
                        <?php else: ?>
                        <ul class="contrib-log-list">
                            <?php foreach ($log as $entry):
                                $icon_class = 'view';
                                $icon_dashicon = 'dashicons-visibility';
                                $reason = $entry['reason'];
                                if (strpos($reason, '管理员调整') === 0) {
                                    $icon_class = 'admin';
                                    $icon_dashicon = 'dashicons-awards';
                                } else switch ($reason) {
                                    case '发布文章':
                                    case '发布说说':
                                        $icon_class = 'publish';
                                        $icon_dashicon = 'dashicons-edit';
                                        break;
                                    case '文章被点赞':
                                        $icon_class = 'like';
                                        $icon_dashicon = 'dashicons-heart';
                                        break;
                                    case '文章收到评论':
                                    case '发表评论':
                                    case '评论说说':
                                        $icon_class = 'comment';
                                        $icon_dashicon = 'dashicons-admin-comments';
                                        break;
                                    default:
                                        $icon_class = 'view';
                                        $icon_dashicon = 'dashicons-visibility';
                                }
                                $is_positive = $entry['amount'] >= 0;
                            ?>
                            <li class="contrib-log-item">
                                <div class="contrib-log-icon <?php echo $icon_class; ?>">
                                    <span class="dashicons <?php echo $icon_dashicon; ?>" style="font-size:15px;width:15px;height:15px;"></span>
                                </div>
                                <div class="contrib-log-info">
                                    <div class="log-reason">
                                        <?php echo esc_html($entry['reason']); ?>
                                        <?php if ($entry['post_id']): ?>
                                            <a href="<?php echo get_permalink($entry['post_id']); ?>"
                                               target="_blank" style="font-size:12px;text-decoration:none;"
                                               title="查看文章">#<?php echo $entry['post_id']; ?></a>
                                        <?php endif; ?>
                                    </div>
                                    <div class="log-time"><?php echo esc_html($entry['time']); ?></div>
                                </div>
                                <div class="contrib-log-amount <?php echo $is_positive ? 'contrib-change-plus' : 'contrib-change-minus'; ?>">
                                    <?php echo $is_positive ? '+' : ''; ?><?php echo number_format($entry['amount'], 2); ?>
                                </div>
                                <button class="contrib-log-delete" data-idx="<?php echo $idx; ?>" data-user="<?php echo $user_id; ?>" title="删除此记录" style="background:none;border:none;color:#ef4444;cursor:pointer;padding:2px 6px;font-size:14px;opacity:0.6;transition:opacity 0.2s;" onmouseover="this.style.opacity='1'" onmouseout="this.style.opacity='0.6'">&times;</button>
                            </li>
                            <?php endforeach; ?>
                        </ul>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // 清空全部
        var clearBtn = document.getElementById('clear-all-log');
        if (clearBtn) {
            clearBtn.addEventListener('click', function() {
                if (!confirm('确定清空该用户全部贡献记录？贡献值将归零，不可恢复！')) return;
                this.disabled = true;
                this.textContent = '清空中...';
                var formData = new FormData();
                formData.append('action', 'boxmoe_clear_contrib_log');
                formData.append('user_id', <?php echo $user_id; ?>);
                fetch(ajaxurl, { method: 'POST', credentials: 'same-origin', body: formData })
                    .then(function(r) { return r.json(); })
                    .then(function(res) {
                        if (res.success) location.reload();
                        else alert(res.data.message || '清空失败');
                    })
                    .catch(function() { alert('请求失败'); });
            });
        }
        // 删除单条
        document.querySelectorAll('.contrib-log-delete').forEach(function(btn) {
            btn.addEventListener('click', function() {
                if (!confirm('确定删除此记录？将同时扣回对应贡献值。')) return;
                var idx = this.getAttribute('data-idx');
                var uid = this.getAttribute('data-user');
                this.disabled = true;
                this.textContent = '...';
                var li = this.closest('li');
                var formData = new FormData();
                formData.append('action', 'boxmoe_delete_contrib_log');
                formData.append('user_id', uid);
                formData.append('idx', idx);
                fetch(ajaxurl, { method: 'POST', credentials: 'same-origin', body: formData })
                    .then(function(r) { return r.json(); })
                    .then(function(res) {
                        if (res.success) {
                            location.reload();
                        } else {
                            alert(res.data.message || '删除失败');
                            btn.disabled = false;
                            btn.innerHTML = '&times;';
                        }
                    })
                    .catch(function() {
                        alert('请求失败');
                        btn.disabled = false;
                        btn.innerHTML = '&times;';
                    });
            });
        });
    });
    </script>
    <?php
}

/**
 * 用户选择器页面 —— 未指定 user_id 时展示
 */
function boxmoe_contrib_user_selector_page() {
    global $wpdb;
    $meta_key = CONTRIB_META_KEY;

    $users = $wpdb->get_results($wpdb->prepare(
        "SELECT u.ID, u.user_login, u.user_email, u.display_name, um.meta_value as contribution
         FROM $wpdb->users u
         LEFT JOIN $wpdb->usermeta um ON u.ID = um.user_id AND um.meta_key = %s
         ORDER BY CAST(COALESCE(um.meta_value, '0') AS DECIMAL(20,4)) DESC
         LIMIT 100",
        $meta_key
    ));
    ?>
    <div class="wrap contrib-wrap">
        <h1>选择用户查看详情</h1>
        <p>请从下方列表中选择用户查看贡献详情，或在排行榜中点击用户名。</p>
        <?php if (empty($users)): ?>
            <div class="contrib-empty">
                <div class="dashicons dashicons-groups"></div>
                <p>暂无用户数据。</p>
            </div>
        <?php else: ?>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th>用户</th>
                    <th>邮箱</th>
                    <th>贡献值</th>
                    <th>操作</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $u): ?>
                <tr>
                    <td>
                        <div class="contrib-user-cell">
                            <?php echo get_avatar($u->ID, 32, '', '', array('class' => 'avatar')); ?>
                            <strong><?php echo esc_html($u->display_name ?: $u->user_login); ?></strong>
                            <span style="color:#94a3b8;font-size:12px;">@<?php echo esc_html($u->user_login); ?></span>
                        </div>
                    </td>
                    <td><?php echo esc_html($u->user_email); ?></td>
                    <td><strong><?php echo number_format((float)($u->contribution ?: 0), 2); ?></strong></td>
                    <td>
                        <a href="<?php echo admin_url('admin.php?page=boxmoe-contrib-user&user_id=' . $u->ID); ?>"
                           class="button button-small">查看详情 →</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
        <p style="margin-top:16px;">
            <a href="<?php echo admin_url('admin.php?page=boxmoe-contribution'); ?>" class="button">← 返回排行榜</a>
        </p>
    </div>
    <?php
}

// ========== 8. 删除贡献日志 ==========

add_action('wp_ajax_boxmoe_delete_contrib_log', 'boxmoe_ajax_delete_contrib_log');
function boxmoe_ajax_delete_contrib_log() {
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => '无权限']);
    }
    $user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;
    $idx     = isset($_POST['idx']) ? intval($_POST['idx']) : -1;
    if (!$user_id || $idx < 0) {
        wp_send_json_error(['message' => '参数错误']);
    }
    $log = get_user_meta($user_id, 'boxmoe_contribution_log', true);
    if (!is_array($log) || !isset($log[$idx])) {
        wp_send_json_error(['message' => '记录不存在']);
    }
    $entry = $log[$idx];
    // 扣回贡献值
    $current = boxmoe_get_contribution($user_id);
    update_user_meta($user_id, 'boxmoe_contribution', max(0, $current - $entry['amount']));
    // 删除该条记录（重建索引）
    array_splice($log, $idx, 1);
    $log = array_values($log);
    update_user_meta($user_id, 'boxmoe_contribution_log', $log);
    wp_send_json_success(['message' => '已删除']);
}

add_action('wp_ajax_boxmoe_clear_contrib_log', 'boxmoe_ajax_clear_contrib_log');
function boxmoe_ajax_clear_contrib_log() {
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => '无权限']);
    }
    $user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;
    if (!$user_id) {
        wp_send_json_error(['message' => '参数错误']);
    }
    delete_user_meta($user_id, 'boxmoe_contribution_log');
    update_user_meta($user_id, 'boxmoe_contribution', 0);
    wp_send_json_success(['message' => '已清空']);
}

// ========== 9. 等级与Tag系统 ==========

/**
 * 等级阈值表
 */
function boxmoe_get_level_thresholds() {
    return [
        0  => 0,
        1  => 1,
        2  => 2,
        3  => 6,
        4  => 21,
        5  => 101,
        6  => 301,
    ];
}

/**
 * 根据贡献值计算等级
 *
 * lv0–lv6 为硬编码阈值，lv7 起区间大小每级 +100：
 *   lv6 区间 300（301–600），lv7 区间 400，lv8 区间 500 …
 */
function boxmoe_get_level($contribution) {
    $c = (float) $contribution;
    $t = boxmoe_get_level_thresholds();

    foreach ($t as $lv => $min) {
        if ($c < $min) return max(0, $lv - 1);
    }

    // lv7+ 递推
    $level = 6;
    $threshold = $t[6];   // 301
    $range = 300;          // lv6 区间大小

    while ($c >= $threshold + $range) {
        $level++;
        $threshold += $range;
        $range += 100;
    }

    return $level;
}

/**
 * 等级下限（精确值）
 */
function boxmoe_get_level_min($level) {
    $t = boxmoe_get_level_thresholds();
    if (isset($t[$level])) return $t[$level];
    if ($level <= 6) return $t[6];

    // lv7+: 递推累加区间
    $threshold = $t[6];  // 301
    $range = 300;         // lv6 区间
    for ($lv = 7; $lv <= $level; $lv++) {
        $threshold += $range;
        $range += 100;
    }
    return $threshold;
}

/**
 * 默认Tag名称
 */
function boxmoe_get_default_tag($level) {
    if ($level <= 0)   return 'nothing';
    if ($level <= 2)   return 'wooden';
    if ($level <= 4)   return 'stone';
    if ($level <= 7)   return 'iron';
    if ($level <= 10)  return 'gold';
    if ($level <= 15)  return 'diamond';
    if ($level <= 25)  return 'netherite';
    return 'creative';
}

/**
 * 获取用户Tag（自定义优先）
 */
function boxmoe_get_user_tag($user_id) {
    $custom = get_user_meta($user_id, 'boxmoe_contrib_tag', true);
    if (!empty($custom)) return $custom;
    $contrib = boxmoe_get_contribution($user_id);
    return boxmoe_get_default_tag(boxmoe_get_level($contrib));
}

/**
 * 前端展示: lv{等级} {Tag}
 */
function boxmoe_get_user_tag_display($user_id) {
    $contrib = boxmoe_get_contribution($user_id);
    $level   = boxmoe_get_level($contrib);
    if ($level >= 301) return 'lv.inf';
    $tag     = boxmoe_get_user_tag($user_id);
    return 'lv' . $level . ' ' . ucfirst($tag);
}

/**
 * 前端彩标HTML: 带颜色的 <span> 标签
 * 等级部分保持等级颜色，自定义Tag名称用渐变色
 */
function boxmoe_get_user_tag_badge($user_id) {
    // 封禁用户不显示 tag
    if (function_exists('boxmoe_is_user_banned') && boxmoe_is_user_banned($user_id) && !current_user_can('manage_options')) {
        return '';
    }
    $contrib  = boxmoe_get_contribution($user_id);
    $level    = boxmoe_get_level($contrib);
    $custom   = get_user_meta($user_id, 'boxmoe_contrib_tag', true);
    $tl_class = ($level >= 26) ? 'tl-h' : ('tl-' . min($level, 25));

    // lv301+ 显示 lv.inf
    if ($level >= 301) {
        $tag_name_class = $custom ? 'tag-name custom' : 'tag-name';
        $tag = $custom ? ucfirst($custom) : 'creative';
        return '<span class="contrib-tag-badge ' . $tl_class . '">'
             . '<span class="tag-level">lv.inf</span><span class="' . $tag_name_class . '">' . esc_html($tag) . '</span>'
             . '</span>';
    }

    $tag      = boxmoe_get_user_tag($user_id);
    $tag_name_class = $custom ? 'tag-name custom' : 'tag-name';

    return '<span class="contrib-tag-badge ' . $tl_class . '">'
         . '<span class="tag-level">lv' . $level . '</span><span class="' . $tag_name_class . '">' . esc_html(ucfirst($tag)) . '</span>'
         . '</span>';
}

/**
 * 前端Tag彩色CSS
 */
add_action('wp_head', 'boxmoe_tag_frontend_css', 99);
function boxmoe_tag_frontend_css() {
    ?>
<style>
.contrib-tag-badge { display: inline-flex; align-items: center; border-radius: 4px; font-size: 0.75rem; font-weight: 700; vertical-align: middle; margin-left: 4px; overflow: hidden; line-height: 1.4; }
.tag-level { padding: 1px 4px 1px 6px; color: inherit; }
.tag-name { padding: 1px 6px 1px 2px; color: inherit; }
.tl-0,.tl-1,.tl-2 { background: #e2e8f0; color: #64748b; }
.tl-3,.tl-4 { background: #d1d5db; color: #4b5563; }
.tl-5,.tl-6,.tl-7 { background: #dbeafe; color: #2563eb; }
.tl-8,.tl-9,.tl-10 { background: #fef3c7; color: #b45309; }
.tl-11,.tl-12,.tl-13,.tl-14,.tl-15 { background: #e0e7ff; color: #4f46e5; }
.tl-16,.tl-17,.tl-18,.tl-19,.tl-20,.tl-21,.tl-22,.tl-23,.tl-24,.tl-25 { background: #4c1d95; color: #e9d5ff; }
.tl-h { background: #fbbf24; color: #92400e; }
/* 自定义Tag — 各等级不同渐变（等级越高颜色越多） */
.tag-name.custom { font-weight: 800; -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text; }
.tl-0 .tag-name.custom,.tl-1 .tag-name.custom,.tl-2 .tag-name.custom { background-image: linear-gradient(135deg, #475569, #1e293b); }
.tl-3 .tag-name.custom,.tl-4 .tag-name.custom { background-image: linear-gradient(135deg, #78716c, #44403c); }
.tl-5 .tag-name.custom,.tl-6 .tag-name.custom,.tl-7 .tag-name.custom { background-image: linear-gradient(135deg, #2563eb, #06b6d4, #10b981); }
.tl-8 .tag-name.custom,.tl-9 .tag-name.custom,.tl-10 .tag-name.custom { background-image: linear-gradient(135deg, #991b1b, #9d174d, #6b21a8); }
.tl-11 .tag-name.custom,.tl-12 .tag-name.custom,.tl-13 .tag-name.custom,.tl-14 .tag-name.custom,.tl-15 .tag-name.custom { background-image: linear-gradient(135deg, #4f46e5, #7c3aed, #db2777, #f43f5e); }
.tl-16 .tag-name.custom,.tl-17 .tag-name.custom,.tl-18 .tag-name.custom,.tl-19 .tag-name.custom,.tl-20 .tag-name.custom,.tl-21 .tag-name.custom,.tl-22 .tag-name.custom,.tl-23 .tag-name.custom,.tl-24 .tag-name.custom,.tl-25 .tag-name.custom { background-image: linear-gradient(135deg, #e0e7ff, #c4b5fd, #a5b4fc, #818cf8); }
.tl-h .tag-name.custom { background-image: linear-gradient(135deg, #dc2626, #c026d3, #7c3aed, #2563eb); }
</style>
    <?php
}

/**
 * 距离下一级还需多少贡献值
 */
function boxmoe_contrib_to_next_level($user_id) {
    $c = boxmoe_get_contribution($user_id);
    $lv = boxmoe_get_level($c);
    return max(0, boxmoe_get_level_min($lv + 1) - $c);
}

// ========== 9. Tag管理后台 ==========

add_action('admin_menu', 'boxmoe_tag_admin_menu');
function boxmoe_tag_admin_menu() {
    add_submenu_page(
        'boxmoe-contribution',
        'Tag管理',
        'Tag管理',
        'manage_options',
        'boxmoe-contrib-tags',
        'boxmoe_tag_admin_page'
    );
}

add_action('admin_head', 'boxmoe_tag_admin_css');
function boxmoe_tag_admin_css() {
    $screen = get_current_screen();
    if (!$screen || strpos($screen->id, 'boxmoe-contrib-tags') === false) return;
    ?>
<style>
.tag-edit-input { width: 160px; }
.tag-readonly { color: #475569; }
.tag-admin-notice { margin: 8px 0 10px; padding: 9px 13px; border-left: 3px solid #8b3dff; background: #faf7ff; color: #475569; font-size: 13px; }
.tag-admin-hint { margin: 0 0 10px; color: #64748b; font-size: 13px; }
.tag-current { font-weight: 600; }
.tag-level-badge { display: inline-block; padding: 2px 8px; border-radius: 4px; font-size: 12px; font-weight: 700; background: #f1f5f9; color: #475569; }
.contrib-tag-badge { display: inline-flex; align-items: center; border-radius: 4px; font-size: 12px; font-weight: 700; overflow: hidden; line-height: 1.4; }
.tag-level { padding: 2px 4px 2px 6px; color: inherit; }
.tag-name { padding: 2px 6px 2px 2px; color: inherit; }
.tl-0,.tl-1,.tl-2 { background: #e2e8f0; color: #64748b; }
.tl-3,.tl-4 { background: #d1d5db; color: #4b5563; }
.tl-5,.tl-6,.tl-7 { background: #dbeafe; color: #2563eb; }
.tl-8,.tl-9,.tl-10 { background: #fef3c7; color: #b45309; }
.tl-11,.tl-12,.tl-13,.tl-14,.tl-15 { background: #e0e7ff; color: #4f46e5; }
.tl-16,.tl-17,.tl-18,.tl-19,.tl-20,.tl-21,.tl-22,.tl-23,.tl-24,.tl-25 { background: #4c1d95; color: #e9d5ff; }
.tl-h { background: #fbbf24; color: #92400e; }
/* 自定义Tag — 各等级不同渐变（等级越高颜色越多） */
.tag-name.custom { font-weight: 800; -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text; }
.tl-0 .tag-name.custom,.tl-1 .tag-name.custom,.tl-2 .tag-name.custom { background-image: linear-gradient(135deg, #475569, #1e293b); }
.tl-3 .tag-name.custom,.tl-4 .tag-name.custom { background-image: linear-gradient(135deg, #78716c, #44403c); }
.tl-5 .tag-name.custom,.tl-6 .tag-name.custom,.tl-7 .tag-name.custom { background-image: linear-gradient(135deg, #2563eb, #06b6d4, #10b981); }
.tl-8 .tag-name.custom,.tl-9 .tag-name.custom,.tl-10 .tag-name.custom { background-image: linear-gradient(135deg, #991b1b, #9d174d, #6b21a8); }
.tl-11 .tag-name.custom,.tl-12 .tag-name.custom,.tl-13 .tag-name.custom,.tl-14 .tag-name.custom,.tl-15 .tag-name.custom { background-image: linear-gradient(135deg, #4f46e5, #7c3aed, #db2777, #f43f5e); }
.tl-16 .tag-name.custom,.tl-17 .tag-name.custom,.tl-18 .tag-name.custom,.tl-19 .tag-name.custom,.tl-20 .tag-name.custom,.tl-21 .tag-name.custom,.tl-22 .tag-name.custom,.tl-23 .tag-name.custom,.tl-24 .tag-name.custom,.tl-25 .tag-name.custom { background-image: linear-gradient(135deg, #e0e7ff, #c4b5fd, #a5b4fc, #818cf8); }
.tl-h .tag-name.custom { background-image: linear-gradient(135deg, #dc2626, #c026d3, #7c3aed, #2563eb); }
</style>
    <?php
}

function boxmoe_tag_admin_page() {
    global $wpdb;

    $search = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';
    $paged = max(1, isset($_GET['tp']) ? (int)$_GET['tp'] : 1);
    $per_page = 30;
    $offset = ($paged - 1) * $per_page;
    $meta_key = CONTRIB_META_KEY;

    // Where for search
    $where = '';
    if ($search) {
        $where = $wpdb->prepare(
            " WHERE u.user_login LIKE %s OR u.display_name LIKE %s",
            '%' . $wpdb->esc_like($search) . '%',
            '%' . $wpdb->esc_like($search) . '%'
        );
    }

    $total_users = $wpdb->get_var("SELECT COUNT(ID) FROM $wpdb->users u" . $where);
    $total_pages = ceil($total_users / $per_page);

    $users = $wpdb->get_results($wpdb->prepare(
        "SELECT u.ID, u.user_login, COALESCE(um.meta_value, '0') as contribution
         FROM $wpdb->users u
         LEFT JOIN $wpdb->usermeta um ON u.ID = um.user_id AND um.meta_key = %s"
         . $where .
         " ORDER BY CAST(COALESCE(um.meta_value, '0') AS DECIMAL(20,4)) DESC
         LIMIT %d OFFSET %d",
        $meta_key, $per_page, $offset
    ));

    ?>
    <div class="wrap contrib-wrap">
        <div class="contrib-header">
            <h1>Tag管理</h1>
        </div>
        <div class="tag-admin-notice">Tag 已迁移到 SSO：WordPress 后台只读，实际修改请到 SSO 账号中心或活动站后台。</div>
        <p class="tag-admin-hint">
            自定义Tag优先 &nbsp;|&nbsp; 格式 <code>lv{等级} {Tag}</code> &nbsp;|&nbsp;
            <a href="#tag-reference">跳转等级参考表 ↓</a>
        </p>
        <p>以下为只读视图，自定义Tag由 SSO 同步，留空则使用默认Tag（按贡献值等级自动分配）。</p>

        <form method="get" style="margin-bottom:12px; display:flex; gap:8px;">
            <input type="hidden" name="page" value="boxmoe-contrib-tags">
            <input type="text" name="s" value="<?php echo esc_attr($search); ?>" placeholder="搜索用户名..." style="width:200px;">
            <button type="submit" class="button">搜索</button>
            <?php if ($search): ?>
                <a href="<?php echo admin_url('admin.php?page=boxmoe-contrib-tags'); ?>" class="button">清除</a>
            <?php endif; ?>
        </form>

        <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th style="width:45px;">#</th>
                        <th>用户</th>
                        <th style="width:85px;">贡献值</th>
                        <th style="width:55px;">等级</th>
                        <th style="width:100px;">默认Tag</th>
                        <th style="width:200px;">自定义Tag（只读）</th>
                        <th>前端展示</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $i => $u):
                        $c = (float) $u->contribution;
                        $lv = boxmoe_get_level($c);
                        $def = boxmoe_get_default_tag($lv);
                        $cust = get_user_meta($u->ID, 'boxmoe_contrib_tag', true);
                        $disp = $cust ?: $def;
                        $tl_class = ($lv >= 26) ? 'tl-h' : ('tl-' . min($lv, 25));
                    ?>
                    <tr>
                        <td><?php echo $offset + $i + 1; ?></td>
                        <td>
                            <div class="contrib-user-cell">
                                <?php echo get_avatar($u->ID, 24, '', '', array('class' => 'avatar')); ?>
                                <?php echo esc_html($u->user_login); ?>
                            </div>
                        </td>
                        <td><strong><?php echo number_format($c, 2); ?></strong></td>
                        <td><span class="tag-level-badge <?php echo $tl_class; ?>">lv<?php echo $lv; ?></span></td>
                        <td style="color:#94a3b8;"><?php echo esc_html(ucfirst($def)); ?></td>
                        <td class="tag-readonly">
                            <?php if ($cust !== '' && $cust !== null): ?>
                                <?php echo esc_html($cust); ?>
                            <?php else: ?>
                                <span style="color:#94a3b8;">—</span>
                            <?php endif; ?>
                        </td>
                        <td class="tag-current">
                            <?php echo boxmoe_get_user_tag_badge($u->ID); ?>
                            <?php if ($cust): ?>
                                <span style="color:#8b3dff;font-size:11px;margin-left:4px;">(自定义)</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <?php if ($total_pages > 1): ?>
            <div class="tablenav" style="margin-top:12px;">
                <div class="tablenav-pages">
                    <span class="displaying-num">共 <?php echo (int)$total_users; ?> 人</span>
                    <span class="pagination-links">
                        <?php
                        $base = admin_url('admin.php?page=boxmoe-contrib-tags&tp=%#%');
                        if ($search) $base .= '&s=' . urlencode($search);
                        echo paginate_links([
                            'base' => $base, 'format' => '', 'current' => $paged,
                            'total' => $total_pages, 'prev_text' => '&laquo;', 'next_text' => '&raquo;',
                        ]);
                        ?>
                    </span>
                </div>
            </div>
            <?php endif; ?>


        <!-- 等级参考表（动态生成） -->
        <div class="postbox" id="tag-reference" style="margin-top:24px; border-radius:10px; overflow:hidden;">
            <div class="postbox-header"><h2 style="padding:8px 12px;margin:0;font-size:15px;">等级-Tag 参考表</h2></div>
            <div class="inside" style="margin:0; padding:0;">
                <table class="wp-list-table widefat fixed" style="border:none;">
                    <thead><tr><th>等级</th><th>贡献值区间</th><th>区间大小</th><th>默认Tag</th></tr></thead>
                    <tbody>
                        <?php
                        // 动态生成参考行（lv0–lv30 全展示，之后跳级到关键节点）
                        for ($lv = 0; $lv <= 305; $lv++):
                            // lv31–299 跳级展示
                            if ($lv > 30 && $lv < 300 && $lv % 30 != 0) continue;
                            $min = boxmoe_get_level_min($lv);
                            $next_min = boxmoe_get_level_min($lv + 1);
                            $max = $next_min - 1;
                            $range = $next_min - $min;
                            $def = boxmoe_get_default_tag($lv);
                            $tl_class = ($lv >= 26) ? 'tl-h' : ('tl-' . min($lv, 25));
                        ?>
                        <tr>
                            <td><span class="tag-level-badge <?php echo $tl_class; ?>">lv<?php echo $lv; ?></span></td>
                            <td><?php echo $min; ?> – <?php echo $max; ?></td>
                            <td style="color:#94a3b8;"><?php echo $range; ?></td>
                            <td><?php echo ucfirst($def); ?></td>
                        </tr>
                        <?php endfor; ?>
                        <tr><td colspan="4" style="text-align:center;color:#94a3b8;padding:10px;">
                            lv5 起每级区间 +100，lv26+ 为 creative，lv301+ 显示 lv.inf
                        </td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php
}
