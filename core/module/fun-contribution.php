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

// ========== 账本系统初始化 ==========

/**
 * 事件驱动贡献值 + 持久化账本 的版本号。
 * 表结构或迁移逻辑变更时递增；版本不一致时会在 init 执行一次性迁移。
 */
define('CONTRIB_LEDGER_VERSION', '1.0.0');

/**
 * 延迟迁移入口：确保账本表存在，并把历史内容标记为「已入账」。
 * 迁移本身幂等，可重复执行；版本一致时直接跳过。
 */
add_action('init', 'boxmoe_contrib_maybe_migrate', 20);
function boxmoe_contrib_maybe_migrate() {
    if (get_option('boxmoe_contrib_ledger_db_version') === CONTRIB_LEDGER_VERSION) {
        return;
    }
    boxmoe_contrib_migrate();
}

/**
 * 旧的每日 02:00 全量重算任务已废弃（改为事件驱动增量入账）。
 * 这里清理历史遗留的定时任务，避免线上继续触发。
 */
add_action('init', 'boxmoe_contrib_unschedule_legacy_cron', 21);
function boxmoe_contrib_unschedule_legacy_cron() {
    if (wp_next_scheduled('boxmoe_contrib_daily_recalc')) {
        wp_clear_scheduled_hook('boxmoe_contrib_daily_recalc');
    }
}

// ========== 常量定义 ==========
// 文章贡献基数
define('CONTRIB_ARTICLE_BASE', 20);       // 发布文章基础贡献
define('CONTRIB_ARTICLE_VIEW', 0.05);     // 每浏览贡献
define('CONTRIB_ARTICLE_LIKE', 2);        // 每被赞贡献（文章作者获得）
define('CONTRIB_ARTICLE_COMMENTED', 3);   // 每被评论贡献（文章作者获得）
define('CONTRIB_COMMENT_MAKE', 2);        // 每发表评论贡献（评论者获得）

// 说说折半系数
define('CONTRIB_SHUOSHUO_RATIO', 0.5);

// 用户 meta key（余额仍写这里，保持全站读取兼容）
define('CONTRIB_META_KEY', 'boxmoe_contribution');
define('CONTRIB_LOG_KEY', 'boxmoe_contribution_log'); // 旧数组日志 key，迁移后删除
define('CONTRIB_LOG_MAX', 200);           // 已废弃，仅保留常量兼容

// 事件幂等标记 / 增量基线（写 postmeta / commentmeta）
define('CONTRIB_MARK_PUBLISH', '_boxmoe_contrib_publish_awarded');   // 文章基础分已发
define('CONTRIB_MARK_VIEW_BASE', '_boxmoe_contrib_view_baseline');   // 已计入的浏览量基线
define('CONTRIB_MARK_LIKE_BASE', '_boxmoe_contrib_like_baseline');   // 已计入的点赞数基线
define('CONTRIB_MARK_COMMENT', '_boxmoe_contrib_awarded');           // 评论当前已入账
define('CONTRIB_MARK_COMMENT_MAKE', '_boxmoe_contrib_make_amount');  // 评论者已入账分值
define('CONTRIB_MARK_COMMENT_RECV', '_boxmoe_contrib_recv_amount');  // 作者已入账分值
define('CONTRIB_MARK_COMMENT_MAKE_USER', '_boxmoe_contrib_make_user');
define('CONTRIB_MARK_COMMENT_RECV_USER', '_boxmoe_contrib_recv_user');
define('CONTRIB_MARK_COMMENT_APPROVE_SEQ', '_boxmoe_contrib_approve_seq');
define('CONTRIB_MARK_COMMENT_REVOKE_SEQ', '_boxmoe_contrib_revoke_seq');

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
 * 获取用户贡献总值（余额仍存 wp_usermeta，全站读取保持兼容）
 */
function boxmoe_get_contribution($user_id = null) {
    if (!$user_id) {
        $user_id = get_current_user_id();
    }
    if (!$user_id) return 0;
    return (float) get_user_meta($user_id, CONTRIB_META_KEY, true);
}

// ========== 账本核心 ==========

/**
 * 账本表名
 */
function boxmoe_contrib_ledger_table() {
    global $wpdb;
    return $wpdb->prefix . 'boxmoe_contrib_ledger';
}

/**
 * 创建 / 升级账本表（dbDelta，可重复执行）
 */
function boxmoe_contrib_ledger_install() {
    global $wpdb;
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';

    $table   = boxmoe_contrib_ledger_table();
    $charset = $wpdb->get_charset_collate();
    $sql = "CREATE TABLE $table (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        user_id bigint(20) unsigned NOT NULL DEFAULT 0,
        delta decimal(20,4) NOT NULL DEFAULT 0.0000,
        reason varchar(190) NOT NULL DEFAULT '',
        source varchar(32) NOT NULL DEFAULT '',
        source_ref varchar(100) NOT NULL DEFAULT '',
        post_id bigint(20) unsigned NOT NULL DEFAULT 0,
        created_at datetime NOT NULL,
        PRIMARY KEY  (id),
        KEY user_id (user_id,id),
        UNIQUE KEY uniq_event (user_id,source,source_ref,reason)
    ) $charset;";
    dbDelta($sql);
    return $table;
}

/**
 * 账本表是否已存在
 */
function boxmoe_contrib_ledger_table_exists() {
    global $wpdb;
    $table = boxmoe_contrib_ledger_table();
    return $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($table))) === $table;
}

/**
 * 原子增减余额（避免 read-modify-write 丢更新），并清理 user meta 缓存。
 */
function boxmoe_contrib_balance_add($user_id, $delta) {
    global $wpdb;

    $user_id = (int) $user_id;
    $delta   = round((float) $delta, 4);
    if ($user_id <= 0 || $delta == 0.0) return false;

    $table = $wpdb->usermeta;
    $wpdb->query('START TRANSACTION');
    $existing = $wpdb->get_var($wpdb->prepare(
        "SELECT meta_value FROM $table WHERE user_id = %d AND meta_key = %s LIMIT 1 FOR UPDATE",
        $user_id, CONTRIB_META_KEY
    ));
    if ($existing === null) {
        $wpdb->insert($table, [
            'user_id'    => $user_id,
            'meta_key'   => CONTRIB_META_KEY,
            'meta_value' => (string) $delta,
        ], ['%d', '%s', '%s']);
    } else {
        $wpdb->query($wpdb->prepare(
            "UPDATE $table SET meta_value = CAST(meta_value AS DECIMAL(24,4)) + %s
             WHERE user_id = %d AND meta_key = %s",
            (string) $delta, $user_id, CONTRIB_META_KEY
        ));
    }
    $wpdb->query('COMMIT');

    clean_user_cache($user_id);
    wp_cache_delete($user_id, 'user_meta');
    return true;
}

/**
 * 直接设置余额（重建 / 修复工具专用），并清理缓存。
 */
function boxmoe_contrib_balance_set($user_id, $value) {
    global $wpdb;

    $user_id = (int) $user_id;
    $value   = round((float) $value, 4);
    if ($user_id <= 0) return false;

    $existing = $wpdb->get_var($wpdb->prepare(
        "SELECT meta_value FROM {$wpdb->usermeta} WHERE user_id = %d AND meta_key = %s LIMIT 1",
        $user_id, CONTRIB_META_KEY
    ));
    if ($existing === null) {
        $wpdb->insert($wpdb->usermeta, [
            'user_id'    => $user_id,
            'meta_key'   => CONTRIB_META_KEY,
            'meta_value' => (string) $value,
        ], ['%d', '%s', '%s']);
    } else {
        $wpdb->update(
            $wpdb->usermeta,
            ['meta_value' => (string) $value],
            ['user_id' => $user_id, 'meta_key' => CONTRIB_META_KEY],
            ['%s'],
            ['%d', '%s']
        );
    }
    clean_user_cache($user_id);
    wp_cache_delete($user_id, 'user_meta');
    return true;
}

/**
 * 写入一条账本记录并同步余额。
 *
 * 幂等规则：唯一键 (user_id, source, source_ref, reason)
 *  - $accumulate = false：重复事件被忽略（不重复入账）
 *  - $accumulate = true ：同一唯一键累加（浏览按「用户+文章+天」聚合）
 */
function boxmoe_contrib_ledger_insert($user_id, $delta, $reason, $source, $source_ref, $post_id = 0, $accumulate = false) {
    global $wpdb;

    $user_id = (int) $user_id;
    $delta   = round((float) $delta, 4);
    if ($user_id <= 0 || $delta == 0.0) return false;

    $table      = boxmoe_contrib_ledger_table();
    $reason     = mb_substr((string) $reason, 0, 185);
    $source     = mb_substr((string) $source, 0, 32);
    $source_ref = mb_substr((string) $source_ref, 0, 100);
    $post_id    = (int) $post_id;
    $now        = current_time('mysql');

    if ($accumulate) {
        $affected = $wpdb->query($wpdb->prepare(
            "INSERT INTO $table (user_id, delta, reason, source, source_ref, post_id, created_at)
             VALUES (%d, %s, %s, %s, %s, %d, %s) AS new_ledger
             ON DUPLICATE KEY UPDATE $table.delta = $table.delta + new_ledger.delta",
            $user_id, (string) $delta, $reason, $source, $source_ref, $post_id, $now
        ));
    } else {
        $affected = $wpdb->query($wpdb->prepare(
            "INSERT IGNORE INTO $table (user_id, delta, reason, source, source_ref, post_id, created_at)
             VALUES (%d, %s, %s, %s, %s, %d, %s)",
            $user_id, (string) $delta, $reason, $source, $source_ref, $post_id, $now
        ));
    }

    if (!$affected) return false; // 重复事件
    boxmoe_contrib_balance_add($user_id, $delta);
    return true;
}

/**
 * 增加用户贡献值（保留原函数签名；现在写入账本 + 原子更新余额）
 * 主要用于「管理员调整」这类非事件型变动。
 */
function boxmoe_add_contribution($user_id, $amount, $reason = '', $post_id = 0) {
    $user_id = (int) $user_id;
    $amount  = round((float) $amount, 4);
    if ($user_id <= 0 || $amount == 0.0) return false;

    $reason = $reason !== '' ? (string) $reason : '管理员手动调整';
    $ref    = 'admin:' . current_time('YmdHis') . ':' . wp_generate_password(8, false);
    boxmoe_contrib_ledger_insert($user_id, $amount, $reason, 'admin', $ref, $post_id, false);

    return boxmoe_get_contribution($user_id);
}

/**
 * @deprecated 旧接口，仅保留兼容：只写账本记录，不改余额。
 */
function boxmoe_log_contribution($user_id, $amount, $reason = '', $post_id = 0) {
    global $wpdb;

    $user_id = (int) $user_id;
    $amount  = round((float) $amount, 4);
    if ($user_id <= 0 || $amount == 0.0) return false;
    if (!boxmoe_contrib_ledger_table_exists()) return false;

    $table = boxmoe_contrib_ledger_table();
    $ref   = 'legacy:' . uniqid('', true);
    return (bool) $wpdb->query($wpdb->prepare(
        "INSERT IGNORE INTO $table (user_id, delta, reason, source, source_ref, post_id, created_at)
         VALUES (%d, %s, %s, 'legacy', %s, %d, %s)",
        $user_id, (string) $amount, mb_substr((string) $reason, 0, 185), $ref, (int) $post_id, current_time('mysql')
    ));
}

/**
 * 获取贡献值变动历史（读账本，保持旧返回结构：time/amount/reason/post_id）
 */
function boxmoe_get_contribution_log($user_id = null, $limit = 20) {
    if (!$user_id) {
        $user_id = get_current_user_id();
    }
    $user_id = (int) $user_id;
    if ($user_id <= 0) return array();
    if (!boxmoe_contrib_ledger_table_exists()) return array();

    global $wpdb;
    $table = boxmoe_contrib_ledger_table();
    $rows  = $wpdb->get_results($wpdb->prepare(
        "SELECT delta, reason, post_id, created_at FROM $table
         WHERE user_id = %d ORDER BY id DESC LIMIT %d",
        $user_id, max(1, (int) $limit)
    ));

    $log = array();
    foreach ((array) $rows as $row) {
        $log[] = array(
            'time'    => $row->created_at,
            'amount'  => (float) $row->delta,
            'reason'  => $row->reason,
            'post_id' => (int) $row->post_id,
        );
    }
    return $log;
}

/**
 * 按 reason 聚合账本（用于「贡献构成」展示）
 */
function boxmoe_contrib_ledger_breakdown($user_id, $limit = 20) {
    global $wpdb;

    $user_id = (int) $user_id;
    if ($user_id <= 0 || !boxmoe_contrib_ledger_table_exists()) return array();

    $table = boxmoe_contrib_ledger_table();
    $rows  = $wpdb->get_results($wpdb->prepare(
        "SELECT reason, SUM(delta) AS total FROM $table
         WHERE user_id = %d GROUP BY reason ORDER BY total DESC LIMIT %d",
        $user_id, max(1, (int) $limit)
    ));

    $out = array();
    foreach ((array) $rows as $row) {
        $out[$row->reason] = (float) $row->total;
    }
    return $out;
}

/**
 * 账本合计（可按来源过滤），用于后台核对与重建
 */
function boxmoe_contrib_ledger_total($user_id = null, $source = null) {
    global $wpdb;

    if (!boxmoe_contrib_ledger_table_exists()) return 0.0;
    $table = boxmoe_contrib_ledger_table();

    if ($user_id) {
        if ($source !== null) {
            return (float) $wpdb->get_var($wpdb->prepare(
                "SELECT COALESCE(SUM(delta),0) FROM $table WHERE user_id = %d AND source = %s",
                (int) $user_id, (string) $source
            ));
        }
        return (float) $wpdb->get_var($wpdb->prepare(
            "SELECT COALESCE(SUM(delta),0) FROM $table WHERE user_id = %d", (int) $user_id
        ));
    }

    if ($source !== null) {
        return (float) $wpdb->get_var($wpdb->prepare(
            "SELECT COALESCE(SUM(delta),0) FROM $table WHERE source = %s", (string) $source
        ));
    }
    return (float) $wpdb->get_var("SELECT COALESCE(SUM(delta),0) FROM $table");
}

/**
 * 当前所有用户余额快照（迁移/重建前后校验用）
 */
function boxmoe_contrib_balance_snapshot() {
    global $wpdb;

    $rows = $wpdb->get_results($wpdb->prepare(
        "SELECT user_id, meta_value FROM {$wpdb->usermeta} WHERE meta_key = %s", CONTRIB_META_KEY
    ));
    $users = array();
    $sum   = 0.0;
    foreach ((array) $rows as $row) {
        $uid = (int) $row->user_id;
        $users[$uid] = round((float) $row->meta_value, 4);
        $sum += (float) $row->meta_value;
    }
    return array('sum' => round($sum, 4), 'users' => $users);
}

// ========== 1. 一次性迁移（幂等） ==========

/**
 * 迁移：建账本表 + 把历史内容标记为「已入账」+ 清理旧数组日志与旧 cron。
 *
 * 关键约束：
 *  - 不改任何用户余额（迁移前后逐用户校验）；
 *  - 重复执行直接 skip（版本号已写入）；中途失败下次请求可继续；
 *  - 旧 usermeta 日志中的「管理员调整」导入账本（仅写账本，不改余额）。
 */
function boxmoe_contrib_migrate() {
    global $wpdb;

    if (get_option('boxmoe_contrib_ledger_db_version') === CONTRIB_LEDGER_VERSION) {
        return ['skipped' => true];
    }

    if (function_exists('set_time_limit')) {
        @set_time_limit(300);
    }

    $before = boxmoe_contrib_balance_snapshot();
    boxmoe_contrib_ledger_install();
    if (!boxmoe_contrib_ledger_table_exists()) {
        return ['error' => 'ledger_table_missing'];
    }
    $table = boxmoe_contrib_ledger_table();

    $report = [
        'version'              => CONTRIB_LEDGER_VERSION,
        'time'                 => current_time('mysql'),
        'balances_before'      => $before['users'],
        'balances_before_sum'  => $before['sum'],
        'imported_admin'       => 0,
        'marked_posts'         => 0,
        'marked_comments'      => 0,
        'legacy_logs_removed'  => 0,
        'cron_removed'         => false,
    ];

    // 1) 旧日志里的「管理员调整」导入账本（只写账本，余额已包含这部分，不重复加）
    $legacy_logs = $wpdb->get_results($wpdb->prepare(
        "SELECT user_id, meta_value FROM {$wpdb->usermeta} WHERE meta_key = %s", CONTRIB_LOG_KEY
    ));
    foreach ((array) $legacy_logs as $row) {
        $entries = maybe_unserialize($row->meta_value);
        if (!is_array($entries)) continue;
        foreach ($entries as $entry) {
            $reason = isset($entry['reason']) ? (string) $entry['reason'] : '';
            if (strpos($reason, '管理员调整') !== 0) continue;
            $amount = isset($entry['amount']) ? round((float) $entry['amount'], 4) : 0.0;
            if ($amount == 0.0) continue;
            $post_id = isset($entry['post_id']) ? (int) $entry['post_id'] : 0;
            $time    = !empty($entry['time']) ? (string) $entry['time'] : current_time('mysql');
            $ref     = 'legacy-admin:' . md5((int) $row->user_id . '|' . $time . '|' . $amount . '|' . $reason . '|' . $post_id);
            $inserted = $wpdb->query($wpdb->prepare(
                "INSERT IGNORE INTO $table (user_id, delta, reason, source, source_ref, post_id, created_at)
                 VALUES (%d, %s, %s, 'admin', %s, %d, %s)",
                (int) $row->user_id, (string) $amount, mb_substr($reason, 0, 185), $ref, $post_id, $time
            ));
            if ($inserted) $report['imported_admin']++;
        }
    }

    // 2) 已发布文章：打「基础分已发」标记，并记录当前浏览/点赞基线（历史值视为已入账）
    $post_ids = $wpdb->get_col($wpdb->prepare(
        "SELECT ID FROM {$wpdb->posts} WHERE post_type = %s AND post_status = %s", 'post', 'publish'
    ));
    foreach ((array) $post_ids as $pid) {
        $pid = (int) $pid;
        update_post_meta($pid, CONTRIB_MARK_PUBLISH, 1);
        if (get_post_meta($pid, CONTRIB_MARK_VIEW_BASE, true) === '') {
            update_post_meta($pid, CONTRIB_MARK_VIEW_BASE, (int) get_post_meta($pid, 'post_views_count', true));
        }
        if (get_post_meta($pid, CONTRIB_MARK_LIKE_BASE, true) === '') {
            update_post_meta($pid, CONTRIB_MARK_LIKE_BASE, (int) get_post_meta($pid, 'post_likes_count', true));
        }
        $report['marked_posts']++;
    }

    // 3) 已审核评论：记录为「已入账」并保存当时分值（供后续取消审核冲正）
    $comments = $wpdb->get_results(
        "SELECT c.comment_ID, c.comment_post_ID, c.user_id, p.post_author
         FROM {$wpdb->comments} c
         JOIN {$wpdb->posts} p ON p.ID = c.comment_post_ID
         WHERE c.comment_approved = '1' AND p.post_status = 'publish' AND p.post_type = 'post'"
    );
    foreach ((array) $comments as $c) {
        if (boxmoe_contrib_comment_mark_awarded((int) $c->comment_ID)) {
            $report['marked_comments']++;
        }
    }

    // 4) 删除旧 usermeta 数组日志（内容已由标记/基线承接，管理员条目已导入账本）
    $deleted = $wpdb->delete($wpdb->usermeta, ['meta_key' => CONTRIB_LOG_KEY]);
    $report['legacy_logs_removed'] = $deleted ? (int) $deleted : 0;

    // 5) 清理旧的每日重算 cron
    if (wp_next_scheduled('boxmoe_contrib_daily_recalc')) {
        wp_clear_scheduled_hook('boxmoe_contrib_daily_recalc');
        $report['cron_removed'] = true;
    }

    // 6) 迁移前后余额校验（必须逐用户一致）
    $after = boxmoe_contrib_balance_snapshot();
    $report['balances_after']     = $after['users'];
    $report['balances_after_sum'] = $after['sum'];
    $report['balance_unchanged']  = true;
    $uids = array_unique(array_merge(array_keys($before['users']), array_keys($after['users'])));
    foreach ($uids as $uid) {
        $b = sprintf('%.4f', round((float) ($before['users'][$uid] ?? 0), 4));
        $a = sprintf('%.4f', round((float) ($after['users'][$uid] ?? 0), 4));
        if ($b !== $a) {
            $report['balance_unchanged'] = false;
            $report['balance_mismatch'][] = ['user_id' => $uid, 'before' => $b, 'after' => $a];
        }
    }

    // 7) 写版本号：重复执行会直接 skip
    update_option('boxmoe_contrib_ledger_db_version', CONTRIB_LEDGER_VERSION, true);
    update_option('boxmoe_contrib_migration_report', $report, false);

    return $report;
}

// ========== 2. 内容事件：发布 / 浏览 / 点赞 ==========

/**
 * 文章首次发布：+20（说说 ×0.5），每篇只加一次。
 */
function boxmoe_contrib_award_publish($post) {
    if (!$post instanceof WP_Post) return false;
    $post_id = (int) $post->ID;
    $uid     = (int) $post->post_author;
    if ($post_id <= 0 || $uid <= 0) return false;
    if (get_post_meta($post_id, CONTRIB_MARK_PUBLISH, true)) return false;

    $mult   = boxmoe_get_contrib_multiplier($post_id);
    $is_shu = $mult < 1;
    $ok = boxmoe_contrib_ledger_insert(
        $uid,
        CONTRIB_ARTICLE_BASE * $mult,
        $is_shu ? '发布说说' : '发布文章',
        'publish',
        'post:' . $post_id,
        $post_id,
        false
    );

    // 新文章从 0 起算浏览/点赞，避免把历史计数重复入账
    if (get_post_meta($post_id, CONTRIB_MARK_VIEW_BASE, true) === '') {
        update_post_meta($post_id, CONTRIB_MARK_VIEW_BASE, (int) get_post_meta($post_id, 'post_views_count', true));
    }
    if (get_post_meta($post_id, CONTRIB_MARK_LIKE_BASE, true) === '') {
        update_post_meta($post_id, CONTRIB_MARK_LIKE_BASE, (int) get_post_meta($post_id, 'post_likes_count', true));
    }
    update_post_meta($post_id, CONTRIB_MARK_PUBLISH, 1);

    return $ok;
}

add_action('transition_post_status', 'boxmoe_contrib_on_post_status', 10, 3);
function boxmoe_contrib_on_post_status($new_status, $old_status, $post) {
    if (!$post instanceof WP_Post || $post->post_type !== 'post') return;
    if ($new_status !== 'publish' || $old_status === 'publish') return;
    boxmoe_contrib_award_publish($post);
}

/**
 * 文章浏览：按浏览量增量入账，0.05/次（说说 ×0.5）。
 * 账本按「用户 + 文章 + 天」聚合成一行。
 */
function boxmoe_contrib_on_post_viewed($post_id) {
    $post_id = (int) $post_id;
    $post    = get_post($post_id);
    if (!$post || $post->post_type !== 'post' || $post->post_status !== 'publish') return;
    $uid = (int) $post->post_author;
    if ($uid <= 0) return;

    $views = (int) get_post_meta($post_id, 'post_views_count', true);
    $base  = get_post_meta($post_id, CONTRIB_MARK_VIEW_BASE, true);
    if ($base === '') {
        // 无基线（例如刚清理过标记）：以当前值为基线，不回灌历史浏览量
        update_post_meta($post_id, CONTRIB_MARK_VIEW_BASE, $views);
        return;
    }
    $delta_views = $views - (int) $base;
    if ($delta_views <= 0) return;

    $mult   = boxmoe_get_contrib_multiplier($post_id);
    $amount = CONTRIB_ARTICLE_VIEW * $mult * $delta_views;

    boxmoe_contrib_ledger_insert(
        $uid,
        $amount,
        '文章浏览',
        'view',
        'post:' . $post_id . ':view:' . current_time('Y-m-d'),
        $post_id,
        true
    );
    update_post_meta($post_id, CONTRIB_MARK_VIEW_BASE, $views);
}
add_action('boxmoe_post_viewed', 'boxmoe_contrib_on_post_viewed', 10, 1);

/**
 * 文章被点赞：+2/赞（说说 ×0.5）给作者；已有 transient 去重保持不变。
 */
function boxmoe_contrib_on_post_liked($post_id, $count = null) {
    $post_id = (int) $post_id;
    $post    = get_post($post_id);
    if (!$post || $post->post_type !== 'post' || $post->post_status !== 'publish') return;
    $uid = (int) $post->post_author;
    if ($uid <= 0) return;

    $count = ($count === null) ? (int) get_post_meta($post_id, 'post_likes_count', true) : (int) $count;
    $base  = get_post_meta($post_id, CONTRIB_MARK_LIKE_BASE, true);
    if ($base === '') {
        update_post_meta($post_id, CONTRIB_MARK_LIKE_BASE, $count);
        return;
    }
    $delta_likes = $count - (int) $base;
    if ($delta_likes <= 0) return;

    $mult   = boxmoe_get_contrib_multiplier($post_id);
    $amount = CONTRIB_ARTICLE_LIKE * $mult * $delta_likes;

    boxmoe_contrib_ledger_insert(
        $uid,
        $amount,
        '文章被点赞',
        'like',
        'post:' . $post_id . ':like:' . $count,
        $post_id,
        false
    );
    update_post_meta($post_id, CONTRIB_MARK_LIKE_BASE, $count);
}
add_action('boxmoe_post_liked', 'boxmoe_contrib_on_post_liked', 10, 2);

// ========== 3. 内容事件：评论审核通过 / 取消 ==========

/**
 * 已审核评论标记为「已入账」（仅打标记 + 记录分值，不写账本、不改余额）。
 * 迁移时对历史评论使用；返回是否新写入。
 */
function boxmoe_contrib_comment_mark_awarded($comment_id) {
    $comment_id = (int) $comment_id;
    $comment    = get_comment($comment_id);
    if (!$comment || (string) $comment->comment_approved !== '1') return false;
    $post = get_post($comment->comment_post_ID);
    if (!$post || $post->post_status !== 'publish' || $post->post_type !== 'post') return false;
    if (get_comment_meta($comment_id, CONTRIB_MARK_COMMENT, true)) return false;

    $mult     = boxmoe_get_contrib_multiplier((int) $comment->comment_post_ID);
    $commenter = (int) $comment->user_id;
    $author    = (int) $post->post_author;
    $make      = ($commenter > 0) ? CONTRIB_COMMENT_MAKE * $mult : 0.0;
    $recv      = ($author > 0 && $author !== $commenter) ? CONTRIB_ARTICLE_COMMENTED * $mult : 0.0;

    update_comment_meta($comment_id, CONTRIB_MARK_COMMENT, 1);
    update_comment_meta($comment_id, CONTRIB_MARK_COMMENT_MAKE, $make);
    update_comment_meta($comment_id, CONTRIB_MARK_COMMENT_RECV, $recv);
    update_comment_meta($comment_id, CONTRIB_MARK_COMMENT_MAKE_USER, $commenter);
    update_comment_meta($comment_id, CONTRIB_MARK_COMMENT_RECV_USER, ($recv > 0) ? $author : 0);
    return true;
}

/**
 * 评论审核通过：评论者 +2、作者 +3（说说 ×0.5，自评除外）。
 * 幂等：同一评论已入账时不重复加（重新通过时按新序列号入账）。
 */
function boxmoe_contrib_award_comment($comment_id) {
    $comment_id = (int) $comment_id;
    $comment    = get_comment($comment_id);
    if (!$comment || (string) $comment->comment_approved !== '1') return false;
    $post = get_post($comment->comment_post_ID);
    if (!$post || $post->post_status !== 'publish' || $post->post_type !== 'post') return false;
    if (get_comment_meta($comment_id, CONTRIB_MARK_COMMENT, true)) return false;

    $post_id   = (int) $comment->comment_post_ID;
    $commenter = (int) $comment->user_id;
    $author    = (int) $post->post_author;
    $mult      = boxmoe_get_contrib_multiplier($post_id);
    $is_shu    = $mult < 1;
    $seq       = (int) get_comment_meta($comment_id, CONTRIB_MARK_COMMENT_APPROVE_SEQ, true) + 1;
    update_comment_meta($comment_id, CONTRIB_MARK_COMMENT_APPROVE_SEQ, $seq);

    $make = 0.0;
    $recv = 0.0;

    if ($commenter > 0) {
        $make = CONTRIB_COMMENT_MAKE * $mult;
        boxmoe_contrib_ledger_insert(
            $commenter,
            $make,
            $is_shu ? '评论说说' : '发表评论',
            'comment_make',
            'comment:' . $comment_id . ':a' . $seq,
            $post_id,
            false
        );
    }
    if ($author > 0 && $author !== $commenter) {
        $recv = CONTRIB_ARTICLE_COMMENTED * $mult;
        boxmoe_contrib_ledger_insert(
            $author,
            $recv,
            '文章收到评论',
            'comment_recv',
            'comment:' . $comment_id . ':a' . $seq,
            $post_id,
            false
        );
    }

    update_comment_meta($comment_id, CONTRIB_MARK_COMMENT, 1);
    update_comment_meta($comment_id, CONTRIB_MARK_COMMENT_MAKE, $make);
    update_comment_meta($comment_id, CONTRIB_MARK_COMMENT_RECV, $recv);
    update_comment_meta($comment_id, CONTRIB_MARK_COMMENT_MAKE_USER, $commenter);
    update_comment_meta($comment_id, CONTRIB_MARK_COMMENT_RECV_USER, ($recv > 0) ? $author : 0);
    return true;
}

/**
 * 评论取消审核 / 删除 / 垃圾：冲正已入账分值（只冲一次）。
 */
function boxmoe_contrib_revoke_comment($comment_id, $comment = null) {
    $comment_id = (int) $comment_id;
    if ($comment_id <= 0) return false;
    if (!get_comment_meta($comment_id, CONTRIB_MARK_COMMENT, true)) return false;

    $make      = (float) get_comment_meta($comment_id, CONTRIB_MARK_COMMENT_MAKE, true);
    $recv      = (float) get_comment_meta($comment_id, CONTRIB_MARK_COMMENT_RECV, true);
    $make_user = (int) get_comment_meta($comment_id, CONTRIB_MARK_COMMENT_MAKE_USER, true);
    $recv_user = (int) get_comment_meta($comment_id, CONTRIB_MARK_COMMENT_RECV_USER, true);
    $seq       = (int) get_comment_meta($comment_id, CONTRIB_MARK_COMMENT_REVOKE_SEQ, true) + 1;
    update_comment_meta($comment_id, CONTRIB_MARK_COMMENT_REVOKE_SEQ, $seq);

    if (!$comment) {
        $comment = get_comment($comment_id);
    }
    $post_id = $comment ? (int) $comment->comment_post_ID : 0;
    if (!$post_id) {
        $post_id = (int) get_comment_meta($comment_id, '_boxmoe_contrib_post_id', true);
    }

    if ($make > 0 && $make_user > 0) {
        boxmoe_contrib_ledger_insert(
            $make_user, -$make, '评论取消审核', 'comment_revoke',
            'comment:' . $comment_id . ':r' . $seq, $post_id, false
        );
    }
    if ($recv > 0 && $recv_user > 0) {
        boxmoe_contrib_ledger_insert(
            $recv_user, -$recv, '评论取消审核', 'comment_revoke',
            'comment:' . $comment_id . ':r' . $seq, $post_id, false
        );
    }

    delete_comment_meta($comment_id, CONTRIB_MARK_COMMENT);
    delete_comment_meta($comment_id, CONTRIB_MARK_COMMENT_MAKE);
    delete_comment_meta($comment_id, CONTRIB_MARK_COMMENT_RECV);
    delete_comment_meta($comment_id, CONTRIB_MARK_COMMENT_MAKE_USER);
    delete_comment_meta($comment_id, CONTRIB_MARK_COMMENT_RECV_USER);
    return true;
}

add_action('comment_post', 'boxmoe_contrib_on_comment_post', 10, 2);
function boxmoe_contrib_on_comment_post($comment_id, $approved = 1) {
    if ((string) $approved !== '1') return;
    boxmoe_contrib_award_comment($comment_id);
}

add_action('transition_comment_status', 'boxmoe_contrib_on_comment_transition', 10, 3);
function boxmoe_contrib_on_comment_transition($new_status, $old_status, $comment) {
    if (!$comment instanceof WP_Comment) return;
    if ($new_status === 'approved') {
        boxmoe_contrib_award_comment($comment->comment_ID);
    } elseif ($old_status === 'approved') {
        boxmoe_contrib_revoke_comment($comment->comment_ID, $comment);
    }
}

add_action('trashed_comment', 'boxmoe_contrib_on_comment_trashed', 10, 2);
function boxmoe_contrib_on_comment_trashed($comment_id, $comment) {
    boxmoe_contrib_revoke_comment($comment_id, $comment);
}

add_action('spammed_comment', 'boxmoe_contrib_on_comment_spammed', 10, 2);
function boxmoe_contrib_on_comment_spammed($comment_id, $comment) {
    boxmoe_contrib_revoke_comment($comment_id, $comment);
}

add_action('delete_comment', 'boxmoe_contrib_on_comment_deleted', 10, 2);
function boxmoe_contrib_on_comment_deleted($comment_id, $comment) {
    boxmoe_contrib_revoke_comment($comment_id, $comment);
}

add_action('untrashed_comment', 'boxmoe_contrib_on_comment_untrashed', 10, 2);
function boxmoe_contrib_on_comment_untrashed($comment_id, $comment) {
    if ($comment && (string) $comment->comment_approved === '1') {
        boxmoe_contrib_award_comment($comment_id);
    }
}

add_action('unspammed_comment', 'boxmoe_contrib_on_comment_unspammed', 10, 2);
function boxmoe_contrib_on_comment_unspammed($comment_id, $comment) {
    if ($comment && (string) $comment->comment_approved === '1') {
        boxmoe_contrib_award_comment($comment_id);
    }
}

// ========== 4. 修复工具：按内容重建余额（保留管理员调整） ==========

/**
 * 按当前内容（发布 + 浏览 + 点赞 + 已审核评论）计算每个用户应得的内容贡献。
 */
function boxmoe_contrib_compute_content_totals() {
    global $wpdb;

    $shuoshuo_cat = get_category_by_slug('shuoshuo');
    $totals = array();
    $stats  = array('posts' => 0, 'shuoshuo' => 0, 'comments' => 0, 'views' => 0, 'likes' => 0);

    $posts = $wpdb->get_results(
        "SELECT ID, post_author FROM {$wpdb->posts} WHERE post_type = 'post' AND post_status = 'publish'"
    );
    foreach ((array) $posts as $p) {
        $pid = (int) $p->ID;
        $uid = (int) $p->post_author;
        if (!$uid) continue;

        $is_shu = $shuoshuo_cat && has_category($shuoshuo_cat->term_id, $pid);
        $mult   = $is_shu ? CONTRIB_SHUOSHUO_RATIO : 1.0;
        $views  = (int) get_post_meta($pid, 'post_views_count', true);
        $likes  = (int) get_post_meta($pid, 'post_likes_count', true);

        if (!isset($totals[$uid])) $totals[$uid] = 0.0;
        $totals[$uid] += CONTRIB_ARTICLE_BASE * $mult;
        $totals[$uid] += CONTRIB_ARTICLE_VIEW * $mult * $views;
        $totals[$uid] += CONTRIB_ARTICLE_LIKE * $mult * $likes;

        if ($is_shu) $stats['shuoshuo']++; else $stats['posts']++;
        $stats['views'] += $views;
        $stats['likes'] += $likes;
    }

    $comments = $wpdb->get_results(
        "SELECT c.comment_ID, c.comment_post_ID, c.user_id, p.post_author
         FROM {$wpdb->comments} c
         JOIN {$wpdb->posts} p ON p.ID = c.comment_post_ID
         WHERE c.comment_approved = '1' AND p.post_status = 'publish' AND p.post_type = 'post'"
    );
    foreach ((array) $comments as $c) {
        $pid       = (int) $c->comment_post_ID;
        $commenter = (int) $c->user_id;
        $author    = (int) $c->post_author;
        if (!$author) continue;
        $is_shu = $shuoshuo_cat && has_category($shuoshuo_cat->term_id, $pid);
        $mult   = $is_shu ? CONTRIB_SHUOSHUO_RATIO : 1.0;

        if ($commenter !== $author) {
            if (!isset($totals[$author])) $totals[$author] = 0.0;
            $totals[$author] += CONTRIB_ARTICLE_COMMENTED * $mult;
        }
        if ($commenter) {
            if (!isset($totals[$commenter])) $totals[$commenter] = 0.0;
            $totals[$commenter] += CONTRIB_COMMENT_MAKE * $mult;
        }
        $stats['comments']++;
    }

    foreach ($totals as $uid => $v) {
        $totals[$uid] = round($v, 4);
    }
    return array('totals' => $totals, 'stats' => $stats);
}

/**
 * 修复工具：按内容重建余额（保留管理员调整）。
 * 会清空内容类账本、重置全部事件标记与基线，然后写回余额。
 */
function boxmoe_contrib_rebuild_all() {
    global $wpdb;

    boxmoe_contrib_ledger_install();
    if (function_exists('set_time_limit')) {
        @set_time_limit(300);
    }
    $table = boxmoe_contrib_ledger_table();

    $computed = boxmoe_contrib_compute_content_totals();
    $content  = $computed['totals'];
    $stats    = $computed['stats'];

    // 管理员调整合计（重建前读取，重建后保留）
    $admin = array();
    foreach ((array) $wpdb->get_results("SELECT user_id, SUM(delta) AS total FROM $table WHERE source = 'admin' GROUP BY user_id") as $row) {
        $admin[(int) $row->user_id] = (float) $row->total;
    }

    // 1) 清掉内容类账本（保留 admin / legacy）
    $wpdb->query("DELETE FROM $table WHERE source IN ('publish','view','like','comment_make','comment_recv','comment_revoke')");

    // 2) 重置全部事件标记 / 基线
    $wpdb->query($wpdb->prepare(
        "DELETE FROM {$wpdb->postmeta} WHERE meta_key IN (%s, %s, %s)",
        CONTRIB_MARK_PUBLISH, CONTRIB_MARK_VIEW_BASE, CONTRIB_MARK_LIKE_BASE
    ));
    $wpdb->query($wpdb->prepare(
        "DELETE FROM {$wpdb->commentmeta} WHERE meta_key IN (%s, %s, %s, %s, %s, %s, %s)",
        CONTRIB_MARK_COMMENT, CONTRIB_MARK_COMMENT_MAKE, CONTRIB_MARK_COMMENT_RECV,
        CONTRIB_MARK_COMMENT_MAKE_USER, CONTRIB_MARK_COMMENT_RECV_USER,
        CONTRIB_MARK_COMMENT_APPROVE_SEQ, CONTRIB_MARK_COMMENT_REVOKE_SEQ
    ));

    // 3) 按当前内容重写标记与基线（之后新事件继续增量入账）
    $post_ids = $wpdb->get_col($wpdb->prepare(
        "SELECT ID FROM {$wpdb->posts} WHERE post_type = %s AND post_status = %s", 'post', 'publish'
    ));
    foreach ((array) $post_ids as $pid) {
        $pid = (int) $pid;
        update_post_meta($pid, CONTRIB_MARK_PUBLISH, 1);
        update_post_meta($pid, CONTRIB_MARK_VIEW_BASE, (int) get_post_meta($pid, 'post_views_count', true));
        update_post_meta($pid, CONTRIB_MARK_LIKE_BASE, (int) get_post_meta($pid, 'post_likes_count', true));
    }
    $comments = $wpdb->get_results(
        "SELECT c.comment_ID FROM {$wpdb->comments} c
         JOIN {$wpdb->posts} p ON p.ID = c.comment_post_ID
         WHERE c.comment_approved = '1' AND p.post_status = 'publish' AND p.post_type = 'post'"
    );
    foreach ((array) $comments as $c) {
        boxmoe_contrib_comment_mark_awarded((int) $c->comment_ID);
    }

    // 4) 写回余额 = 内容分 + 管理员调整
    $existing = $wpdb->get_col($wpdb->prepare(
        "SELECT user_id FROM {$wpdb->usermeta} WHERE meta_key = %s", CONTRIB_META_KEY
    ));
    $uids = array_unique(array_merge(array_map('intval', (array) $existing), array_keys($content), array_keys($admin)));
    foreach ($uids as $uid) {
        $uid = (int) $uid;
        if ($uid <= 0) continue;
        boxmoe_contrib_balance_set($uid, ($content[$uid] ?? 0) + ($admin[$uid] ?? 0));
    }

    wp_cache_flush();
    return array_merge($stats, array('users' => count($uids)));
}

/**
 * 「清空全部贡献值」：账本 + 标记 + 基线 + 余额一起清空（不可恢复）。
 */
function boxmoe_contrib_clear_all() {
    global $wpdb;

    $table = boxmoe_contrib_ledger_table();
    if (boxmoe_contrib_ledger_table_exists()) {
        $wpdb->query("DELETE FROM $table");
    }
    $wpdb->delete($wpdb->usermeta, ['meta_key' => CONTRIB_META_KEY]);
    $wpdb->query($wpdb->prepare(
        "DELETE FROM {$wpdb->postmeta} WHERE meta_key IN (%s, %s, %s)",
        CONTRIB_MARK_PUBLISH, CONTRIB_MARK_VIEW_BASE, CONTRIB_MARK_LIKE_BASE
    ));
    $wpdb->query($wpdb->prepare(
        "DELETE FROM {$wpdb->commentmeta} WHERE meta_key IN (%s, %s, %s, %s, %s, %s, %s)",
        CONTRIB_MARK_COMMENT, CONTRIB_MARK_COMMENT_MAKE, CONTRIB_MARK_COMMENT_RECV,
        CONTRIB_MARK_COMMENT_MAKE_USER, CONTRIB_MARK_COMMENT_RECV_USER,
        CONTRIB_MARK_COMMENT_APPROVE_SEQ, CONTRIB_MARK_COMMENT_REVOKE_SEQ
    ));
    wp_cache_flush();
    return true;
}

/**
 * 分页读取某用户账本（后台用户详情页）
 */
function boxmoe_contrib_ledger_page($user_id, $per_page = 30, $paged = 1) {
    global $wpdb;

    $user_id  = (int) $user_id;
    $per_page = max(1, (int) $per_page);
    $paged    = max(1, (int) $paged);
    $offset   = ($paged - 1) * $per_page;
    if ($user_id <= 0 || !boxmoe_contrib_ledger_table_exists()) {
        return array('total' => 0, 'rows' => array());
    }

    $table = boxmoe_contrib_ledger_table();
    $total = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM $table WHERE user_id = %d", $user_id
    ));
    $rows = $wpdb->get_results($wpdb->prepare(
        "SELECT id, delta, reason, source, source_ref, post_id, created_at
         FROM $table WHERE user_id = %d ORDER BY id DESC LIMIT %d OFFSET %d",
        $user_id, $per_page, $offset
    ));
    return array('total' => $total, 'rows' => (array) $rows);
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

    // 显示「按内容重建余额」结果
    if (isset($_GET['rebuilt'])) {
        $rc = $_GET;
        $msg = sprintf(
            '重建完成！按当前内容重算：<strong>%d</strong> 篇文章、<strong>%d</strong> 篇说说、<strong>%d</strong> 条评论、<strong>%d</strong> 次浏览。已保留管理员调整，事件标记与基线已重置。',
            (int)($rc['posts'] ?? 0),
            (int)($rc['shuoshuo'] ?? 0),
            (int)($rc['comments'] ?? 0),
            (int)($rc['views'] ?? 0)
        );
        echo '<div class="notice notice-success is-dismissible"><p>' . $msg . '</p></div>';
    }
    if (isset($_GET['cleared'])) {
        echo '<div class="notice notice-warning is-dismissible"><p>已清空全站贡献值：余额、账本、事件标记与基线均已清空（不可恢复）。</p></div>';
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

    // 管理员调整部分（读账本 source=admin）
    $admin_contrib = 0.0;
    if (boxmoe_contrib_ledger_table_exists()) {
        $ledger_table  = boxmoe_contrib_ledger_table();
        $admin_contrib = (float) $wpdb->get_var("SELECT COALESCE(SUM(delta),0) FROM $ledger_table WHERE source = 'admin'");
    }
    $display_contrib = max(0, $total_contrib - $admin_contrib);
    $ledger_contrib  = boxmoe_contrib_ledger_total();

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
            <form method="post" style="margin-left:auto;" onsubmit="return confirm('按当前内容重建所有用户余额？\n\n会用文章/浏览/点赞/评论重新计算内容分，保留管理员调整，并重置事件标记与基线；之后新事件继续增量入账。\n一般只在账实不符时使用。');">
                <?php wp_nonce_field('boxmoe_contrib_rebuild', '_rebuild_nonce'); ?>
                <input type="hidden" name="page" value="boxmoe-contribution">
                <button type="submit" name="contrib_rebuild_submit" class="button button-secondary">
                    按内容重建余额
                </button>
            </form>
            <form method="post" style="display:inline;margin-left:8px;"
                  onsubmit="return confirm('确定清空全站所有用户的贡献值？\n\n将同时清空：余额、账本记录、事件标记与基线。\n等级标签不受影响。\n此操作不可恢复！');">
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
                <div class="stat-sub">不含管理员调整；总余额 <?php echo number_format($total_contrib, 2); ?>（调整 <?php echo number_format($admin_contrib, 2); ?>）</div>
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
                        <td>文章首次发布时一次性入账（事件驱动，幂等）</td>
                    </tr>
                    <tr>
                        <td>浏览</td>
                        <td><strong>+0.05</strong></td>
                        <td><strong>+0.025</strong></td>
                        <td>按浏览量增量实时入账；账本按「用户+文章+天」聚合一行</td>
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
                说说 = 分类为「shuoshuo」的文章，所有贡献值折半。每次变动都会写入持久化账本，可在用户详情页分页查看。&nbsp;
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
add_action('admin_init', 'boxmoe_contrib_handle_rebuild');
function boxmoe_contrib_handle_rebuild() {
    if (!isset($_POST['contrib_rebuild_submit'])) return;
    if (!current_user_can('manage_options')) return;
    if (!wp_verify_nonce($_POST['_rebuild_nonce'] ?? '', 'boxmoe_contrib_rebuild')) {
        wp_die('安全验证失败，请刷新页面重试。');
    }

    $stats = boxmoe_contrib_rebuild_all();

    $query = http_build_query([
        'page'     => 'boxmoe-contribution',
        'rebuilt'  => '1',
        'posts'    => $stats['posts'] ?? 0,
        'shuoshuo' => $stats['shuoshuo'] ?? 0,
        'comments' => $stats['comments'] ?? 0,
        'views'    => $stats['views'] ?? 0,
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

    // 账本 + 余额 + 事件标记/基线 一起清空
    boxmoe_contrib_clear_all();

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

    // 账本分页（不再有 200 条上限）
    $ledger_per_page = 30;
    $ledger_paged    = max(1, isset($_GET['lp']) ? (int) $_GET['lp'] : 1);
    $ledger_page     = boxmoe_contrib_ledger_page($user_id, $ledger_per_page, $ledger_paged);
    $ledger_total    = (int) $ledger_page['total'];
    $ledger_pages    = (int) ceil($ledger_total / $ledger_per_page);
    $log = array();
    foreach ($ledger_page['rows'] as $row) {
        $log[] = array(
            'id'      => (int) $row->id,
            'time'    => $row->created_at,
            'amount'  => (float) $row->delta,
            'reason'  => $row->reason,
            'post_id' => (int) $row->post_id,
            'source'  => $row->source,
        );
    }

    // 贡献构成（按 reason 聚合账本）；迁移后尚无新事件时用历史基线兜底展示
    $breakdown = boxmoe_contrib_ledger_breakdown($user_id, 30);
    if (empty($breakdown) && $contribution != 0) {
        $breakdown = array('历史贡献（迁移基线）' => $contribution);
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
                        <h2 style="padding:8px 12px;margin:0;font-size:15px;">账本记录 <span style="color:#94a3b8;font-weight:400;font-size:12px;">共 <?php echo (int) $ledger_total; ?> 条</span></h2>
                        <button id="clear-all-log" class="button button-small" style="color:#dc3232;border-color:#dc3232;margin-right:8px;">清空全部记录</button>
                    </div>
                    <div class="inside" style="margin:0; padding:0; max-height:420px; overflow-y:auto;">
                        <p style="margin:8px 12px 0;color:#94a3b8;font-size:12px;">记录仅用于展示，删除记录不会改变当前余额。</p>
                        <?php if (empty($log)): ?>
                            <p style="text-align:center;color:#94a3b8;padding:30px;">暂无账本记录</p>
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
                                <button class="contrib-log-delete" data-ledger="<?php echo (int) $entry['id']; ?>" data-user="<?php echo (int) $user_id; ?>" title="删除此记录" style="background:none;border:none;color:#ef4444;cursor:pointer;padding:2px 6px;font-size:14px;opacity:0.6;transition:opacity 0.2s;" onmouseover="this.style.opacity='1'" onmouseout="this.style.opacity='0.6'">&times;</button>
                            </li>
                            <?php endforeach; ?>
                        </ul>
                        <?php if ($ledger_pages > 1): ?>
                        <div class="tablenav" style="padding:8px 12px;">
                            <div class="tablenav-pages">
                                <span class="displaying-num">第 <?php echo (int) $ledger_paged; ?>/<?php echo (int) $ledger_pages; ?> 页</span>
                                <span class="pagination-links">
                                    <?php
                                    $lp_base = admin_url('admin.php?page=boxmoe-contrib-user&user_id=' . $user_id . '&lp=%#%');
                                    echo paginate_links([
                                        'base' => $lp_base, 'format' => '', 'current' => $ledger_paged,
                                        'total' => $ledger_pages, 'prev_text' => '&laquo;', 'next_text' => '&raquo;',
                                    ]);
                                    ?>
                                </span>
                            </div>
                        </div>
                        <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        var ledgerNonce = <?php echo wp_json_encode(wp_create_nonce('boxmoe_contrib_ledger')); ?>;
        // 清空全部（只删记录，不改余额）
        var clearBtn = document.getElementById('clear-all-log');
        if (clearBtn) {
            clearBtn.addEventListener('click', function() {
                if (!confirm('确定清空该用户全部账本记录？\n\n只删除记录，不会改变当前余额。')) return;
                this.disabled = true;
                this.textContent = '清空中...';
                var formData = new FormData();
                formData.append('action', 'boxmoe_clear_contrib_log');
                formData.append('user_id', <?php echo (int) $user_id; ?>);
                formData.append('nonce', ledgerNonce);
                fetch(ajaxurl, { method: 'POST', credentials: 'same-origin', body: formData })
                    .then(function(r) { return r.json(); })
                    .then(function(res) {
                        if (res.success) location.reload();
                        else alert(res.data.message || '清空失败');
                    })
                    .catch(function() { alert('请求失败'); });
            });
        }
        // 删除单条（只删记录，不改余额）
        document.querySelectorAll('.contrib-log-delete').forEach(function(btn) {
            btn.addEventListener('click', function() {
                if (!confirm('确定删除此记录？\n\n只删除记录，不会改变当前余额。')) return;
                var ledgerId = this.getAttribute('data-ledger');
                var uid = this.getAttribute('data-user');
                this.disabled = true;
                this.textContent = '...';
                var formData = new FormData();
                formData.append('action', 'boxmoe_delete_contrib_log');
                formData.append('user_id', uid);
                formData.append('ledger_id', ledgerId);
                formData.append('nonce', ledgerNonce);
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

// ========== 8. 账本记录删除（只删记录，不改余额） ==========

add_action('wp_ajax_boxmoe_delete_contrib_log', 'boxmoe_ajax_delete_contrib_log');
function boxmoe_ajax_delete_contrib_log() {
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => '无权限']);
    }
    check_ajax_referer('boxmoe_contrib_ledger', 'nonce');

    global $wpdb;
    $user_id   = isset($_POST['user_id']) ? (int) $_POST['user_id'] : 0;
    $ledger_id = isset($_POST['ledger_id']) ? (int) $_POST['ledger_id'] : 0;
    if ($user_id <= 0 || $ledger_id <= 0) {
        wp_send_json_error(['message' => '参数错误']);
    }
    if (!boxmoe_contrib_ledger_table_exists()) {
        wp_send_json_error(['message' => '账本表不存在']);
    }
    $table   = boxmoe_contrib_ledger_table();
    $deleted = $wpdb->delete($table, ['id' => $ledger_id, 'user_id' => $user_id], ['%d', '%d']);
    if (!$deleted) {
        wp_send_json_error(['message' => '记录不存在']);
    }
    wp_send_json_success(['message' => '已删除记录（余额未变）']);
}

add_action('wp_ajax_boxmoe_clear_contrib_log', 'boxmoe_ajax_clear_contrib_log');
function boxmoe_ajax_clear_contrib_log() {
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => '无权限']);
    }
    check_ajax_referer('boxmoe_contrib_ledger', 'nonce');

    global $wpdb;
    $user_id = isset($_POST['user_id']) ? (int) $_POST['user_id'] : 0;
    if ($user_id <= 0) {
        wp_send_json_error(['message' => '参数错误']);
    }
    if (!boxmoe_contrib_ledger_table_exists()) {
        wp_send_json_error(['message' => '账本表不存在']);
    }
    $table = boxmoe_contrib_ledger_table();
    $wpdb->delete($table, ['user_id' => $user_id], ['%d']);
    wp_send_json_success(['message' => '已清空记录（余额未变）']);
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
