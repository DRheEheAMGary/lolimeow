<?php
/**
 * @link https://www.boxmoe.com
 * @package lolimeow
 */
//boxmoe.com===安全设置=阻止直接访问主题文件
if(!defined('ABSPATH')){echo'Look your sister';exit;}

// 获取说说分类：优先读取页面自定义字段 shuoshuo_cat，否则默认 slug 为 "shuoshuo"
$shuoshuo_cat_slug = get_post_meta(get_the_ID(), 'shuoshuo_cat', true) ?: 'shuoshuo';
$shuoshuo_cat = get_category_by_slug($shuoshuo_cat_slug);

$paged = (get_query_var('paged')) ? get_query_var('paged') : 1;
$posts_per_page = get_option('posts_per_page', 10);

$args = array(
    'category_name'  => $shuoshuo_cat_slug,
    'posts_per_page' => $posts_per_page,
    'paged'          => $paged,
    'orderby'        => 'date',
    'order'          => 'DESC',
    'ignore_sticky_posts' => 1,
);
$shuoshuo_query = new WP_Query($args);
?>
<div class="<?php echo boxmoe_layout_setting(); ?> blog-post">
    <?php if ($shuoshuo_cat) : ?>
    <div class="shuoshuo-header mb-5 text-center">
        <h2 class="shuoshuo-title">
            <i class="fa fa-clock-o"></i> <?php echo esc_html($shuoshuo_cat->name); ?>
        </h2>
        <?php if (!empty($shuoshuo_cat->description)) : ?>
        <p class="shuoshuo-desc text-muted"><?php echo esc_html($shuoshuo_cat->description); ?></p>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <?php if ($shuoshuo_query->have_posts()) : ?>
        <?php while ($shuoshuo_query->have_posts()) : $shuoshuo_query->the_post(); ?>
        <article class="post-list list-one row blog-border shuoshuo-post">
            <div class="post-list-avatar" style="flex:0 0 auto;width:auto;padding:15px 0 15px 10px;">
                <img src="<?php boxmoe_lazy_load_images(); ?>" 
                     data-src="<?php echo boxmoe_get_avatar_url(get_the_author_meta('ID'), 80); ?>" 
                     alt="avatar" class="avatar lazy">
            </div>
            <div class="post-list-content" style="padding:15px 10px 15px 5px;">
                <div class="post-meta-info" style="margin-bottom:8px;">
                    <span class="list-post-author" style="font-size:0.85rem;font-weight:600;">
                        <i class="fa fa-at"></i><?php the_author(); ?>
                        <span class="dot"></span>
                        <?php 
                        $post_time = get_the_time('U');
                        if ((current_time('timestamp') - $post_time) < 365 * 86400) {
                            echo get_the_time('m-d H:i');
                        } else {
                            echo get_the_time('Y-m-d');
                        }
                        ?>
                    </span>
                </div>
                <div class="shuoshuo-content" style="margin-bottom:10px;">
                    <?php the_content(); ?>
                </div>
                <div class="post-meta" style="margin:0;padding:8px 0 0;border-top:1px dashed #eee;gap:12px;display:flex;flex-wrap:wrap;align-items:center;">
                    <?php if(get_boxmoe('boxmoe_like_switch')): ?>
                    <span style="font-size:0.75rem;color:var(--bs-gray-700);">
                        <i class="fa fa-thumbs-up" style="margin-right:3px;"></i><?php echo getPostLikes(get_the_ID()); ?>
                    </span>
                    <?php endif; ?>
                    <a href="<?php the_permalink(); ?>#comments-container" style="font-size:0.75rem;color:var(--bs-gray-700);text-decoration:none;">
                        <i class="fa fa-comments-o" style="margin-right:3px;"></i><?php echo get_comments_number(); ?>
                    </a>
                    <span style="font-size:0.75rem;color:var(--bs-gray-700);">
                        <i class="fa fa-street-view" style="margin-right:3px;"></i><?php echo getPostViews(get_the_ID()); ?>
                    </span>
                    <a href="<?php the_permalink(); ?>" style="font-size:0.75rem;color:#D87CFF;text-decoration:none;margin-left:auto;">
                        详情 <i class="fa fa-arrow-right"></i>
                    </a>
                </div>
            </div>
        </article>
        <?php endwhile; ?>

    <!-- 分页 -->
    <div class="col-lg-12 col-md-12 pagenav">
        <?php 
        $paging_type = get_boxmoe('boxmoe_article_paging_type');
        if ($shuoshuo_query->max_num_pages > 1) {
            if ($paging_type == 'next') {
                echo '<nav class="pagination-next-prev"><ul class="pagination justify-content-center">';
                if ($paged > 1) {
                    echo '<li class="page-item"><a class="page-link" href="' . get_pagenum_link($paged - 1) . '"><i class="fa fa-arrow-left"></i> 上一页</a></li>';
                }
                if ($paged < $shuoshuo_query->max_num_pages) {
                    echo '<li class="page-item ms-2"><a class="page-link" href="' . get_pagenum_link($paged + 1) . '">下一页 <i class="fa fa-arrow-right"></i></a></li>';
                }
                echo '</ul></nav>';
            } else {
                echo '<nav class="d-flex justify-content-center"><ul class="pagination">';
                $total = $shuoshuo_query->max_num_pages;
                $current = max(1, $paged);
                if ($current > 1) {
                    echo '<li class="page-item"><a class="page-link" href="' . get_pagenum_link(1) . '"><i class="fa fa-angle-double-left"></i></a></li>';
                    echo '<li class="page-item"><a class="page-link" href="' . get_pagenum_link($current - 1) . '"><i class="fa fa-angle-left"></i></a></li>';
                }
                $start = max(1, $current - 2);
                $end = min($total, $current + 2);
                if ($start > 1) {
                    echo '<li class="page-item"><a class="page-link" href="' . get_pagenum_link(1) . '">1</a></li>';
                    if ($start > 2) echo '<li class="page-item"><span class="page-link">···</span></li>';
                }
                for ($i = $start; $i <= $end; $i++) {
                    if ($i == $current) {
                        echo '<li class="page-item active"><a class="page-link" href="#">' . $i . '</a></li>';
                    } else {
                        echo '<li class="page-item"><a class="page-link" href="' . get_pagenum_link($i) . '">' . $i . '</a></li>';
                    }
                }
                if ($end < $total) {
                    if ($end < $total - 1) echo '<li class="page-item"><span class="page-link">···</span></li>';
                    echo '<li class="page-item"><a class="page-link" href="' . get_pagenum_link($total) . '">' . $total . '</a></li>';
                }
                if ($current < $total) {
                    echo '<li class="page-item"><a class="page-link" href="' . get_pagenum_link($current + 1) . '"><i class="fa fa-angle-right"></i></a></li>';
                    echo '<li class="page-item"><a class="page-link" href="' . get_pagenum_link($total) . '"><i class="fa fa-angle-double-right"></i></a></li>';
                }
                echo '</ul></nav>';
            }
        }
        ?>
    </div>

    <?php else : ?>
    <div class="text-center py-7">
        <div class="mb-4">
            <i class="fa fa-pencil-square-o" style="font-size:4rem;color:#ccc;"></i>
        </div>
        <p class="text-muted">还没有说说，快去发布第一条吧～</p>
        <?php if (is_user_logged_in()) : ?>
        <a href="<?php echo admin_url('post-new.php'); ?>" class="btn btn-primary mt-3">
            <i class="fa fa-plus"></i> 发布说说
        </a>
        <?php endif; ?>
    </div>
    <?php endif; wp_reset_postdata(); ?>
</div>

