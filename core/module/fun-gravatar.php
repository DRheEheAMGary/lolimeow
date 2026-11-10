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

/**
 * 从 get_avatar()/模板里常见的 $id_or_email 解析出用户 ID。
 *
 * 返回 0 表示这是游客（按邮箱走 Gravatar/QQ，不挂头像框）。
 */
function boxmoe_avatar_user_id($id_or_email) {
    if (is_numeric($id_or_email) && (int) $id_or_email > 0) {
        return (int) $id_or_email;
    }

    if (is_object($id_or_email)) {
        if (!empty($id_or_email->user_id)) {
            return (int) $id_or_email->user_id;
        }
        if (!empty($id_or_email->ID)) {
            return (int) $id_or_email->ID;
        }
        if (!empty($id_or_email->comment_author_email)) {
            $user = get_user_by('email', $id_or_email->comment_author_email);
            return $user ? (int) $user->ID : 0;
        }
        return 0;
    }

    if (is_string($id_or_email) && is_email($id_or_email)) {
        $user = get_user_by('email', $id_or_email);
        return $user ? (int) $user->ID : 0;
    }

    return 0;
}

/**
 * 读取 SSO 同步下来的头像框。
 *
 * 关键点：框只属于「已绑定 SSO 的用户」，游客和封禁用户不挂框。
 * 框的图片地址优先用 SSO 同步的绝对地址；老数据没有 URL 时按 key 兜底。
 */
function boxmoe_avatar_frame_data($user_id) {
    $user_id = (int) $user_id;
    if ($user_id <= 0) {
        return null;
    }

    if (function_exists('boxmoe_is_user_banned')
        && boxmoe_is_user_banned($user_id)
        && !current_user_can('manage_options')) {
        return null;
    }

    $key = sanitize_key((string) get_user_meta($user_id, 'sso_avatar_frame', true));
    $keyFromCookie = false;

    // SSO 换框后会写一个 .dreamgary.cn 共享 cookie，值为「用户ID.框key」。
    // 只要 cookie 里的用户 ID 和当前头像用户一致就优先用 cookie：
    // 换框后即使没有重新登录博客、甚至当前是游客状态，也能立即看到新框。
    // cookie 不存在时才回退到登录时同步的 meta。
    $cookie = isset($_COOKIE['sso_avatar_frame']) ? (string) $_COOKIE['sso_avatar_frame'] : '';
    if ($cookie !== '') {
        $parts = explode('.', $cookie, 2);
        if ((int) ($parts[0] ?? 0) === $user_id) {
            $cookieKey = (string) ($parts[1] ?? '');
            if ($cookieKey === 'none') {
                return null;
            }
            if ($cookieKey !== '') {
                $key = sanitize_key($cookieKey);
                $keyFromCookie = true;
            }
        }
    }

    if ($key === '') {
        return null;
    }

    $url = (string) get_user_meta($user_id, 'sso_avatar_frame_url', true);
    $fallback = [
        'mg'      => 'https://sso.dreamgary.cn/assets/frames/mg.webp',
        'rainbow' => 'https://sso.dreamgary.cn/assets/frames/rainbow.webp',
        'diamond_sword' => 'https://sso.dreamgary.cn/assets/frames/diamond_sword.webp',
        'gold_bubble' => 'https://sso.dreamgary.cn/assets/frames/gold_bubble.webp',
        'winter_snow' => 'https://sso.dreamgary.cn/assets/frames/winter_snow.webp',
        'basori_tiara' => 'https://sso.dreamgary.cn/assets/frames/basori_tiara.webp',
    ];
    // 已知框一律以本地目录的 key→URL 映射为准，避免 cookie/meta 换框后
    // key 与旧 URL 不一致。未来新增框才回退到 meta 里的绝对地址。
    if (isset($fallback[$key])) {
        $url = $fallback[$key];
    } elseif ($keyFromCookie
        || $url === ''
        || !preg_match('#^https://sso\.dreamgary\.cn/assets/frames/[a-z0-9-]+\.webp$#i', $url)) {
        $url = '';
    }
    if ($url === '') {
        return null;
    }

    $scale = (float) get_user_meta($user_id, 'sso_avatar_frame_scale', true);
    if ($scale < 1.05 || $scale > 3) {
        $scale = 1.6;
    }

    return [
        'key'      => $key,
        'url'      => esc_url_raw($url),
        'scale'    => $scale,
        'min_size' => 34,
    ];
}

/**
 * 头像容器的公共 class / 属性。
 *
 * 小尺寸（<34px）不显示框：成员列表/后台列表里的 24px 头像挂上会糊成一片。
 */
function boxmoe_avatar_frame_class($user_id, $size) {
    $frame = boxmoe_avatar_frame_data($user_id);
    $show  = $frame !== null && (int) $size >= (int) $frame['min_size'];
    return 'sso-av' . ($show ? ' sso-av--framed' : '');
}

function boxmoe_avatar_frame_attrs($user_id, $size) {
    $frame = boxmoe_avatar_frame_data($user_id);
    if ($frame === null || (int) $size < (int) $frame['min_size']) {
        return '';
    }

    $labels = ['mg' => '毛怪', 'rainbow' => '彩虹', 'diamond_sword' => '钻石剑', 'gold_bubble' => '金色泡泡', 'winter_snow' => '霜雪', 'basori_tiara' => 'Basori Tiara'];
    return ' data-frame="' . esc_attr($frame['key']) . '"'
         . ' data-frame-label="' . esc_attr($labels[$frame['key']] ?? $frame['key']) . '"'
         . ' style="--sso-frame:url(\'' . esc_url($frame['url']) . '\');'
         . '--sso-frame-scale:' . (float) $frame['scale'] . '"';
}

/**
 * 主题统一头像输出。
 *
 * 以前模板直接拼 <img>，导致头像框只能覆盖 get_avatar()，覆盖不到主题的
 * 20 多个直接调用点。所有头像都从这里输出后，框才有唯一入口。
 *
 * 参数：
 *   $id_or_email  用户 ID / 邮箱 / 评论对象
 *   $size         目标显示尺寸（px）
 *   $args         class / alt / id / lazy / style / url
 */
function boxmoe_avatar_img($id_or_email, $size = 100, $args = array()) {
    $size = max(1, (int) $size);
    $args = wp_parse_args($args, array(
        'class' => 'avatar',
        'alt'   => '',
        'id'    => '',
        'lazy'  => false,
        'style' => '',
        'url'   => '',
        'frame' => true,
        'auto'  => false,
    ));

    $user_id = !empty($args['frame']) ? boxmoe_avatar_user_id($id_or_email) : 0;

    // 允许调用方明确指定地址（例如封禁用户的默认头像）
    $url = trim((string) $args['url']);
    if ($url === '') {
        $url = boxmoe_get_avatar_url($id_or_email, $size);
    }
    if ($url === '') {
        $url = get_avatar_url(0, array('size' => $size));
    }

    $classes = is_array($args['class'])
        ? $args['class']
        : preg_split('/\s+/', trim((string) $args['class']));
    $classes = array_values(array_filter(array_map('sanitize_html_class', $classes)));
    if (empty($classes)) {
        $classes = array('avatar');
    }
    if (!empty($args['lazy']) && !in_array('lazy', $classes, true)) {
        $classes[] = 'lazy';
    }

    $img_class = esc_attr(implode(' ', array_unique($classes)));
    $alt       = esc_attr((string) $args['alt']);
    $src       = esc_url($url);
    $data_src  = '';
    if (!empty($args['lazy']) && function_exists('boxmoe_lazy_load_images')) {
        $placeholder = boxmoe_lazy_load_images();
        if ($placeholder !== '') {
            $src      = esc_url($placeholder);
            $data_src = ' data-src="' . esc_url($url) . '"';
        }
    }

    $id_attr    = $args['id'] !== '' ? ' id="' . esc_attr((string) $args['id']) . '"' : '';
    $style_attr = $args['style'] !== '' ? ' style="' . esc_attr((string) $args['style']) . '"' : '';

    $wrap_class = boxmoe_avatar_frame_class($user_id, $size);
    if (!empty($args['auto'])) {
        // get_avatar() 的尺寸只是请求值，实际显示尺寸常由主题 CSS 决定。
        // 这种情况下不写死宽高，让 img 的 class 决定尺寸，wrapper 自动收缩。
        $wrap_class .= ' sso-av--auto';
    }
    $wrap_style = '--sso-av-size:' . $size . 'px;';
    if (empty($args['auto'])) {
        $wrap_style = 'width:' . $size . 'px;height:' . $size . 'px;' . $wrap_style;
    }
    $frame_attr = boxmoe_avatar_frame_attrs($user_id, $size);
    if ($frame_attr !== '') {
        // 框属性里也带 style，这里把尺寸并进去，避免出现两个 style 属性
        $frame_style = '';
        if (preg_match('/style="([^"]*)"/', $frame_attr, $m)) {
            $frame_style = $m[1];
        }
        $frame_attr = preg_replace('/ style="[^"]*"/', '', $frame_attr);
        $wrap_style .= $frame_style;
    }

    return '<span class="' . esc_attr($wrap_class) . '"' . $frame_attr
         . ' style="' . esc_attr($wrap_style) . '">'
         . '<img src="' . $src . '"' . $data_src
         . ' class="' . $img_class . '" alt="' . $alt . '"'
         . ' width="' . $size . '" height="' . $size . '"'
         . $id_attr . $style_attr . '>'
         . '</span>';
}

/**
 * get_avatar() 过滤器：核心/插件调用也走统一头像输出。
 *
 * 主题自己的模板不再依赖这个过滤器，但评论区、成员列表等 get_avatar()
 * 调用点仍然会经过这里。
 */
function boxmoe_get_avatar($avatar, $id_or_email, $size = 96, $default = '', $alt = '', $args = array()) {
    $class = isset($args['class'])
        ? $args['class']
        : 'avatar';

    return boxmoe_avatar_img($id_or_email, $size, array(
        'class' => $class,
        'alt'   => $alt,
        'lazy'  => false,
        'auto'  => true,
    ));
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

/**
 * 头像框样式。放在主题 CSS 里而不是每次内联，保证所有页面一致。
 */
function boxmoe_avatar_frame_assets() {
    wp_enqueue_style(
        'boxmoe-avatar-frame',
        get_template_directory_uri() . '/assets/css/avatar-frame.css',
        array(),
        (string) (filemtime(get_template_directory() . '/assets/css/avatar-frame.css') ?: '20261105')
    );
}
add_action('wp_enqueue_scripts', 'boxmoe_avatar_frame_assets');

//get_avatar(get_the_author_meta('ID'), 100, '', '', array('class' => 'lazy'));
