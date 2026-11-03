<?php
/**
 * 关注系统模块
 * @package lolimeow
 */
if (!defined('ABSPATH')) { echo 'Look your sister'; exit; }

// ==================== 数据操作 ====================

function boxmoe_get_following($user_id) {
    $v = get_user_meta($user_id, 'boxmoe_following', true);
    return is_array($v) ? $v : [];
}

function boxmoe_get_followers($user_id) {
    $v = get_user_meta($user_id, 'boxmoe_followers', true);
    return is_array($v) ? $v : [];
}

function boxmoe_is_following($from, $to) {
    return in_array($to, boxmoe_get_following($from));
}

function boxmoe_can_be_followed($user_id) {
    return get_user_meta($user_id, 'boxmoe_allow_follow', true) !== 'no';
}

// ==================== 关注 / 取关 ====================

function boxmoe_follow_user($from, $to) {
    if ($from == $to) return false;
    if (!boxmoe_can_be_followed($to)) return false;
    $following = boxmoe_get_following($from);
    if (in_array($to, $following)) return true; // 已关注
    $following[] = $to;
    update_user_meta($from, 'boxmoe_following', $following);
    $followers = boxmoe_get_followers($to);
    $followers[] = $from;
    update_user_meta($to, 'boxmoe_followers', $followers);
    return true;
}

function boxmoe_unfollow_user($from, $to) {
    $following = boxmoe_get_following($from);
    $idx = array_search($to, $following);
    if ($idx === false) return false;
    array_splice($following, $idx, 1);
    update_user_meta($from, 'boxmoe_following', $following);
    $followers = boxmoe_get_followers($to);
    $idx2 = array_search($from, $followers);
    if ($idx2 !== false) {
        array_splice($followers, $idx2, 1);
        update_user_meta($to, 'boxmoe_followers', $followers);
    }
    return true;
}

// ==================== 隐私检查 ====================

function boxmoe_can_view_following($user_id) {
    if (current_user_can('manage_options')) return true;
    if (get_current_user_id() === $user_id) return true;
    $v = get_user_meta($user_id, 'following_visibility', true) ?: 'public';
    if ($v === 'public') return true;
    if ($v === 'members') return is_user_logged_in();
    return false;
}

function boxmoe_can_view_followers($user_id) {
    if (current_user_can('manage_options')) return true;
    if (get_current_user_id() === $user_id) return true;
    $v = get_user_meta($user_id, 'followers_visibility', true) ?: 'public';
    if ($v === 'public') return true;
    if ($v === 'members') return is_user_logged_in();
    return false;
}

// ==================== AJAX ====================

add_action('wp_ajax_boxmoe_toggle_follow', 'boxmoe_ajax_toggle_follow');
function boxmoe_ajax_toggle_follow() {
    if (!is_user_logged_in()) wp_send_json_error(['message' => '请先登录']);
    $uid = get_current_user_id();
    $tid = isset($_POST['user_id']) ? (int)$_POST['user_id'] : 0;
    if (!$tid || $uid == $tid) wp_send_json_error(['message' => '参数错误']);
    if (boxmoe_is_following($uid, $tid)) {
        boxmoe_unfollow_user($uid, $tid);
        wp_send_json_success(['following' => false, 'count' => count(boxmoe_get_followers($tid))]);
    } else {
        if (!boxmoe_can_be_followed($tid)) wp_send_json_error(['message' => '该用户不允许被关注']);
        boxmoe_follow_user($uid, $tid);
        wp_send_json_success(['following' => true, 'count' => count(boxmoe_get_followers($tid))]);
    }
}
