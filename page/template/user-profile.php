<?php
/**
 * 用户主页内容模板
 * 信息卡片 + Tab 切换（文章 / 说说 / 评论）
 * URL hash 驱动切换，无页面刷新
 *
 * @link https://www.boxmoe.com
 * @package lolimeow
 */
if (!defined('ABSPATH')) { echo 'Look your sister'; exit; }

global $profile_user;
if (!$profile_user) return;

$user_id   = $profile_user->ID;
$stats     = boxmoe_get_user_stats($user_id);
$user_url  = boxmoe_get_user_profile_url($user_id);

// 仅用于服务端确定默认 active tab（给 noscript 回退用），JS 会覆盖
$hash_tab = 'posts';
// 分页
$paged = max(1, get_query_var('paged', 1));
$per_page = 10;

// 头像编辑权限 + 封禁遮盖
$can_edit_avatar = (get_current_user_id() === $user_id || current_user_can('manage_options'));
$profile_banned = (function_exists('boxmoe_is_user_banned') && boxmoe_is_user_banned($user_id));
$show_masked = ($profile_banned && !current_user_can('manage_options'));

// 预查询所有 tab 数据（封禁用户对非管理员返回空）
if ($show_masked) {
    $posts_query    = new WP_Query();
    $shuoshuo_query = new WP_Query();
    $comments       = array();
    $comment_total  = 0;
} else {
    $posts_query    = boxmoe_get_user_posts($user_id, $paged, $per_page);
    $shuoshuo_query = boxmoe_get_user_shuoshuo($user_id, $paged, $per_page);
    $comments       = boxmoe_get_user_comments($user_id, $paged, $per_page);
    $comment_total  = boxmoe_get_user_comment_count($user_id);
}
?>

<div class="<?php echo boxmoe_layout_setting(); ?> blog-post user-profile-page">

    <style>
        .profile-tab-pane { display:none; }
        .profile-tab-pane.active { display:block; animation: tabFadeIn 0.25s ease; }
        @keyframes tabFadeIn { from{opacity:0;transform:translateY(6px);} to{opacity:1;transform:translateY(0);} }
        .profile-tab-pane .row { margin-left:0;margin-right:0; }
        .user-profile-page .post-single { background:transparent !important; padding:20px 30px; }
        .profile-avatar-wrap { position:relative; display:inline-block; border-radius:50%; }
        .profile-avatar-wrap img { display:block; border-radius:50%; }
        .profile-avatar-wrap .avatar-overlay { position:absolute; top:0;left:0;width:100%;height:100%; border-radius:50%; background:rgba(0,0,0,0.4); display:flex; align-items:center; justify-content:center; opacity:0; pointer-events:none; }
        .profile-avatar-wrap .avatar-overlay i { color:#fff; font-size:2rem; }
        .about-rendered .markdown-body { line-height:1.8; font-size:0.95rem; color:var(--bs-body-color); }
        .about-rendered .markdown-body h1,.about-rendered .markdown-body h2,.about-rendered .markdown-body h3 { margin-top:1em; margin-bottom:0.5em; }
        .about-rendered .markdown-body p { margin-bottom:0.8em; }
        .about-rendered .markdown-body img { max-width:100%; border-radius:8px; }
        .about-rendered .markdown-body code { background:var(--bs-light); padding:2px 6px; border-radius:4px; font-size:0.9em; }
        .about-rendered .markdown-body pre { background:var(--bs-light); padding:16px; border-radius:8px; overflow-x:auto; }
        .about-rendered .markdown-body blockquote { background:rgba(var(--bs-warning-rgb, 249, 168, 37), 0.1);border-left:4px solid var(--bs-warning,#f9a825);padding:0.8em 1em;margin:1em 0;color:var(--bs-body-color); }
        .about-rendered .markdown-body table { border-collapse:collapse; }
        .about-rendered .markdown-body th,.about-rendered .markdown-body td { border:1px solid var(--bs-border-color); padding:8px 12px; }
        .about-rendered .markdown-body a { color:var(--bs-primary); }
        .about-rendered .markdown-body ul,.about-rendered .markdown-body ol { padding-left:1.5em; }
        .editor-toolbar { border-radius:6px 6px 0 0 !important; display:flex !important; flex-wrap:wrap !important; width:auto !important; }
        .editor-toolbar::after { display:none !important; }
        .editor-toolbar button { display:inline-block !important; width:auto !important; min-width:30px !important; }
        .editor-toolbar i,.editor-toolbar svg { display:inline-block !important; }
        .CodeMirror { border-radius:0 0 6px 6px !important; max-width:100% !important; }
        .editor-statusbar { display:none; }
        .EasyMDEContainer { max-width:100% !important; }
        .profile-social-row { padding:12px 0; }
        .profile-social-item { display:inline-flex;align-items:center;justify-content:center;gap:0;min-width:36px;height:36px;padding:0;border-radius:50%;background:var(--bs-light,#f0f0f0);cursor:pointer;transition:all 0.25s ease;font-size:0.85rem;overflow:hidden;white-space:nowrap;user-select:none; }
        .profile-social-item i { font-size:1.1rem;color:var(--bs-primary);transition:color 0.25s;flex-shrink:0; }
        .profile-social-text { max-width:0;opacity:0;overflow:hidden;font-weight:500; }
        .profile-social-item:hover { padding:8px 14px;gap:6px;border-radius:20px; }
        .profile-social-item:hover .profile-social-text { opacity:1; }
        .profile-social-item:active { transform:scale(0.95); }
    </style>

    <!-- ====== 信息卡片 ====== -->
    <div class="post-single">
        <div class="row align-items-center">
            <div class="col-auto">
                <?php if ($can_edit_avatar): ?>
                <div class="profile-avatar-wrap" id="profile-avatar-wrap">
                    <input type="file" id="profile-avatar-input" accept=".jpg,.jpeg,.png,.gif,.webp" style="display:none;">
                <?php endif; ?>
                    <img src="<?php echo boxmoe_lazy_load_images(); ?>"
                         data-src="<?php echo $show_masked ? get_avatar_url(0) : boxmoe_get_avatar_url($user_id, 120); ?>"
                         alt="<?php echo esc_attr($profile_user->display_name); ?>"
                         class="rounded-circle lazy"
                         style="width:100px;height:100px;object-fit:cover;"
                         id="profile-avatar-img">
                    <?php if ($can_edit_avatar): ?>
                    <div class="avatar-overlay">
                        <i class="fa fa-camera"></i>
                    </div>
                </div>
                    <?php endif; ?>
            </div>
            <div class="col">
                <h2 class="mb-1" style="font-size:1.5rem;">
                    <?php if ($show_masked): ?>封禁用户<?php else: ?><?php echo esc_html($profile_user->display_name); ?><?php endif; ?>
                    <?php if ($show_masked): ?><?php else: ?><?php echo boxmoe_get_user_tag_badge($user_id); ?><?php endif; ?>
                    <?php if (!empty($profile_user->user_url)): ?>
                    <a href="<?php echo esc_url($profile_user->user_url); ?>" target="_blank"
                       class="text-muted ms-2" title="个人网站" style="font-size:0.9rem;">
                        <i class="fa fa-link"></i>
                    </a>
                    <?php endif; ?>
                </h2>
                <p class="text-muted mb-2" style="font-size:0.85rem;">@<?php echo esc_html($profile_user->user_nicename); ?></p>
                <div id="profile-desc-wrap" style="position:relative;">
                    <span id="profile-desc-text" class="<?php echo empty($profile_user->description) ? 'text-muted' : 'text-secondary'; ?>" style="font-size:0.9rem;<?php echo $can_edit_avatar ? 'cursor:pointer;' : ''; ?>">
                        <?php echo !empty($profile_user->description) ? esc_html($profile_user->description) : ($can_edit_avatar ? '添加签名...' : ''); ?>
                    </span>
                    <?php if ($can_edit_avatar): ?>
                    <span id="profile-desc-edit-icon" style="display:none;margin-left:4px;color:var(--bs-primary);cursor:pointer;font-size:0.8rem;"><i class="fa fa-pencil"></i></span>
                    <div id="profile-desc-edit" style="display:none;margin-top:4px;width:100%;">
                        <div style="display:flex;gap:6px;align-items:center;flex-wrap:nowrap;">
                            <input type="text" id="profile-desc-input" class="form-control form-control-sm" value="<?php echo esc_attr($profile_user->description); ?>" placeholder="写一句签名..." style="flex:1;">
                            <button class="btn btn-primary btn-sm" id="profile-desc-save" style="white-space:nowrap;flex-shrink:0;">保存</button>
                            <button class="btn btn-outline-secondary btn-sm" id="profile-desc-cancel" style="white-space:nowrap;flex-shrink:0;">取消</button>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
                <?php if (get_current_user_id() !== $user_id && is_user_logged_in() && function_exists('boxmoe_can_be_followed') && boxmoe_can_be_followed($user_id) && !(function_exists('boxmoe_is_user_banned') && boxmoe_is_user_banned($user_id))): ?>
                <div class="mt-2">
                    <?php
                    $cu = get_current_user_id();
                    $is_following = function_exists('boxmoe_is_following') && boxmoe_is_following($cu, $user_id);
                    $is_followed_back = function_exists('boxmoe_is_following') && boxmoe_is_following($user_id, $cu);
                    if ($is_following && $is_followed_back): $btn_class = 'btn-outline-secondary'; $btn_text = '<i class="fa fa-exchange"></i> 已互关';
                    elseif ($is_following): $btn_class = 'btn-outline-secondary'; $btn_text = '<i class="fa fa-check"></i> 已关注';
                    elseif ($is_followed_back): $btn_class = 'btn-primary'; $btn_text = '<i class="fa fa-plus"></i> 互关';
                    else: $btn_class = 'btn-primary'; $btn_text = '<i class="fa fa-plus"></i> 关注';
                    endif; ?>
                    <button id="follow-btn" class="btn btn-sm <?php echo $btn_class; ?>"
                            data-uid="<?php echo $user_id; ?>"
                            data-following="<?php echo $is_following ? '1' : '0'; ?>"
                            data-followed-back="<?php echo $is_followed_back ? '1' : '0'; ?>">
                        <?php echo $btn_text; ?>
                    </button>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <div class="row mt-3 gx-1">
            <div class="text-center" style="flex:1 1 0;min-width:0;">
                <div class="fw-bold fs-5"><?php echo $stats['post_count']; ?></div>
                <small class="text-muted">文章</small>
            </div>
            <div class="text-center" style="flex:1 1 0;min-width:0;">
                <div class="fw-bold fs-5"><?php echo $stats['shuoshuo_count']; ?></div>
                <small class="text-muted">说说</small>
            </div>
            <div class="text-center" style="flex:1 1 0;min-width:0;">
                <div class="fw-bold fs-5"><?php echo $stats['comment_count']; ?></div>
                <small class="text-muted">评论</small>
            </div>
            <?php $fc = function_exists('boxmoe_get_following') ? count(boxmoe_get_following($user_id)) : 0; ?>
            <?php $frc = function_exists('boxmoe_get_followers') ? count(boxmoe_get_followers($user_id)) : 0; ?>
            <div class="text-center" style="flex:1 1 0;min-width:0;">
                <div class="fw-bold fs-5"><?php echo $fc; ?></div>
                <small class="text-muted">关注</small>
            </div>
            <div class="text-center" style="flex:1 1 0;min-width:0;">
                <div class="fw-bold fs-5" id="follower-count"><?php echo $frc; ?></div>
                <small class="text-muted">粉丝</small>
            </div>
        </div>

    <?php if (current_user_can('manage_options') && function_exists('boxmoe_is_user_banned') && ($ban = boxmoe_is_user_banned($user_id))): ?>
    <div class="alert alert-danger py-2 px-3 my-2" style="border-radius:8px;font-size:0.85rem;">
        <i class="fa fa-exclamation-triangle"></i>
        <strong>该用户已被封禁</strong>
        <?php echo $ban === '永久' ? '(永久)' : '至 ' . esc_html($ban); ?>
        <?php $r = get_user_meta($user_id, 'boxmoe_ban_reason', true); if ($r): ?>
        — 理由：<?php echo esc_html($r); ?>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- ====== 社交图标行 ====== -->
    <?php
    $social_links = get_user_meta($user_id, 'user_social_links', true) ?: array();
    $sl_icons = array(
        array('key' => 'email',  'icon' => 'fa-envelope', 'label' => '邮箱',   'action' => 'copy',  'prefix' => 'mailto:'),
        array('key' => 'github', 'icon' => 'fa-github',   'label' => 'GitHub', 'action' => 'link',  'prefix' => 'https://github.com/'),
        array('key' => 'qq',     'icon' => 'fa-qq',       'label' => 'QQ',     'action' => 'copy',  'prefix' => ''),
        array('key' => 'wechat', 'icon' => 'fa-wechat',   'label' => '微信',   'action' => 'copy',  'prefix' => ''),
        array('key' => 'weibo',  'icon' => 'fa-weibo',    'label' => '微博',   'action' => 'copy',  'prefix' => ''),
    );
    $has_visible = false;
    foreach ($sl_icons as $s) {
        $value = isset($social_links[$s['key']]) ? $social_links[$s['key']] : '';
        $show  = isset($social_links[$s['key'].'_show']) ? $social_links[$s['key'].'_show'] : '1';
        if (!empty($value) && $show === '1') { $has_visible = true; break; }
    }
    // 辅助函数：构建展示用链接
    function _sl_url($prefix, $value) {
        if (empty($value)) return '';
        if (preg_match('#^https?://#i', $value)) return $value;
        return $prefix . $value;
    }
    if ($has_visible):
    ?>
    <div class="profile-social-row d-flex flex-wrap align-items-center gap-3 mb-3 px-3">
        <?php foreach ($sl_icons as $s):
            $value = isset($social_links[$s['key']]) ? $social_links[$s['key']] : '';
            $show  = isset($social_links[$s['key'].'_show']) ? $social_links[$s['key'].'_show'] : '1';
            if (empty($value) || $show !== '1') continue;
        ?>
        <span class="profile-social-item"
              data-copy="<?php echo $s['action'] === 'copy' ? esc_attr($value) : ''; ?>"
              data-href="<?php echo $s['action'] === 'link' ? esc_url(_sl_url($s['prefix'], $value)) : ''; ?>"
              data-action="<?php echo $s['action']; ?>"
              data-label="<?php echo esc_attr($s['label']); ?>">
            <i class="fa <?php echo $s['icon']; ?>"></i>
            <span class="profile-social-text"><?php echo esc_html($value); ?></span>
        </span>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- ====== Tab 导航（hash 驱动，无刷新） ====== -->
    <div class="single-category mb-3" style="display:flex;gap:10px;flex-wrap:wrap;">
        <a href="<?php echo esc_url($user_url); ?>#about"
           class="tag-cloud profile-tab-link" data-tab="about">
            <i class="fa fa-user"></i>简介
        </a>
        <a href="<?php echo esc_url($user_url); ?>#posts"
           class="tag-cloud profile-tab-link" data-tab="posts">
            <i class="fa fa-file-text-o"></i>文章
        </a>
        <a href="<?php echo esc_url($user_url); ?>#shuoshuo"
           class="tag-cloud profile-tab-link" data-tab="shuoshuo">
            <i class="fa fa-clock-o"></i>说说
        </a>
        <a href="<?php echo esc_url($user_url); ?>#comments"
           class="tag-cloud profile-tab-link" data-tab="comments">
            <i class="fa fa-comments-o"></i>评论
        </a>
        <a href="<?php echo esc_url($user_url); ?>#favorites"
           class="tag-cloud profile-tab-link" data-tab="favorites">
            <i class="fa fa-heart"></i>收藏
        </a>
        <a href="<?php echo esc_url($user_url); ?>#following"
           class="tag-cloud profile-tab-link" data-tab="following">
            <i class="fa fa-users"></i>关注
        </a>
        <a href="<?php echo esc_url($user_url); ?>#followers"
           class="tag-cloud profile-tab-link" data-tab="followers">
            <i class="fa fa-user-plus"></i>粉丝
        </a>
    </div>

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/easymde/dist/easymde.min.css">

    <!-- ====== 简介（默认 Tab） ====== -->
    <div class="profile-tab-pane active" data-tab="about" id="profile-tab-about">
        <?php if (!boxmoe_can_view_content($user_id, 'about_visibility')): ?>
            <div class="text-center py-7">
                <i class="fa fa-lock" style="font-size:4rem;color:#ccc;"></i>
                <p class="mt-3 text-muted">抱歉，根据隐私设置，该用户拒绝显示此页面</p>
            </div>
        <?php else: ?>
        <?php $about_content = $show_masked ? '' : get_user_meta($user_id, 'user_about', true); ?>
                <?php if (!empty(trim($about_content))): ?>
                <script type="text/plain" id="about-content-data"><?php echo str_replace('</', '<\/', $about_content); ?></script>
                <?php endif; ?>
                <div class="about-view-mode" id="about-view">
                    <?php if (!empty(trim($about_content))): ?>
                    <div class="about-rendered" style="min-height:100px;">
                        <div class="markdown-body"></div>
                    </div>
                    <?php else: ?>
                    <div class="text-center py-5">
                        <i class="fa fa-pencil" style="font-size:4rem;color:#ccc;"></i>
                        <p class="mt-3 text-muted">暂无简介</p>
                    </div>
                    <?php endif; ?>
                    <?php if ($can_edit_avatar): ?>
                    <button class="btn btn-outline-primary btn-sm mt-3" id="about-edit-btn">
                        <i class="fa fa-edit"></i> 编辑简介
                    </button>
                    <?php endif; ?>
                </div>
                <?php if ($can_edit_avatar): ?>
                <div class="about-edit-mode" id="about-edit" style="display:none;">
                    <textarea id="about-editor"><?php echo esc_textarea($about_content); ?></textarea>
                    <div style="margin-top:10px;display:flex;gap:10px;align-items:center;flex-wrap:wrap;">
                        <button class="btn btn-outline-secondary btn-sm" id="about-emoji-btn" type="button">
                            <i class="fa fa-smile-o"></i> 表情
                        </button>
                        <button class="btn btn-primary btn-sm" id="about-save-btn" type="button">
                            <i class="fa fa-check"></i> 保存
                        </button>
                        <button class="btn btn-outline-secondary btn-sm" id="about-cancel-btn" type="button">
                            取消
                        </button>
                    </div>
                    <div class="emoji-panel" id="about-emoji-panel" style="display:none;margin-top:8px;border:1px solid var(--bs-border-color);border-radius:8px;padding:10px;background:var(--bs-body-bg);max-height:260px;overflow-y:auto;">
                        <div class="emoji-tabs" style="display:flex;gap:8px;margin-bottom:8px;overflow-x:auto;white-space:nowrap;border-bottom:1px solid var(--bs-border-color);padding-bottom:8px;">
                            <span data-tab="emoji" class="active" style="cursor:pointer;padding:2px 10px;border-radius:4px;font-size:13px;background:var(--bs-primary);color:#fff;">表情</span>
                            <span data-tab="custom" style="cursor:pointer;padding:2px 10px;border-radius:4px;font-size:13px;color:var(--bs-body-color);">颜文字</span>
                        </div>
                        <div class="emoji-content" style="display:flex;flex-wrap:wrap;gap:4px;"></div>
                    </div>
                </div>
                <?php endif; ?>
        <?php endif; ?>
    </div>

    <!-- ====== 文章列表 ====== -->
    <div class="profile-tab-pane" data-tab="posts" id="profile-tab-posts">
        <?php if (!boxmoe_can_view_content($user_id, 'posts_visibility')): ?>
            <div class="text-center py-7">
                <i class="fa fa-lock" style="font-size:4rem;color:#ccc;"></i>
                <p class="mt-3 text-muted">抱歉，根据隐私设置，该用户拒绝显示此页面</p>
            </div>
        <?php elseif ($posts_query->have_posts()):
            while ($posts_query->have_posts()): $posts_query->the_post(); ?>
            <article class="post-list list-one row <?php echo boxmoe_border_setting(); ?>">
                <div class="post-list-img">
                    <figure class="mb-4 mb-lg-0 zoom-img">
                        <a href="<?php the_permalink(); ?>" title="<?php the_title(); ?>">
                            <img src="<?php echo boxmoe_lazy_load_images(); ?>"
                                 data-src="<?php echo boxmoe_article_thumbnail_src(); ?>"
                                 alt="<?php the_title(); ?>" class="img-fluid rounded-3 lazy">
                        </a>
                    </figure>
                </div>
                <div class="post-list-content">
                    <div class="category"><div class="tags">
                        <?php
                        $categories = get_the_category();
                        if (!empty($categories)) {
                            foreach ($categories as $cat) {
                                $cat_color = get_term_meta($cat->term_id, 'boxmoe_cat_color', true) ?: '#999';
                                echo '<a href="' . esc_url(get_category_link($cat->term_id)) . '" rel="category tag">';
                                echo '<i class="tagfa fa fa-dot-circle-o" style="color:' . esc_attr($cat_color) . '"></i>' . esc_html($cat->name) . '</a> ';
                            }
                        }
                        ?>
                    </div></div>
                    <div class="mt-2 mb-2">
                        <h3 class="post-title h4">
                            <a href="<?php the_permalink(); ?>" title="<?php the_title(); ?>" class="text-reset"><?php the_title(); ?></a>
                        </h3>
                        <p class="post-content"><?php echo _get_excerpt(80); ?></p>
                    </div>
                    <div class="post-meta align-items-center">
                        <div class="post-list-avatar">
                            <img src="<?php echo boxmoe_lazy_load_images(); ?>"
                                 data-src="<?php echo boxmoe_get_avatar_url($user_id, 80); ?>"
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
                                <i class="fa fa-at"></i><?php the_author(); ?><?php echo boxmoe_get_user_tag_badge(get_the_author_meta('ID')); ?>
                                <span class="dot"></span><?php echo get_the_time('Y-m-d'); ?>
                            </span>
                        </div>
                    </div>
                </div>
            </article>
            <?php endwhile;
            wp_reset_postdata();
            $total_pages = $posts_query->max_num_pages;
            if ($total_pages > 1): ?>
            <div class="col-lg-12 col-md-12 pagenav">
                <nav class="d-flex justify-content-center"><ul class="pagination">
                <?php
                $current = max(1, $paged);
                for ($i = 1; $i <= $total_pages; $i++):
                    $link = ($i == 1) ? $user_url . '#posts' : add_query_arg('paged', $i, $user_url) . '#posts'; ?>
                    <li class="page-item<?php echo $i == $current ? ' active' : ''; ?>">
                        <a class="page-link" href="<?php echo esc_url($link); ?>"><?php echo $i; ?></a>
                    </li>
                <?php endfor; ?>
                </ul></nav>
            </div>
            <?php endif;
        else: ?>
            <div class="text-center py-7">
                <i class="fa fa-inbox" style="font-size:4rem;color:#ccc;"></i>
                <p class="mt-3 text-muted">暂无文章</p>
            </div>
        <?php endif; ?>
    </div>

    <!-- ====== 说说列表 ====== -->
    <div class="profile-tab-pane" data-tab="shuoshuo" id="profile-tab-shuoshuo">
        <?php if (!boxmoe_can_view_content($user_id, 'posts_visibility')): ?>
            <div class="text-center py-7">
                <i class="fa fa-lock" style="font-size:4rem;color:#ccc;"></i>
                <p class="mt-3 text-muted">抱歉，根据隐私设置，该用户拒绝显示此页面</p>
            </div>
        <?php elseif ($shuoshuo_query->have_posts()):
            while ($shuoshuo_query->have_posts()): $shuoshuo_query->the_post(); ?>
            <article class="shuoshuo-post <?php echo boxmoe_border_setting(); ?>">
                <div class="shuoshuo-avatar-wrap">
                    <img src="<?php echo boxmoe_lazy_load_images(); ?>"
                         data-src="<?php echo boxmoe_get_avatar_url($user_id, 80); ?>"
                         alt="avatar" class="shuoshuo-avatar lazy">
                </div>
                <div class="shuoshuo-card-body">
                    <div class="shuoshuo-meta-top">
                        <span class="shuoshuo-author">
                            <i class="fa fa-at"></i><?php echo esc_html($profile_user->display_name); ?><?php echo boxmoe_get_user_tag_badge($user_id); ?>
                        </span>
                        <span class="shuoshuo-dot"></span>
                        <span class="shuoshuo-time"><?php echo get_the_time('m-d H:i'); ?></span>
                    </div>
                    <div class="shuoshuo-content">
                        <a href="<?php the_permalink(); ?>" style="color:inherit;text-decoration:none;">
                            <?php the_content(); ?>
                        </a>
                    </div>
                    <div class="shuoshuo-actions">
                        <span class="shuoshuo-stat">
                            <i class="fa fa-street-view"></i><?php echo getPostViews(get_the_ID()); ?>
                        </span>
                        <a href="<?php the_permalink(); ?>#comments-container" class="shuoshuo-stat">
                            <i class="fa fa-comments-o"></i><?php echo get_comments_number(); ?>
                        </a>
                    </div>
                </div>
            </article>
            <?php endwhile;
            wp_reset_postdata();
            $total_pages = $shuoshuo_query->max_num_pages;
            if ($total_pages > 1): ?>
            <div class="col-lg-12 col-md-12 pagenav">
                <nav class="d-flex justify-content-center"><ul class="pagination">
                <?php
                $current = max(1, $paged);
                for ($i = 1; $i <= $total_pages; $i++):
                    $link = ($i == 1) ? $user_url . '#shuoshuo' : add_query_arg('paged', $i, $user_url) . '#shuoshuo'; ?>
                    <li class="page-item<?php echo $i == $current ? ' active' : ''; ?>">
                        <a class="page-link" href="<?php echo esc_url($link); ?>"><?php echo $i; ?></a>
                    </li>
                <?php endfor; ?>
                </ul></nav>
            </div>
            <?php endif;
        else: ?>
            <div class="text-center py-7">
                <i class="fa fa-clock-o" style="font-size:4rem;color:#ccc;"></i>
                <p class="mt-3 text-muted">暂无说说</p>
            </div>
        <?php endif; ?>
    </div>

    <!-- ====== 评论列表 ====== -->
    <div class="profile-tab-pane" data-tab="comments" id="profile-tab-comments">
        <?php if (!boxmoe_can_view_content($user_id, 'comments_visibility')): ?>
            <div class="text-center py-7">
                <i class="fa fa-lock" style="font-size:4rem;color:#ccc;"></i>
                <p class="mt-3 text-muted">抱歉，根据隐私设置，该用户拒绝显示此页面</p>
            </div>
        <?php elseif (!empty($comments)):
            foreach ($comments as $comment):
                $GLOBALS['comment'] = $comment; ?>
            <div id="comment-<?php comment_ID(); ?>" class="comment-item parent">
                <div class="comment-avatar">
                    <img src="<?php echo boxmoe_lazy_load_images(); ?>"
                         data-src="<?php echo boxmoe_get_avatar_url($comment->comment_author_email, 60); ?>"
                         alt="评论头像" class="lazy">
                </div>
                <div class="comment-content">
                    <div class="comment-meta">
                        <span class="comment-author">
                            <?php
                            $comment_url = get_comment_author_url();
                            if (!empty($comment_url) && $comment_url !== 'http://') {
                                echo '<a href="' . esc_url($comment_url) . '" target="_blank" rel="nofollow">' . get_comment_author() . '</a>';
                            } else {
                                comment_author();
                            }
                            ?>
                        </span>
                        <?php if ($comment->user_id): ?>
                            <?php echo boxmoe_get_user_tag_badge($comment->user_id); ?>
                        <?php endif; ?>
                        <span class="comment-date"><?php comment_date('Y年m月d日'); ?></span>
                    </div>
                    <div class="comment-text">
                        <?php
                        $is_private = get_comment_meta($comment->comment_ID, 'private_comment', true);
                        if ($is_private) {
                            $current_user_id = get_current_user_id();
                            $post_author_id = get_post_field('post_author', $comment->comment_post_ID);
                            if ($current_user_id &&
                                ($current_user_id == $post_author_id ||
                                 $current_user_id == $comment->user_id ||
                                 ($comment->comment_parent > 0 && $current_user_id == get_comment($comment->comment_parent)->user_id))
                            ) {
                                echo wp_kses_post(get_comment_text());
                                echo '<span class="private-comment-badge">仅作者可见</span>';
                            } else {
                                echo '<p class="private-comment-notice">此评论仅作者可见</p>';
                            }
                        } else {
                            echo wp_kses_post(get_comment_text());
                        }
                        ?>
                    </div>
                    <div class="comment-from-post" style="margin-top:6px;display:flex;align-items:center;gap:12px;">
                        <small class="text-muted">
                            评论于：
                            <a href="<?php echo esc_url(get_permalink($comment->comment_post_ID) . '#comment-' . $comment->comment_ID); ?>">
                                <?php echo esc_html(get_the_title($comment->comment_post_ID)); ?>
                            </a>
                        </small>
                        <?php
                        $profile_uid = get_current_user_id();
                        $post_aid = get_post_field('post_author', $comment->comment_post_ID);
                        if ($profile_uid && ($profile_uid == $post_aid || $profile_uid == $comment->user_id || current_user_can('manage_options'))):
                        ?>
                        <span class="comment-delete-btn" data-comment-id="<?php comment_ID(); ?>" style="margin-left:auto;">
                            <i class="fa fa-trash-o"></i>删除
                        </span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endforeach;
            $total_pages = ceil($comment_total / $per_page);
            if ($total_pages > 1): ?>
            <div class="col-lg-12 col-md-12 pagenav">
                <nav class="d-flex justify-content-center"><ul class="pagination">
                <?php for ($i = 1; $i <= $total_pages; $i++):
                    $active = ($i == $paged) ? ' active' : '';
                    $link = ($i == 1) ? $user_url . '#comments' : add_query_arg('paged', $i, $user_url) . '#comments'; ?>
                    <li class="page-item<?php echo $active; ?>"><a class="page-link" href="<?php echo esc_url($link); ?>"><?php echo $i; ?></a></li>
                <?php endfor; ?>
                </ul></nav>
            </div>
            <?php endif;
        else: ?>
            <div class="text-center py-7">
                <i class="fa fa-comments-o" style="font-size:4rem;color:#ccc;"></i>
                <p class="mt-3 text-muted">暂无评论</p>
            </div>
        <?php endif; ?>
    </div>

    <!-- ====== 收藏列表 ====== -->
    <div class="profile-tab-pane" data-tab="favorites" id="profile-tab-favorites">
        <?php if (!boxmoe_can_view_content($user_id, 'favorites_visibility')): ?>
            <div class="text-center py-7">
                <i class="fa fa-lock" style="font-size:4rem;color:#ccc;"></i>
                <p class="mt-3 text-muted">抱歉，根据隐私设置，该用户拒绝显示此页面</p>
            </div>
        <?php else: ?>
            <?php
            if ($show_masked) {
                $favorite_posts = array();
                $favorites_total = 0;
                $favorites_pages = 0;
            } else {
                $favorites_data = boxmoe_get_user_favorites_query($user_id, $paged, $per_page);
                $favorite_posts = $favorites_data['posts'];
                $favorites_total = $favorites_data['total'];
                $favorites_pages = $favorites_data['pages'];
            }
            ?>
            <?php if (!empty($favorite_posts)): ?>
                <?php foreach ($favorite_posts as $_post):
                    setup_postdata($GLOBALS['post'] = $_post); ?>
                <article class="post-list list-one row <?php echo boxmoe_border_setting(); ?>">
                    <div class="post-list-img">
                        <figure class="mb-4 mb-lg-0 zoom-img">
                            <a href="<?php the_permalink(); ?>" title="<?php the_title(); ?>">
                                <img src="<?php echo boxmoe_lazy_load_images(); ?>"
                                     data-src="<?php echo boxmoe_article_thumbnail_src(); ?>"
                                     alt="<?php the_title(); ?>" class="img-fluid rounded-3 lazy">
                            </a>
                        </figure>
                    </div>
                    <div class="post-list-content">
                        <div class="category"><div class="tags">
                            <?php
                            $categories = get_the_category();
                            if (!empty($categories)) {
                                foreach ($categories as $cat) {
                                    $cat_color = get_term_meta($cat->term_id, 'boxmoe_cat_color', true) ?: '#999';
                                    echo '<a href="' . esc_url(get_category_link($cat->term_id)) . '" rel="category tag">';
                                    echo '<i class="tagfa fa fa-dot-circle-o" style="color:' . esc_attr($cat_color) . '"></i>' . esc_html($cat->name) . '</a> ';
                                }
                            }
                            ?>
                        </div></div>
                        <div class="mt-2 mb-2">
                            <h3 class="post-title h4">
                                <a href="<?php the_permalink(); ?>" title="<?php the_title(); ?>" class="text-reset"><?php the_title(); ?></a>
                            </h3>
                            <p class="post-content"><?php echo _get_excerpt(80); ?></p>
                        </div>
                        <div class="post-meta align-items-center">
                            <div class="post-list-avatar">
                                <img src="<?php echo boxmoe_lazy_load_images(); ?>"
                                     data-src="<?php echo boxmoe_get_avatar_url($user_id, 80); ?>"
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
                                    <i class="fa fa-at"></i><?php the_author(); ?><?php echo boxmoe_get_user_tag_badge(get_the_author_meta('ID')); ?>
                                    <span class="dot"></span><?php echo get_the_time('Y-m-d'); ?>
                                </span>
                            </div>
                        </div>
                    </div>
                </article>
                <?php endforeach;
                wp_reset_postdata(); ?>
                <?php if ($favorites_pages > 1): ?>
                <div class="col-lg-12 col-md-12 pagenav">
                    <nav class="d-flex justify-content-center"><ul class="pagination">
                    <?php
                    $current = max(1, $paged);
                    for ($i = 1; $i <= $favorites_pages; $i++):
                        $link = ($i == 1) ? $user_url . '#favorites' : add_query_arg('paged', $i, $user_url) . '#favorites'; ?>
                        <li class="page-item<?php echo $i == $current ? ' active' : ''; ?>">
                            <a class="page-link" href="<?php echo esc_url($link); ?>"><?php echo $i; ?></a>
                        </li>
                    <?php endfor; ?>
                    </ul></nav>
                </div>
                <?php endif; ?>
            <?php else: ?>
                <div class="text-center py-7">
                    <i class="fa fa-heart-o" style="font-size:4rem;color:#ccc;"></i>
                    <p class="mt-3 text-muted">暂无收藏</p>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <!-- ====== 关注列表 ====== -->
    <div class="profile-tab-pane" data-tab="following" id="profile-tab-following">
        <?php if (!function_exists('boxmoe_can_view_following') || !boxmoe_can_view_following($user_id)): ?>
            <div class="text-center py-7">
                <i class="fa fa-lock" style="font-size:4rem;color:#ccc;"></i>
                <p class="mt-3 text-muted">抱歉，根据隐私设置，该用户拒绝显示此页面</p>
            </div>
        <?php else: ?>
            <?php $following_ids = function_exists('boxmoe_get_following') ? boxmoe_get_following($user_id) : []; ?>
            <?php if (empty($following_ids)): ?>
                <div class="text-center py-7">
                    <i class="fa fa-users" style="font-size:4rem;color:#ccc;"></i>
                    <p class="mt-3 text-muted">暂无关注</p>
                </div>
            <?php else: ?>
                <?php foreach ($following_ids as $fuid):
                    $fu = get_userdata($fuid);
                    if (!$fu) continue; ?>
                <div class="d-flex align-items-center gap-3 py-3 border-bottom">
                    <a href="<?php echo esc_url(boxmoe_get_user_profile_url($fuid)); ?>">
                        <img src="<?php echo boxmoe_get_avatar_url($fuid, 42); ?>" class="rounded-circle" style="width:42px;height:42px;object-fit:cover;">
                    </a>
                    <div class="flex-fill">
                        <a href="<?php echo esc_url(boxmoe_get_user_profile_url($fuid)); ?>" class="fw-semibold text-reset text-decoration-none"><?php echo esc_html($fu->display_name); ?></a>
                        <?php if (function_exists('boxmoe_get_user_tag_badge')) echo boxmoe_get_user_tag_badge($fuid); ?>
                        <div class="small text-muted"><?php echo esc_html(get_user_meta($fuid, 'description', true)); ?></div>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <!-- ====== 粉丝列表 ====== -->
    <div class="profile-tab-pane" data-tab="followers" id="profile-tab-followers">
        <?php if (!function_exists('boxmoe_can_view_followers') || !boxmoe_can_view_followers($user_id)): ?>
            <div class="text-center py-7">
                <i class="fa fa-lock" style="font-size:4rem;color:#ccc;"></i>
                <p class="mt-3 text-muted">抱歉，根据隐私设置，该用户拒绝显示此页面</p>
            </div>
        <?php else: ?>
            <?php $follower_ids = function_exists('boxmoe_get_followers') ? boxmoe_get_followers($user_id) : []; ?>
            <?php if (empty($follower_ids)): ?>
                <div class="text-center py-7">
                    <i class="fa fa-user-plus" style="font-size:4rem;color:#ccc;"></i>
                    <p class="mt-3 text-muted">暂无粉丝</p>
                </div>
            <?php else: ?>
                <?php foreach ($follower_ids as $fuid):
                    $fu = get_userdata($fuid);
                    if (!$fu) continue; ?>
                <div class="d-flex align-items-center gap-3 py-3 border-bottom">
                    <a href="<?php echo esc_url(boxmoe_get_user_profile_url($fuid)); ?>">
                        <img src="<?php echo boxmoe_get_avatar_url($fuid, 42); ?>" class="rounded-circle" style="width:42px;height:42px;object-fit:cover;">
                    </a>
                    <div class="flex-fill">
                        <a href="<?php echo esc_url(boxmoe_get_user_profile_url($fuid)); ?>" class="fw-semibold text-reset text-decoration-none"><?php echo esc_html($fu->display_name); ?></a>
                        <?php if (function_exists('boxmoe_get_user_tag_badge')) echo boxmoe_get_user_tag_badge($fuid); ?>
                        <div class="small text-muted"><?php echo esc_html(get_user_meta($fuid, 'description', true)); ?></div>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        <?php endif; ?>
    </div>

</div>

<?php if ($can_edit_avatar): ?>
<script>
(function() {
    var wrap = document.getElementById('profile-avatar-wrap');
    var input = document.getElementById('profile-avatar-input');
    var overlay = wrap ? wrap.querySelector('.avatar-overlay') : null;
    var img = document.getElementById('profile-avatar-img');
    if (!wrap || !input || !img) return;

    // 鼠标 hover 效果
    wrap.addEventListener('mouseenter', function() {
        if (overlay) overlay.style.opacity = '1';
        wrap.style.cursor = 'pointer';
    });
    wrap.addEventListener('mouseleave', function() {
        if (overlay) overlay.style.opacity = '0';
    });

    // 点击 → 打开文件选择
    wrap.addEventListener('click', function(e) {
        e.stopPropagation();
        input.click();
    });

    // 文件选择 → 上传
    input.addEventListener('change', function() {
        var file = this.files[0];
        if (!file) return;

        // loading
        if (overlay) {
            overlay.querySelector('i').className = 'fa fa-spinner fa-spin';
            overlay.style.opacity = '1';
        }

        // 本地预览
        var reader = new FileReader();
        reader.onload = function(e) {
            img.setAttribute('src', e.target.result);
            img.classList.remove('lazy');
        };
        reader.readAsDataURL(file);

        // 上传
        var fd = new FormData();
        fd.append('action', 'boxmoe_upload_avatar');
        fd.append('user_id', <?php echo $user_id; ?>);
        fd.append('file', file);

        fetch('<?php echo admin_url("admin-ajax.php"); ?>', {
            method: 'POST',
            credentials: 'same-origin',
            body: fd
        })
        .then(function(r) { return r.json(); })
        .then(function(result) {
            if (result.success && result.data && result.data.avatar_url) {
                img.setAttribute('src', result.data.avatar_url);
                img.classList.remove('lazy');
                if (overlay) {
                    overlay.querySelector('i').className = 'fa fa-check';
                    setTimeout(function() { overlay.style.opacity = '0'; overlay.querySelector('i').className = 'fa fa-camera'; }, 1500);
                }
            } else {
                throw new Error(result.data && result.data.message ? result.data.message : '未知错误');
            }
        })
        .catch(function(err) {
            console.error('Avatar upload failed:', err);
            alert('头像上传失败：' + err.message);
            if (overlay) {
                overlay.querySelector('i').className = 'fa fa-camera';
                overlay.style.opacity = '0';
            }
        });

        // 清空 input 以允许重新选择同一文件
        this.value = '';
    });
})();
</script>
<?php endif; ?>

<script src="https://cdn.jsdelivr.net/npm/marked/marked.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/easymde/dist/easymde.min.js"></script>
<script>
(function() {
    var tabs = document.querySelectorAll('.profile-tab-link');
    var panes = document.querySelectorAll('.profile-tab-pane');

    function activateTab(name) {
        panes.forEach(function(p) {
            p.classList.toggle('active', p.getAttribute('data-tab') === name);
        });
        tabs.forEach(function(a) {
            var isActive = a.getAttribute('data-tab') === name;
            a.style.background = isActive ? 'var(--bs-primary)' : '';
            a.style.color = isActive ? '#fff' : '';
        });
        // 评论 / 关注 / 粉丝 tab：手动触发逐项渐入
        if (name === 'comments' || name === 'following' || name === 'followers') {
            var sel = name === 'comments' ? '#profile-tab-comments .comment-item' : ('#profile-tab-' + name + ' .d-flex');
            var items = document.querySelectorAll(sel);
            items.forEach(function(el, i) {
                el.style.opacity = '0';
                el.style.transform = 'translateY(40px)';
                el.style.animation = 'fadeInUp .6s ease-out forwards';
                el.style.animationDelay = (i * 0.06) + 's';
            });
        }
    }

    function getHashTab() {
        var h = window.location.hash.replace('#', '');
        return (h === 'posts' || h === 'shuoshuo' || h === 'comments' || h === 'favorites' || h === 'following' || h === 'followers') ? h : 'about';
    }

    activateTab(getHashTab());

    tabs.forEach(function(a) {
        a.addEventListener('click', function(e) {
            e.preventDefault();
            var name = this.getAttribute('data-tab');
            var url = window.location.pathname + window.location.search + '#' + name;
            history.replaceState(null, '', url);
            activateTab(name);
        });
    });

    window.addEventListener('hashchange', function() {
        activateTab(getHashTab());
    });

    // ====== Markdown 渲染 ======
    var aboutView = document.getElementById('about-view');
    var aboutEdit = document.getElementById('about-edit');
    var editBtn = document.getElementById('about-edit-btn');
    var saveBtn = document.getElementById('about-save-btn');
    var cancelBtn = document.getElementById('about-cancel-btn');
    var editorTextarea = document.getElementById('about-editor');
    var easyMDE = null;

    // 渲染 markdown（动态查找 .markdown-body，处理空内容场景）
    function renderMarkdown() {
        var dataEl = document.getElementById('about-content-data');
        var raw = dataEl ? dataEl.textContent : (editorTextarea ? editorTextarea.value : '');
        var body = document.querySelector('.markdown-body');
        if (!raw.trim()) return;

        // 如果 about-rendered 容器不存在（初始无内容），先创建
        var rendered = document.querySelector('.about-rendered');
        if (!rendered) {
            var emptyEl = document.querySelector('#about-view .text-center');
            if (emptyEl) emptyEl.style.display = 'none';
            rendered = document.createElement('div');
            rendered.className = 'about-rendered';
            rendered.style.cssText = 'min-height:100px;';
            body = document.createElement('div');
            body.className = 'markdown-body';
            rendered.appendChild(body);
            var viewEl = document.getElementById('about-view');
            var editBtn = document.getElementById('about-edit-btn');
            if (editBtn) {
                viewEl.insertBefore(rendered, editBtn);
            } else {
                viewEl.appendChild(rendered);
            }
        }

        if (typeof marked !== 'undefined' && body) {
            body.innerHTML = marked.parse(raw);
        }
    }
    renderMarkdown();

    // 进入编辑模式
    if (editBtn) {
        editBtn.addEventListener('click', function() {
            aboutView.style.display = 'none';
            aboutEdit.style.display = 'block';

            if (!easyMDE && typeof EasyMDE !== 'undefined') {
                easyMDE = new EasyMDE({
                    element: editorTextarea,
                    spellChecker: false,
                    placeholder: '写点什么...支持 Markdown 语法',
                    toolbar: ['bold','italic','heading','|','quote','unordered-list','ordered-list','|','link','image','table','|','preview','side-by-side','fullscreen','|','guide'],
                    status: false,
                    minHeight: '300px'
                });
            }
            // 刷新编辑器布局（从隐藏状态恢复时需要）
            if (easyMDE && easyMDE.codemirror) {
                setTimeout(function() { easyMDE.codemirror.refresh(); }, 50);
            }
        });
    }

    // ====== 表情面板 ======
    var emojiBtn = document.getElementById('about-emoji-btn');
    var emojiPanel = document.getElementById('about-emoji-panel');
    if (emojiBtn && emojiPanel) {
        var emojis = {
            emoji: ['😀','😁','😂','🤣','😃','😄','😅','😆','😉','😊','😋','😎','😍','🥰','😘','😗','😙','😚','😛','😝','🤗','🤔','🤨','😐','😑','😶','🙄','😏','😣','😥','😮','🤤','😴','😪','😵','🤯','🤠','🤡','🥳','🥺','🤩','😤','😡','🤬','💀','👻','👽','🤖','🎉','🎊','❤️','🧡','💛','💚','💙','💜','🖤','🤍','👍','👎','👏','🙌','💪','🔥','⭐','✨','💯','✅','❌'],
            custom: ['(⌒▽⌒)','(￣▽￣)','(=・ω・=)','(｀・ω・´)','(〜￣△￣)〜','(･∀･)','(°∀°)ﾉ','(￣3￣)','╮(￣▽￣)╭','(*>.<*)','( ˃̶͈◡˂̶͈ ) hi!','⚆_⚆？','⚆_⚆','(｡•ˇ‸ˇ•｡)','(╯°口°)╯(┴—┴','( ´_ゝ`)','(╥﹏╥)','_(:3」∠)_','(´･ω･`)','(¦3[▓▓]']
        };

        emojiBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            emojiPanel.style.display = emojiPanel.style.display === 'block' ? 'none' : 'block';
            if (emojiPanel.style.display === 'block') {
                var firstTab = emojiPanel.querySelector('.emoji-tabs span');
                if (firstTab) firstTab.click();
            }
        });

        // 点击其他地方关闭面板
        document.addEventListener('click', function(e) {
            if (!emojiPanel.contains(e.target) && e.target !== emojiBtn) {
                emojiPanel.style.display = 'none';
            }
        });

        // Tab 切换
        emojiPanel.querySelectorAll('.emoji-tabs span').forEach(function(tab) {
            tab.addEventListener('click', function() {
                var type = this.dataset.tab;
                emojiPanel.querySelectorAll('.emoji-tabs span').forEach(function(t) {
                    t.style.background = ''; t.style.color = 'var(--bs-body-color)';
                });
                this.style.background = 'var(--bs-primary)'; this.style.color = '#fff';

                var content = emojiPanel.querySelector('.emoji-content');
                content.innerHTML = '';
                var data = emojis[type];
                if (data && Array.isArray(data)) {
                    data.forEach(function(item) {
                        var span = document.createElement('span');
                        span.textContent = item;
                        span.style.cssText = 'cursor:pointer;font-size:22px;padding:4px;border-radius:4px;line-height:1;';
                        span.addEventListener('mouseenter', function() { this.style.background = 'var(--bs-light)'; });
                        span.addEventListener('mouseleave', function() { this.style.background = ''; });
                        span.addEventListener('click', function() {
                            insertAtCursor(item);
                            emojiPanel.style.display = 'none';
                        });
                        content.appendChild(span);
                    });
                }
            });
        });

        function insertAtCursor(text) {
            if (easyMDE && easyMDE.codemirror) {
                easyMDE.codemirror.replaceSelection(text);
            } else if (editorTextarea) {
                var start = editorTextarea.selectionStart;
                var end = editorTextarea.selectionEnd;
                editorTextarea.value = editorTextarea.value.substring(0, start) + text + editorTextarea.value.substring(end);
                editorTextarea.selectionStart = editorTextarea.selectionEnd = start + text.length;
                editorTextarea.focus();
            }
        }
    }

    // 保存
    if (saveBtn) {
        saveBtn.addEventListener('click', function() {
            var content = easyMDE ? easyMDE.value() : (editorTextarea ? editorTextarea.value : '');
            saveBtn.disabled = true;
            saveBtn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> 保存中...';

            var fd = new FormData();
            fd.append('action', 'boxmoe_save_about');
            fd.append('user_id', <?php echo $user_id; ?>);
            fd.append('content', content);

            fetch('<?php echo admin_url("admin-ajax.php"); ?>', {
                method: 'POST',
                credentials: 'same-origin',
                body: fd
            })
            .then(function(r) { return r.json(); })
            .then(function(result) {
                saveBtn.disabled = false;
                saveBtn.innerHTML = '<i class="fa fa-check"></i> 保存';
                if (result.success) {
                    // 更新数据源
                    if (editorTextarea) editorTextarea.value = content;
                    var dataEl = document.getElementById('about-content-data');
                    if (!dataEl) {
                        dataEl = document.createElement('script');
                        dataEl.type = 'text/plain';
                        dataEl.id = 'about-content-data';
                        document.body.appendChild(dataEl);
                    }
                    dataEl.textContent = content;
                    // 隐藏"暂无简介"
                    var emptyEl = document.querySelector('#about-view .text-center');
                    if (emptyEl && content.trim()) emptyEl.style.display = 'none';
                    // 渲染 markdown
                    renderMarkdown();
                    // 切换回查看模式
                    aboutView.style.display = 'block';
                    aboutEdit.style.display = 'none';
                } else {
                    alert('保存失败：' + (result.data && result.data.message ? result.data.message : '未知错误'));
                }
            })
            .catch(function(err) {
                saveBtn.disabled = false;
                saveBtn.innerHTML = '<i class="fa fa-check"></i> 保存';
                alert('网络错误：' + err.message);
            });
        });
    }

    // 取消编辑
    if (cancelBtn) {
        cancelBtn.addEventListener('click', function() {
            // 恢复原始内容
            if (easyMDE && editorTextarea) {
                easyMDE.value(editorTextarea.defaultValue || '');
            }
            aboutView.style.display = 'block';
            aboutEdit.style.display = 'none';
        });
    }


    // ====== 关注按钮 ======
    var fb = document.getElementById('follow-btn');
    if (fb) {
        fb.addEventListener('click', function() {
            var uid = this.getAttribute('data-uid');
            var following = this.getAttribute('data-following') === '1';
            var followedBack = this.getAttribute('data-followed-back') === '1';
            this.disabled = true;
            this.innerHTML = '<i class="fa fa-spinner fa-spin"></i>';
            var fd = new FormData();
            fd.append('action', 'boxmoe_toggle_follow');
            fd.append('user_id', uid);
            fetch('<?php echo admin_url("admin-ajax.php"); ?>', { method: 'POST', credentials: 'same-origin', body: fd })
                .then(function(r) { return r.json(); })
                .then(function(res) {
                    if (res.success) {
                        var nowFollowing = res.data.following;
                        fb.setAttribute('data-following', nowFollowing ? '1' : '0');
                        if (nowFollowing && followedBack) {
                            fb.className = 'btn btn-sm btn-outline-secondary';
                            fb.innerHTML = '<i class="fa fa-exchange"></i> 已互关';
                        } else if (nowFollowing) {
                            fb.className = 'btn btn-sm btn-outline-secondary';
                            fb.innerHTML = '<i class="fa fa-check"></i> 已关注';
                        } else if (followedBack) {
                            fb.className = 'btn btn-sm btn-primary';
                            fb.innerHTML = '<i class="fa fa-plus"></i> 互关';
                        } else {
                            fb.className = 'btn btn-sm btn-primary';
                            fb.innerHTML = '<i class="fa fa-plus"></i> 关注';
                        }
                        var fc = document.getElementById('follower-count');
                        if (fc) fc.textContent = res.data.count;
                    } else {
                        alert(res.data.message || '操作失败');
                    }
                    fb.disabled = false;
                })
                .catch(function() { fb.disabled = false; fb.innerHTML = following ? '<i class="fa fa-check"></i> 已关注' : '<i class="fa fa-plus"></i> 关注'; });
        });
    }

    // ====== 社交图标自适应展开动画 ======
    document.querySelectorAll('.profile-social-item').forEach(function(item) {
        var textEl = item.querySelector('.profile-social-text');
        if (!textEl) return;
        // 测量文本实际宽度
        var clone = textEl.cloneNode(true);
        clone.style.cssText = 'position:absolute;visibility:hidden;max-width:none;white-space:nowrap;font-size:' + getComputedStyle(textEl).fontSize;
        document.body.appendChild(clone);
        var realWidth = clone.offsetWidth;
        document.body.removeChild(clone);
        // 时长 = 基础0.15s + 每20px加0.05s，上限0.6s
        var duration = Math.min(0.6, 0.15 + realWidth / 20 * 0.05);
        textEl.style.transition = 'max-width ' + duration + 's ease, opacity 0.25s ease';
        textEl.style.maxWidth = '0';
        textEl._targetWidth = realWidth;
        item.addEventListener('mouseenter', function() { textEl.style.maxWidth = (realWidth + 20) + 'px'; });
        item.addEventListener('mouseleave', function() { textEl.style.maxWidth = '0'; });
    });

    // ====== 社交图标点击 ======
    document.querySelectorAll('.profile-social-item').forEach(function(item) {
        item.addEventListener('click', function() {
            var action = this.dataset.action;
            var label = this.dataset.label;
            if (action === 'link') {
                var href = this.dataset.href;
                if (href) window.open(href, '_blank');
                return;
            }
            var val = this.dataset.copy;
            if (!val) return;
            if (navigator.clipboard) {
                navigator.clipboard.writeText(val).then(function() {
                    showCopyToast(label + ' 已复制');
                });
            } else {
                var ta = document.createElement('textarea');
                ta.value = val; ta.style.position = 'fixed'; ta.style.left = '-9999px';
                document.body.appendChild(ta); ta.select();
                try { document.execCommand('copy'); showCopyToast(label + ' 已复制'); } catch(e) {}
                document.body.removeChild(ta);
            }
        });
    });
    function showCopyToast(msg) {
        var el = document.createElement('div');
        el.textContent = msg;
        el.style.cssText = 'position:fixed;bottom:30px;left:50%;transform:translateX(-50%);z-index:99999;padding:10px 24px;border-radius:20px;background:#333;color:#fff;font-size:13px;box-shadow:0 4px 15px rgba(0,0,0,0.3);animation:fadeIn 0.3s ease;';
        document.body.appendChild(el);
        setTimeout(function(){ el.remove(); }, 2000);
    }

    // ====== 签名行内编辑 ======
    var descWrap = document.getElementById('profile-desc-wrap');
    if (descWrap) {
        var descText = document.getElementById('profile-desc-text');
        var descEditIcon = document.getElementById('profile-desc-edit-icon');
        var descEdit = document.getElementById('profile-desc-edit');
        var descInput = document.getElementById('profile-desc-input');
        var descSave = document.getElementById('profile-desc-save');
        var descCancel = document.getElementById('profile-desc-cancel');
        var descEditing = false;

        descWrap.addEventListener('mouseenter', function() {
            if (!descEditing && descEditIcon) descEditIcon.style.display = '';
        });
        descWrap.addEventListener('mouseleave', function() {
            if (descEditIcon) descEditIcon.style.display = 'none';
        });

        // 点击文字或图标 → 编辑模式
        function enterEdit() {
            if (typeof easyMDE !== 'undefined' && easyMDE && easyMDE.isFullscreenActive && easyMDE.isFullscreenActive()) {
                return;
            }
            descEditing = true;
            descText.style.display = 'none';
            if (descEditIcon) descEditIcon.style.display = 'none';
            descEdit.style.display = '';
            descInput.focus();
        }
        function exitEdit() {
            descEditing = false;
            descText.style.display = '';
            descEdit.style.display = 'none';
        }
        if (descEdit) {
            descText.addEventListener('click', enterEdit);
            if (descEditIcon) descEditIcon.addEventListener('click', enterEdit);
        }

        if (descEdit) {
            // 取消
            descCancel.addEventListener('click', function() {
                descInput.value = descInput.defaultValue;
                exitEdit();
            });

            // 保存
            descSave.addEventListener('click', function() {
                var val = descInput.value.trim();
                descSave.disabled = true;
                descSave.innerHTML = '<i class="fa fa-spinner fa-spin"></i>';

                var fd = new FormData();
                fd.append('action', 'boxmoe_save_description');
                fd.append('user_id', <?php echo $user_id; ?>);
                fd.append('content', val);

                fetch('<?php echo admin_url("admin-ajax.php"); ?>', {
                    method: 'POST',
                    credentials: 'same-origin',
                    body: fd
                })
                .then(function(r) { return r.json(); })
                .then(function(r) {
                    descSave.disabled = false;
                    descSave.innerHTML = '保存';
                    if (r.success) {
                        descText.textContent = val || '添加签名...';
                        descText.className = val ? 'text-secondary' : 'text-muted';
                        exitEdit();
                    } else {
                        alert(r.data && r.data.message ? r.data.message : '保存失败');
                    }
                })
                .catch(function() {
                    descSave.disabled = false;
                    descSave.innerHTML = '保存';
                    alert('网络错误');
                });
            });

            // 回车保存
            descInput.addEventListener('keydown', function(e) {
                if (e.key === 'Enter') { e.preventDefault(); descSave.click(); }
                if (e.key === 'Escape') descCancel.click();
            });
        }
    }
})();
</script>
</div>
