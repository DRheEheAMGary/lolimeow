<?php
/**
 * @link https://www.boxmoe.com
 * @package lolimeow
 */
//boxmoe.com===安全设置=阻止直接访问主题文件
if(!defined('ABSPATH')){echo'Look your sister';exit;}
?>
<div class="<?php echo boxmoe_layout_setting(); ?> blog-post">
    <?php if (have_posts()) : ?>
        <?php while (have_posts()) : the_post(); 
            $is_shuoshuo = has_category('shuoshuo');
        ?>
        <?php if ($is_shuoshuo): ?>
        <!-- ====== 说说卡片：紧凑模式 ====== -->
        <article class="post-list blog-border shuoshuo-post">
            <div class="shuoshuo-avatar-wrap">
                <img src="<?php echo boxmoe_lazy_load_images(); ?>" 
                     data-src="<?php echo boxmoe_get_avatar_url(get_the_author_meta('ID'), 80); ?>" 
                     alt="avatar" class="shuoshuo-avatar lazy">
            </div>
            <div class="shuoshuo-card-body">
                <div class="shuoshuo-meta-top">
                    <span class="shuoshuo-author">
                        <i class="fa fa-at"></i><?php the_author(); ?>
                    </span>
                    <span class="shuoshuo-dot"></span>
                    <span class="shuoshuo-time"><?php echo get_the_time('m-d H:i'); ?></span>
                </div>
                <div class="shuoshuo-content">
                    <?php the_content(); ?>
                </div>
                <div class="shuoshuo-actions">
                    <span class="shuoshuo-stat">
                        <i class="fa fa-street-view"></i><?php echo getPostViews(get_the_ID()); ?>
                    </span>
                    <a href="<?php the_permalink(); ?>#comments-container" class="shuoshuo-stat">
                        <i class="fa fa-comments-o"></i><?php echo get_comments_number(); ?>
                    </a>
                    <a href="<?php the_permalink(); ?>" class="shuoshuo-link">
                        详情 <i class="fa fa-arrow-right"></i>
                    </a>
                </div>
            </div>
        </article>
                <?php else: ?>
        <!-- ====== 普通文章：缩略图 + 摘要 ====== -->
        <article class="post-list list-one row blog-border">
            <div class="post-list-img">
                <figure class="mb-4 mb-lg-0 zoom-img">
                    <a <?php echo boxmoe_article_new_window(); ?> href="<?php the_permalink(); ?>" title="<?php the_title(); ?>">
                        <img src="<?php echo boxmoe_lazy_load_images(); ?>" 
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
                        <img src="<?php echo boxmoe_lazy_load_images(); ?>" 
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
        <?php endif; ?>
        <?php endwhile; ?>

    <!-- 分页 -->
    <div class="col-lg-12 col-md-12 pagenav">
        <?php boxmoe_pagination(); ?>
    </div>

    <?php else : ?>
    <div class="text-center py-7">
        <div class="mb-4">
            <i class="fa fa-inbox" style="font-size:4rem;color:#ccc;"></i>
        </div>
        <p class="text-muted">还没有内容，敬请期待～</p>
    </div>
    <?php endif; ?>
</div>
