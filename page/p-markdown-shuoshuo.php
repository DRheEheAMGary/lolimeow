<?php
/**
 * Template Name: Markdown发布说说
 *
 * @package lolimeow
 */
if (!defined('ABSPATH')) { echo 'Look your sister'; exit; }

if (!is_user_logged_in()) {
    wp_safe_redirect(boxmoe_sign_in_link_page() ?: home_url('/'));
    exit;
}

get_header();
?>
<div class="<?php boxmoe_layout_setting(); ?> blog-post">
    <section class="markdown-shuoshuo-page blog-border">
        <header class="markdown-shuoshuo-page-header">
            <div>
                <h1><?php the_title(); ?></h1>
                <p>支持标题、列表、引用、链接和代码等 Markdown 语法。</p>
            </div>
            <i class="fa fa-pencil-square-o" aria-hidden="true"></i>
        </header>
        <?php boxmoe_render_markdown_shuoshuo_publisher(false); ?>
    </section>
</div>
<?php
get_sidebar();
get_footer();
