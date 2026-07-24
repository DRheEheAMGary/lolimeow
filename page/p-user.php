<?php
/**
 * Template Name: 用户主页
 * 对应 /user/{username} 路由的入口模板
 *
 * @link https://www.boxmoe.com
 * @package lolimeow
 */
if (!defined('ABSPATH')) { echo 'Look your sister'; exit; }

// 获取 URL 中的用户名
$username = get_query_var('username', '');
$profile_user = null;

if (!empty($username)) {
    $profile_user = boxmoe_get_profile_user($username);
}

// 没找到用户 → 重定向到 /user/none
if (!$profile_user && $username !== 'none') {
    wp_redirect(home_url('/user/none'));
    exit;
}

// /user/none block 页
if (!$profile_user) {
    get_header(); ?>
    <div class="<?php echo boxmoe_layout_setting(); ?> blog-post">
        <div class="post-single text-center py-7">
            <div class="mb-4">
                <i class="fa fa-user-secret" style="font-size:5rem;color:var(--bs-secondary-color);"></i>
            </div>
            <h3>用户不存在</h3>
            <p class="text-muted mt-3" style="max-width:400px;margin:0 auto;">
                你查找的用户还未加入本站。
                <?php if (!is_user_logged_in()): ?>
                <br>登录后可以创建你的个人主页。
                <?php endif; ?>
            </p>
            <div class="mt-4" style="display:flex;gap:10px;justify-content:center;flex-wrap:wrap;">
                <?php if (!is_user_logged_in()): ?>
                <a href="<?php echo wp_login_url(); ?>" class="btn btn-primary">
                    <i class="fa fa-sign-in"></i> 登录
                </a>
                <a href="<?php echo wp_registration_url(); ?>" class="btn btn-outline-primary">
                    <i class="fa fa-user-plus"></i> 注册
                </a>
                <?php endif; ?>
                <a href="<?php echo home_url(); ?>" class="btn btn-outline-secondary">
                    <i class="fa fa-home"></i> 返回首页
                </a>
            </div>
        </div>
    </div>
    <?php get_sidebar(); ?>
    <?php
    get_footer();
    return;
}

// 隐私检查
if (!boxmoe_is_profile_public($profile_user->ID)) {
    get_header(); ?>
    <div class="<?php echo boxmoe_layout_setting(); ?> blog-post">
        <div class="post-single text-center py-7">
            <div class="mb-4">
                <i class="fa fa-lock" style="font-size:5rem;color:var(--bs-secondary-color);"></i>
            </div>
            <h3>主页未公开</h3>
            <p class="text-muted mt-3" style="max-width:400px;margin:0 auto;">
                该用户设置了仅登录后可见。
            </p>
            <a href="<?php echo wp_login_url(boxmoe_get_user_profile_url($profile_user->ID)); ?>" class="btn btn-primary mt-4">
                <i class="fa fa-sign-in"></i> 去登录
            </a>
        </div>
    </div>
    <?php get_sidebar(); ?>
    <?php
    get_footer();
    return;
}

// 全局化 $profile_user 供子模板使用
global $profile_user;
$profile_user = $profile_user;

// SEO 标题
function boxmoe_user_profile_seo_title($title) {
    global $profile_user;
    if ($profile_user) {
        return $profile_user->display_name . ' 的个人主页 - ' . get_bloginfo('name');
    }
    return $title;
}
add_filter('wp_title', 'boxmoe_user_profile_seo_title', 99);
add_filter('pre_get_document_title', 'boxmoe_user_profile_seo_title', 99);

get_header();

// 清理 SEO 过滤器，避免影响其他页面
remove_filter('wp_title', 'boxmoe_user_profile_seo_title', 99);
remove_filter('pre_get_document_title', 'boxmoe_user_profile_seo_title', 99);

get_template_part('page/template/user-profile');
get_sidebar();
get_footer();
