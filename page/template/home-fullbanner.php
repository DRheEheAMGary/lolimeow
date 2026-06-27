<?php
/**
 * @link https://www.boxmoe.com
 * @package lolimeow
 */
//boxmoe.com===安全设置=阻止直接访问主题文件
if(!defined('ABSPATH')){echo'Look your sister';exit;}
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo boxmoe_theme_title(); ?></title>
    <link rel="icon" href="<?php echo boxmoe_favicon(); ?>" type="image/x-icon">
    <?php boxmoe_keywords(); ?>
    <?php boxmoe_description(); ?>
    <?php ob_start();wp_head();$wp_head_output = ob_get_clean();echo preg_replace('/\n/', "\n    ", trim($wp_head_output))."\n    ";?>
</head>
<body <?php body_class(); ?>>

<?php boxmoe_festival_lantern(); ?>

<!-- 全屏 Banner 区域 -->
<section class="home-fullbanner" id="home-banner">
    <div class="home-fullbanner-bg">
        <img src="<?php boxmoe_banner_image(); ?>" alt="banner" class="banner-img">
        <div class="banner-overlay"></div>
    </div>

    <!-- 导航栏 -->
    <header class="home-navbar">
        <nav class="navbar navbar-expand-lg w-100">
            <div class="container d-flex justify-content-between align-items-center">
                <button class="navbar-toggler offcanvas-nav-btn" type="button" data-bs-toggle="offcanvas" data-bs-target="#homeOffcanvas">
                    <span class="navbar-toggler-bar"></span>
                    <span class="navbar-toggler-bar"></span>
                    <span class="navbar-toggler-bar"></span>
                </button>
                <a class="navbar-brand mx-auto" href="<?php echo home_url(); ?>">
                    <?php boxmoe_logo(); ?>
                </a>
                <div class="d-flex d-lg-none align-items-center">
                    <form class="mobile-search-form" role="search" method="get" action="<?php echo home_url('/'); ?>">
                        <input type="search" class="mobile-search-input" placeholder="搜索..." name="s" value="<?php echo get_search_query(); ?>">
                        <button type="submit" class="mobile-search-btn"><i class="fa fa-search"></i></button>
                    </form>
                </div>

                <div class="offcanvas offcanvas-start offcanvas-nav width" id="homeOffcanvas">
                    <div class="offcanvas-header">
                        <a href="<?php echo home_url(); ?>" class="text-inverse"><?php boxmoe_logo(); ?></a>
                        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
                    </div>
                    <?php if(is_user_logged_in() && get_boxmoe('boxmoe_sign_in_link_switch')): ?>
                    <div class="mobile-logged-user-wrapper d-block d-lg-none">
                        <a href="<?php echo boxmoe_user_center_link_page(); ?>" class="user-info-wrap d-flex align-items-center">
                            <div class="user-avatar">
                                <img src="<?php boxmoe_lazy_load_images(); ?>" data-src="<?php echo boxmoe_get_avatar_url(get_current_user_id(), 100); ?>" alt="avatar" class="img-fluid rounded-3 lazy">
                            </div>
                            <div class="user-info">
                                <div class="user-name"><?php echo get_the_author_meta('display_name', get_current_user_id()); ?></div>
                                <div class="user-email"><?php echo get_the_author_meta('user_email', get_current_user_id()); ?></div>
                            </div>
                        </a>
                    </div>
                    <?php endif; ?>
                    <div class="lighting d-lg-none">
                        <ul>
                            <li data-bs-theme-value="light" aria-pressed="false">Light</li>
                            <li data-bs-theme-value="dark" aria-pressed="false">Dark</li>
                            <li data-bs-theme-value="auto" aria-pressed="true">Auto</li>
                        </ul>
                    </div>
                    <div class="offcanvas-body pt-0 align-items-center">
                        <?php boxmoe_nav_menu(); ?>
                        <div class="nav-right-section d-flex align-items-center">
                            <div class="search-box">
                                <form class="search-form" role="search" method="get" action="<?php echo home_url('/'); ?>">
                                    <input type="search" class="search-input" placeholder="搜索..." name="s" value="<?php echo get_search_query(); ?>">
                                    <button type="submit" class="search-btn"><i class="fa fa-search"></i></button>
                                </form>
                            </div>
                            <div class="dropdown">
                                <button class="float-btn bd-theme" type="button" aria-expanded="false" data-bs-toggle="dropdown" aria-label="Toggle theme">
                                    <i class="fa fa-adjust"></i>
                                </button>
                                <ul class="bs-theme dropdown-menu dropdown-menu-end shadow" aria-labelledby="bs-theme-text">
                                    <li><button type="button" class="dropdown-item d-flex align-items-center" data-bs-theme-value="light"><i class="fa fa-sun-o"></i><span class="ms-2">Light</span></button></li>
                                    <li><button type="button" class="dropdown-item d-flex align-items-center" data-bs-theme-value="dark"><i class="fa fa-moon-o"></i><span class="ms-2">Dark</span></button></li>
                                    <li><button type="button" class="dropdown-item d-flex align-items-center active" data-bs-theme-value="auto"><i class="fa fa-adjust"></i><span class="ms-2">Auto</span></button></li>
                                </ul>
                            </div>
                            <?php if(!is_user_logged_in() && get_boxmoe('boxmoe_sign_in_link_switch')): ?>
                            <div class="user-wrapper d-none d-lg-flex">
                                <div class="user-login-wrap">
                                    <a href="<?php echo boxmoe_sign_in_link_page(); ?>" class="user-login"><span class="login-text">登录</span></a>
                                </div>
                                <span class="divider">or</span>
                                <div class="user-reg-wrap">
                                    <a href="<?php echo boxmoe_sign_up_link_page(); ?>" class="user-reg"><span class="reg-text">注册</span></a>
                                </div>
                                <img src="<?php echo boxmoe_theme_url(); ?>/assets/images/up-new-iocn.png" class="new-tag" alt="new">
                            </div>
                            <?php endif; ?>
                            <?php if(is_user_logged_in() && get_boxmoe('boxmoe_sign_in_link_switch')): ?>
                            <div class="logged-user-wrapper d-none d-lg-flex">
                                <div class="user-info-wrap d-flex align-items-center dropdown">
                                    <a href="<?php echo boxmoe_user_center_link_page(); ?>" class="dropdown-toggle d-flex align-items-center" data-bs-toggle="dropdown">
                                        <div class="user-avatar">
                                            <img src="<?php boxmoe_lazy_load_images(); ?>" data-src="<?php echo boxmoe_get_avatar_url(get_current_user_id(), 100); ?>" alt="avatar" class="img-fluid rounded-3 lazy">
                                        </div>
                                        <div class="user-info">
                                            <div class="user-name"><?php echo get_the_author_meta('display_name', get_current_user_id()); ?></div>
                                            <div class="user-email"><?php echo get_the_author_meta('user_email', get_current_user_id()); ?></div>
                                        </div>
                                    </a>
                                    <ul class="dropdown-menu dropdown-menu-end">
                                        <li><a class="dropdown-item" href="<?php echo boxmoe_user_center_link_page(); ?>"><i class="fa fa-user-circle"></i>会员中心</a></li>
                                        <?php if(current_user_can('administrator')): ?>
                                        <li><a class="dropdown-item" target="_blank" href="<?php echo admin_url(); ?>"><i class="fa fa-cog"></i>后台管理</a></li>
                                        <?php endif; ?>
                                        <li><a class="dropdown-item" href="<?php echo wp_logout_url(home_url()); ?>"><i class="fa fa-sign-out"></i>注销登录</a></li>
                                    </ul>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </nav>
    </header>

    <!-- Banner 文字 -->
    <div class="home-banner-content">
        <h1 class="home-banner-title"><?php the_title(); ?></h1>
        <p class="home-banner-subtitle"><?php echo get_bloginfo('description'); ?></p>
        <div class="home-banner-stats">
            <span class="stat-item"><i class="fa fa-file-text-o"></i> <?php echo wp_count_posts()->publish; ?> 篇文章</span>
            <span class="stat-item"><i class="fa fa-comments-o"></i> <?php echo wp_count_comments()->total_comments; ?> 条评论</span>
            <span class="stat-item"><i class="fa fa-calendar-o"></i> Since <?php 
                $first_post = get_posts(array('numberposts' => 1, 'orderby' => 'date', 'order' => 'ASC', 'post_status' => 'publish'));
                echo !empty($first_post) ? get_the_time('Y', $first_post[0]) : date('Y');
            ?></span>
        </div>
    </div>

    <!-- 向下箭头 -->
    <div class="home-scroll-indicator">
        <span class="scroll-text">向下滚动</span>
        <i class="fa fa-angle-down scroll-arrow"></i>
    </div>
</section>

<!-- 文章列表区域 -->
<main class="home-content">
    <div class="container">
        <div class="row">
            <div class="col-12 col-lg-8">
                <div class="home-posts-header mb-4">
                    <h2 class="section-title"><i class="fa fa-newspaper-o"></i> 最新文章</h2>
                </div>

                <?php
                $paged = (get_query_var('paged')) ? get_query_var('paged') : 1;
                $args = array(
                    'posts_per_page' => get_option('posts_per_page', 10),
                    'paged'          => $paged,
                    'orderby'        => 'date',
                    'order'          => 'DESC',
                    'ignore_sticky_posts' => 0,
                );
                $home_query = new WP_Query($args);
                ?>

                <?php if ($home_query->have_posts()) : ?>
                    <?php while ($home_query->have_posts()) : $home_query->the_post(); ?>
                    <article class="post-list list-one row blog-border">
                        <div class="post-list-img">
                            <figure class="mb-4 mb-lg-0 zoom-img">
                                <a <?php echo boxmoe_article_new_window(); ?> href="<?php the_permalink(); ?>" title="<?php the_title(); ?>">
                                    <img src="<?php boxmoe_lazy_load_images(); ?>" 
                                         data-src="<?php echo boxmoe_article_thumbnail_src(); ?>" 
                                         alt="<?php the_title(); ?>" class="img-fluid rounded-3 lazy">
                                </a>
                            </figure>
                        </div>
                        <div class="post-list-content">
                            <div class="category">
                                <div class="tags">
                                    <?php 
                                    $categories = get_the_category();
                                    if (!empty($categories)) {
                                        foreach ($categories as $cat) {
                                            $cat_color = get_term_meta($cat->term_id, 'boxmoe_cat_color', true) ?: '#999';
                                            echo '<a href="' . esc_url(get_category_link($cat->term_id)) . '" title="查看《' . esc_attr($cat->name) . '》分类下的所有文章" rel="category tag">';
                                            echo '<i class="tagfa fa fa-dot-circle-o" style="color:' . esc_attr($cat_color) . '"></i>' . esc_html($cat->name) . '</a> ';
                                        }
                                    }
                                    ?>
                                </div>
                            </div>
                            <div class="mt-2 mb-2">
                                <h3 class="post-title h4">
                                    <a href="<?php the_permalink(); ?>" title="<?php the_title(); ?>" class="text-reset"><?php the_title(); ?></a>
                                </h3>
                                <p class="post-content"><?php echo _get_excerpt(80); ?></p>
                            </div>
                            <div class="post-meta align-items-center">
                                <div class="post-list-avatar">
                                    <img src="<?php boxmoe_lazy_load_images(); ?>" 
                                         data-src="<?php echo boxmoe_get_avatar_url(get_the_author_meta('ID'), 80); ?>" 
                                         alt="avatar" class="avatar lazy">
                                </div>
                                <div class="post-meta-info">
                                    <div class="post-meta-stats">
                                        <span class="list-post-view">
                                            <i class="fa fa-street-view"></i><?php echo getPostViews(get_the_ID()); ?>
                                        </span>
                                        <span class="list-post-comment">
                                            <i class="fa fa-comments-o"></i><?php echo get_comments_number(); ?>
                                        </span>
                                    </div>
                                    <span class="list-post-author">
                                        <i class="fa fa-at"></i><?php the_author(); ?>
                                        <span class="dot"></span><?php echo get_the_time('Y-m-d'); ?>
                                    </span>
                                </div>
                            </div>
                        </div>
                    </article>
                    <?php endwhile; ?>

                    <div class="col-lg-12 col-md-12 pagenav">
                        <?php boxmoe_pagination($home_query); ?>
                    </div>

                <?php else : ?>
                    <div class="text-center py-7">
                        <div class="mb-4"><i class="fa fa-inbox" style="font-size:4rem;color:#ccc;"></i></div>
                        <p class="text-muted">还没有内容，敬请期待～</p>
                    </div>
                <?php endif; wp_reset_postdata(); ?>
            </div>

            <div class="col-12 col-lg-4">
                <?php if (is_active_sidebar('sidebar-1')) : ?>
                    <aside class="sidebar mt-5 mt-lg-0"><?php dynamic_sidebar('sidebar-1'); ?></aside>
                <?php endif; ?>
            </div>
        </div>
    </div>
</main>

<!-- 页脚 -->
<footer class="home-footer">
    <div class="container text-center py-4">
        <p class="mb-0 text-muted small">
            Copyright &copy; <?php echo date('Y'); ?> 
            <a href="<?php echo home_url(); ?>" class="text-reset"><?php echo get_bloginfo('name'); ?></a>
            | Theme by <a href="https://www.boxmoe.com" class="text-reset" target="_blank">Boxmoe</a>
            | Powered by WordPress
        </p>
    </div>
</footer>

<script>
document.addEventListener('DOMContentLoaded', function() {
    var navbar = document.querySelector('.home-navbar .navbar');
    var scrollIndicator = document.querySelector('.home-scroll-indicator');
    var contentSection = document.querySelector('.home-content');
    
    // 导航栏滚动效果
    window.addEventListener('scroll', function() {
        if (window.scrollY > 80) {
            navbar.classList.add('scrolled');
        } else {
            navbar.classList.remove('scrolled');
        }
    });
    
    // 点击向下箭头滚动到内容区
    if (scrollIndicator && contentSection) {
        scrollIndicator.addEventListener('click', function() {
            contentSection.scrollIntoView({ behavior: 'smooth' });
        });
    }
});
</script>

<?php wp_footer(); ?>
</body>
</html>
