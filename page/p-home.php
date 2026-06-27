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
get_template_part('page/template/blog-list');
get_sidebar();
get_footer();
