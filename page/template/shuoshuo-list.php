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
    <div class="shuoshuo-timeline">
        <div class="timeline-line"></div>
        <?php 
        $index = 0;
        while ($shuoshuo_query->have_posts()) : $shuoshuo_query->the_post(); 
            $index++;
        ?>
        <div class="timeline-item <?php echo ($index % 2 == 1) ? 'timeline-left' : 'timeline-right'; ?> fadein-bottom">
            <div class="timeline-dot">
                <div class="dot-inner"></div>
            </div>
            <div class="timeline-card <?php echo boxmoe_border_setting(); ?>">
                <!-- 头部：头像 + 作者 + 时间 -->
                <div class="timeline-card-header">
                    <div class="timeline-avatar">
                        <img src="<?php echo boxmoe_lazy_load_images(); ?>" 
                             data-src="<?php echo boxmoe_get_avatar_url(get_the_author_meta('ID'), 80); ?>" 
                             alt="avatar" class="avatar lazy">
                    </div>
                    <div class="timeline-meta">
                        <span class="timeline-author">
                            <i class="fa fa-at"></i><?php the_author(); ?>
                        </span>
                        <span class="timeline-time">
                            <i class="fa fa-clock-o"></i>
                            <?php 
                            // 如果是一年内显示月日时分，否则显示完整日期
                            $post_time = get_the_time('U');
                            $current_time = current_time('timestamp');
                            if (($current_time - $post_time) < 365 * 86400) {
                                echo get_the_time('m-d H:i');
                            } else {
                                echo get_the_time('Y-m-d H:i');
                            }
                            ?>
                        </span>
                    </div>
                </div>

                <!-- 正文内容 -->
                <div class="timeline-card-body">
                    <div class="shuoshuo-content">
                        <?php 
                        // 移除标题标签，说说以内容为主
                        the_content(); 
                        ?>
                    </div>
                </div>

                <!-- 底部操作栏 -->
                <div class="timeline-card-footer">
                    <div class="shuoshuo-actions">
                        <?php if(get_boxmoe('boxmoe_like_switch')): ?>
                        <button class="action-btn like-btn" title="点赞" data-post-id="<?php the_ID(); ?>">
                            <i class="fa fa-thumbs-up"></i>
                            <span class="like-count"><?php echo getPostLikes(get_the_ID()); ?></span>
                        </button>
                        <?php endif; ?>
                        <a href="<?php the_permalink(); ?>#comments-container" class="action-btn comment-btn" title="评论">
                            <i class="fa fa-comments-o"></i>
                            <span><?php echo get_comments_number(); ?></span>
                        </a>
                        <?php if(is_user_logged_in()): ?>
                        <button class="action-btn favorite-btn <?php echo isPostFavorited(get_the_ID()) ? 'favorited' : ''; ?>" title="收藏" data-post-id="<?php the_ID(); ?>">
                            <i class="fa fa-star"></i>
                            <span class="favorite-text"><?php echo isPostFavorited(get_the_ID()) ? '已收藏' : '收藏'; ?></span>
                        </button>
                        <?php endif; ?>
                        <a href="<?php the_permalink(); ?>" class="action-btn view-btn" title="查看详情">
                            <i class="fa fa-arrow-right"></i>
                            <span>详情</span>
                        </a>
                    </div>
                    <div class="shuoshuo-tags">
                        <?php 
                        $tags = get_the_tags();
                        if ($tags) {
                            foreach ($tags as $tag) {
                                echo '<a href="' . esc_url(get_tag_link($tag->term_id)) . '" class="tag-badge">#' . esc_html($tag->name) . '</a> ';
                            }
                        }
                        ?>
                    </div>
                </div>
            </div>
        </div>
        <?php endwhile; ?>
    </div>

    <!-- 分页 -->
    <div class="col-lg-12 col-md-12 pagenav">
        <?php 
        // 使用主题自带分页
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
                // 数字分页
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
    <div class="shuoshuo-empty text-center py-7">
        <div class="empty-icon mb-4">
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
