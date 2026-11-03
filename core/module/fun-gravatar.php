<?php
/**
 * @link https://www.boxmoe.com
 * @package lolimeow
 */

// 安全设置--------------------------boxmoe.com--------------------------
if(!defined('ABSPATH')){
    echo'Look your sister';
    exit;
}


// Gravatar头像--------------------------boxmoe.com--------------------------
function boxmoe_getavatar_host() {
    $gravatar_Url = 'cravatar.cn/avatar';

    switch (get_boxmoe('boxmoe_gravatar_url')) {
        case 'cn':
            $gravatar_Url = 'cn.gravatar.com/avatar';
            break;
        case 'ssl':
            $gravatar_Url = 'secure.gravatar.com/avatar';
            break;
        case 'v2excom':
            $gravatar_Url = 'cdn.v2ex.com/gravatar';
            break;
        case 'qiniu':
            $gravatar_Url = 'dn-qiniu-avatar.qbox.me/avatar';
            break;
        case 'geekzu':
            $gravatar_Url = 'fdn.geekzu.org/avatar';
            break;
        case 'cravatar':
            $gravatar_Url = 'cravatar.cn/avatar';
            break;
        case 'wavatar':
            $gravatar_Url = 'wavatar.com/avatar';
            break;
        default:
            $gravatar_Url = 'dn-qiniu-avatar.qbox.me/avatar';
    }
    return $gravatar_Url;
}


function boxmoe_qqavatar_host() {
    $qqavatar_Url = 'q2.qlogo.cn';
    switch (get_boxmoe('boxmoe_qqavatar_url')) {
        case 'Q1':
            $qqavatar_Url = 'q1.qlogo.cn';
            break;
        case 'Q2':
            $qqavatar_Url = 'q2.qlogo.cn';
            break;
        case 'Q3':
            $qqavatar_Url = 'q3.qlogo.cn';
            break;
        case 'Q4':
            $qqavatar_Url = 'q4.qlogo.cn';
        default:
            $qqavatar_Url = 'q2.qlogo.cn';
    }
    return $qqavatar_Url;
}


function boxmoe_get_avatar($avatar, $id_or_email, $size = 96, $default = '', $alt = '', $args = array()) {
    $email = '';
    $user_id = '';
    if (is_numeric($id_or_email)) {
        $id   = (int) $id_or_email;
        $user = get_userdata($id);
        $user_id = $id_or_email;
        if ($user)
            $email = $user->user_email;
            $user_id = $user->ID;
    } else if (is_object($id_or_email)) {
        $user_id = $id_or_email->user_id;
        if (!empty($user_id)) {
            $id   = (int) $id_or_email->user_id;
            $user = get_userdata($id);
            if ($user)
                $email = $user->user_email;
                $user_id = $user->ID;
        } else if (!empty($id_or_email->comment_author_email)) {
            $email = $id_or_email->comment_author_email;
        }
    } else {
        $email = $id_or_email;
    }
    $hash       = md5(strtolower(trim($email)));
    $gavatarurl = 'https://' . boxmoe_getavatar_host() . '/' . $hash;

    $class = isset($args['class']) 
        ? array_merge(['avatar'], is_array($args['class']) ? $args['class'] : explode(' ', $args['class'])) 
        : ['avatar'];
    $class = array_map('sanitize_html_class', $class);
    $class = esc_attr(implode(' ', array_unique($class)));

    // 封禁用户返回默认头像
    if (isset($user_id) && function_exists('boxmoe_is_user_banned') && boxmoe_is_user_banned($user_id) && !current_user_can('manage_options')) {
        $gavatarurl = get_avatar_url(0);
        return '<img src="' . $gavatarurl . '" class="' . $class . '" alt="avatar" />';
    }

    if (isset($user_id)) {
        $user_avatar_url = get_user_meta($user_id, 'user_avatar', true);
        // SSO 统一头像：用户没自己传过就用统一认证那边的
        if (!$user_avatar_url) {
            $sso_picture = get_user_meta($user_id, 'sso_picture', true);
            if ($sso_picture) {
                $user_avatar_url = $sso_picture;
            }
        }
        if ($user_avatar_url) {
            return '<img src="' . $user_avatar_url . '" class="' . $class . '" alt="avatar" />';
        } elseif (stripos($email, "@qq.com"))  {
            $qq = str_ireplace("@qq.com", "", $email);
            if (preg_match("/^\d+$/", $qq)) {
                $qqavatar = "https://" . boxmoe_qqavatar_host() . "/headimg_dl?dst_uin=" . $qq . "&spec=100";
                return '<img src="' . $qqavatar . '" class="' . $class . '" alt="avatar" />';
            } else {
                return '<img src="' . $gavatarurl . '" class="' . $class . '" alt="avatar" />';
            }
        } else {
            return '<img src="' . $gavatarurl . '" class="' . $class . '" alt="avatar" />';
        }
    } elseif (stripos($email, "@qq.com"))  {
        $qq = str_ireplace("@qq.com", "", $email);
        if (preg_match("/^\d+$/", $qq)) {
            $qqavatar = "https://" . boxmoe_qqavatar_host() . "/headimg_dl?dst_uin=" . $qq . "&spec=100";
            return '<img src="' . $qqavatar . '" class="' . $class . '" alt="avatar" />';
        } else {
            return '<img src="' . $gavatarurl . '" class="' . $class . '" alt="avatar" />';
        }
    } else {
        return '<img src="' . $gavatarurl . '" class="' . $class . '" alt="avatar" />';
    }
}
add_filter('get_avatar', 'boxmoe_get_avatar', 10, 6);

// 提取头像地址--------------------------boxmoe.com--------------------------
function boxmoe_get_avatar_url($id_or_email, $size = 100) {
    $email = '';
    $user_id = '';
    // 封禁用户返回默认头像（管理员除外）
    if (is_numeric($id_or_email) && $id_or_email > 0) {
        if (function_exists('boxmoe_is_user_banned') && boxmoe_is_user_banned((int)$id_or_email) && !current_user_can('manage_options')) {
            return get_avatar_url(0, ['size' => $size]);
        }
    }
        if (is_numeric($id_or_email)) {
        $user = get_userdata($id_or_email);
        if ($user) {
            $user_id = $id_or_email;
            $email = $user->user_email;
        }
    } else {
        $email = $id_or_email;
        $user = get_user_by('email', $email);
        if ($user) {
            $user_id = $user->ID;
        }
    }
    if ($user_id) {
        $user_avatar_url = get_user_meta($user_id, 'user_avatar', true);
        if ($user_avatar_url) {
            return $user_avatar_url;
        }
        // SSO 统一头像：用户没自己传过就用统一认证那边的
        $sso_picture = get_user_meta($user_id, 'sso_picture', true);
        if ($sso_picture) {
            return $sso_picture;
        }
    }
    if (stripos($email, "@qq.com")) {
        $qq = str_ireplace("@qq.com", "", $email);
        if (preg_match("/^\d+$/", $qq)) {
            return "https://" . boxmoe_qqavatar_host() . "/headimg_dl?dst_uin=" . $qq . "&spec=100";
        }
    }
    $hash = md5(strtolower(trim($email)));
    return 'https://' . boxmoe_getavatar_host() . '/' . $hash;
}




//get_avatar(get_the_author_meta('ID'), 100, '', '', array('class' => 'lazy'));

