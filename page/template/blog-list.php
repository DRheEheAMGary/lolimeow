<?php
/**
 * @link https://www.boxmoe.com
 * @package lolimeow
 */
//boxmoe.com===安全设置=阻止直接访问主题文件
if(!defined('ABSPATH')){echo'Look your sister';exit;}

// 说说分类 slug（与 shuoshuo-list 共用同一标识）
$shuoshuo_slug = 'shuoshuo';
?>
<div class="<?php echo boxmoe_layout_setting(); ?> blog-post">
    <?php if (have_posts()) : ?>
    <div class="blog-timeline">
        <div class="timeline-line"></div>
        <?php 
        $index = 0;
        while (have_posts()) : the_post(); 
            $index++;
            // 判断是否为说说类型
            $is_shuoshuo = has_category($shuoshuo_slug);
        ?>
        <div class="timeline-item <?php echo ($index % 2 == 1) ? 'timeline-left' : 'timeline-right'; ?>">
            <!-- 时间指示器 -->
            <div class="timeline-dot">
                <div class="dot-inner"></div>
            </div>
            <div class="timeline-card <?php echo boxmoe_border_setting(); ?> <?php echo $is_shuoshuo ? 'shuoshuo-card' : ''; ?>">
                
                <!-- 头部：头像 + 作者 + 时间 + 分类 -->
                <div class="timeline-card-header">
                    <div class="timeline-avatar">
                        <img src="<?php boxmoe_lazy_load_images(); ?>" 
                             data-src="<?php echo boxmoe_get_avatar_url(get_the_author_meta('ID'), 80); ?>" 
                             alt="avatar" class="avatar lazy">
                    </div>
                    <div class="timeline-meta">
                        <div class="timeline-meta-top">
                            <span class="timeline-author">
                                <i class="fa fa-at"></i><?php the_author(); ?>
                            </span>
                            <span class="timeline-time">
                                <i class="fa fa-clock-o"></i>
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
                        <div class="timeline-cats">
                            <?php 
                            $categories = get_the_category();
                            if (!empty($categories)) {
                                foreach ($categories as $cat) {
                                    echo '<a href="' . esc_url(get_category_link($cat->term_id)) . '" class="timeline-cat-badge">' . esc_html($cat->name) . '</a> ';
                                }
                            }
                            ?>
                        </div>
                    </div>
                </div>

                <!-- 卡片正文 -->
                <div class="timeline-card-body">
                    <?php if ($is_shuoshuo): ?>
                    <!-- ====== 说说模式：直接展示内容 ====== -->
                    <div class="shuoshuo-content">
                        <?php the_content(); ?>
                    </div>
                    <?php else: ?>
                    <!-- ====== 文章模式：缩略图 + 标题 + 摘要 ====== -->
                    <?php if (has_post_thumbnail() || boxmoe_article_thumbnail_src()): ?>
                    <div class="timeline-thumb">
                        <a <?php echo boxmoe_article_new_window(); ?> href="<?php the_permalink(); ?>" title="<?php the_title(); ?>">
                            <img src="<?php boxmoe_lazy_load_images(); ?>" 
                                 data-src="<?php echo boxmoe_article_thumbnail_src(); ?>" 
                                 alt="<?php the_title(); ?>" class="img-fluid rounded-3 lazy">
                        </a>
                    </div>
                    <?php endif; ?>
                    <h3 class="timeline-post-title">
                        <a href="<?php the_permalink(); ?>" class="text-reset" title="<?php the_title(); ?>"><?php the_title(); ?></a>
                    </h3>
                    <p class="timeline-excerpt"><?php echo _get_excerpt(80); ?></p>
                    <?php endif; ?>
                </div>

                <!-- 底部操作栏 -->
                <div class="timeline-card-footer">
                    <div class="timeline-actions">
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
                        <span class="action-btn view-stat" title="阅读">
                            <i class="fa fa-street-view"></i>
                            <span><?php echo getPostViews(get_the_ID()); ?></span>
                        </span>
                        <?php if(is_user_logged_in()): ?>
                        <button class="action-btn favorite-btn <?php echo isPostFavorited(get_the_ID()) ? 'favorited' : ''; ?>" title="收藏" data-post-id="<?php the_ID(); ?>">
                            <i class="fa fa-star"></i>
                            <span class="favorite-text"><?php echo isPostFavorited(get_the_ID()) ? '已收藏' : '收藏'; ?></span>
                        </button>
                        <?php endif; ?>
                        <a href="<?php the_permalink(); ?>" class="action-btn read-btn" title="阅读全文">
                            <i class="fa fa-arrow-right"></i>
                            <span>阅读</span>
                        </a>
                    </div>
                    <?php 
                    $tags = get_the_tags();
                    if ($tags): ?>
                    <div class="timeline-tags">
                        <?php 
                        foreach ($tags as $tag) {
                            echo '<a href="' . esc_url(get_tag_link($tag->term_id)) . '" class="tag-badge">#' . esc_html($tag->name) . '</a> ';
                        }
                        ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endwhile; ?>
    </div>

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
