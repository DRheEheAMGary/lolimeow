<?php
/**
 * Template Name: 博客主页-全屏Banner
 * @link https://www.boxmoe.com
 * @package lolimeow
 */
//boxmoe.com===安全设置=阻止直接访问主题文件
if(!defined('ABSPATH')){echo'Look your sister';exit;}

// 覆盖 banner 高度为 100vh
function boxmoe_home_fullscreen_banner_css() {
    echo '<style>
        .boxmoe_header_banner {height:100vh !important;}
        @media (max-width:768px) {.boxmoe_header_banner {height:100vh !important;}}
    </style>';
}
add_action('wp_head', 'boxmoe_home_fullscreen_banner_css', 99);

get_header(); 
// 替换全局主查询为博客文章列表，避免「主页」页面本身作为文章卡片出现
global $wp_query, $wp_the_query;
$paged = get_query_var('paged') ?: 1;
$wp_query = new WP_Query(array(
    'post_type'      => 'post',
    'posts_per_page' => get_option('posts_per_page', 10),
    'paged'          => $paged,
    'orderby'        => 'date',
    'order'          => 'DESC',
    'ignore_sticky_posts' => 0,
));
$wp_the_query = $wp_query;
get_template_part('page/template/blog-list');
wp_reset_query();
get_sidebar();
get_footer();
