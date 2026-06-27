<?php
/**
 * Template Name: 博客主页-全屏Banner
 * @link https://www.boxmoe.com
 * @package lolimeow
 */
//boxmoe.com===安全设置=阻止直接访问主题文件
if(!defined('ABSPATH')){echo'Look your sister';exit;}

// 覆盖 banner 高度为 100vh，隐藏文章列表及侧边栏
function boxmoe_home_fullscreen_banner_css() {
    echo '<style>
        html,body{overflow:hidden !important;height:100% !important;}
        .boxmoe_header_banner {height:100vh !important;overflow:hidden !important;}
        .boxmoe-container,.boxmoe-footer,.sidebar,#sidebar,.col-lg-10,.col-lg-8,.pagenav,footer{display:none !important;}
        @media (max-width:768px) {.boxmoe_header_banner {height:100vh !important;overflow:hidden !important;}}
    </style>';
}
add_action('wp_head', 'boxmoe_home_fullscreen_banner_css', 99);

get_header(); 
get_footer();

