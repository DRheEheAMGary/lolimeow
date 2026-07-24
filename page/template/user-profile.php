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

// 预查询所有 tab 数据
$posts_query    = boxmoe_get_user_posts($user_id, $paged, $per_page);
$shuoshuo_query = boxmoe_get_user_shuoshuo($user_id, $paged, $per_page);
$comments       = boxmoe_get_user_comments($user_id, $paged, $per_page);
$comment_total  = boxmoe_get_user_comment_count($user_id);

// 头像编辑权限：仅本人
$can_edit_avatar = (get_current_user_id() === $user_id);
?>

<div class="<?php echo boxmoe_layout_setting(); ?> blog-post user-profile-page">

    <style>
        .user-profile-page .blog-border::after,
        .user-profile-page .blog-shadow::after,
        .user-profile-page .blog-lines::after { display:none !important; }
        .profile-tab-pane { display:none; }
        .profile-tab-pane.active { display:block; }
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
        .about-rendered .markdown-body blockquote { border-left:3px solid var(--bs-primary); padding-left:16px; color:var(--bs-secondary-color); margin-left:0; }
        .about-rendered .markdown-body table { border-collapse:collapse; width:100%; }
        .about-rendered .markdown-body th,.about-rendered .markdown-body td { border:1px solid var(--bs-border-color); padding:8px 12px; }
        .about-rendered .markdown-body a { color:var(--bs-primary); }
        .about-rendered .markdown-body ul,.about-rendered .markdown-body ol { padding-left:1.5em; }
        .editor-toolbar { border-radius:6px 6px 0 0 !important; }
        .CodeMirror { border-radius:0 0 6px 6px !important; }
        .editor-statusbar { display:none; }
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
                         data-src="<?php echo boxmoe_get_avatar_url($user_id, 120); ?>"
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
                    <?php echo esc_html($profile_user->display_name); ?>
                    <?php if (!empty($profile_user->user_url)): ?>
                    <a href="<?php echo esc_url($profile_user->user_url); ?>" target="_blank"
                       class="text-muted ms-2" title="个人网站" style="font-size:0.9rem;">
                        <i class="fa fa-link"></i>
                    </a>
                    <?php endif; ?>
                </h2>
                <p class="text-muted mb-2" style="font-size:0.85rem;">@<?php echo esc_html($profile_user->user_nicename); ?></p>
                <?php if (!empty($profile_user->description)): ?>
                <p class="text-secondary mb-0" style="font-size:0.9rem;">
                    <?php echo esc_html($profile_user->description); ?>
                </p>
                <?php endif; ?>
            </div>
        </div>
        <div class="row mt-3">
            <div class="col-4 text-center">
                <div class="fw-bold fs-5"><?php echo $stats['post_count']; ?></div>
                <small class="text-muted">文章</small>
            </div>
            <div class="col-4 text-center">
                <div class="fw-bold fs-5"><?php echo $stats['shuoshuo_count']; ?></div>
                <small class="text-muted">说说</small>
            </div>
            <div class="col-4 text-center">
                <div class="fw-bold fs-5"><?php echo $stats['comment_count']; ?></div>
                <small class="text-muted">评论</small>
            </div>
        </div>
    </div>

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
    </div>

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/easymde/dist/easymde.min.css">

    <!-- ====== 简介（默认 Tab） ====== -->
    <div class="profile-tab-pane active" data-tab="about" id="profile-tab-about">
        <?php $about_content = get_user_meta($user_id, 'description', true); ?>
        <div class="about-view-mode" id="about-view">
            <?php if (!empty(trim($about_content))): ?>
            <div class="about-rendered" style="min-height:100px;">
                <div class="markdown-body"></div>
            </div>
            <?php else: ?>
            <div class="text-center py-7">
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
            <div style="margin-top:10px;display:flex;gap:10px;">
                <button class="btn btn-primary btn-sm" id="about-save-btn">
                    <i class="fa fa-check"></i> 保存
                </button>
                <button class="btn btn-outline-secondary btn-sm" id="about-cancel-btn">
                    取消
                </button>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- ====== 文章列表 ====== -->
    <div class="profile-tab-pane" data-tab="posts" id="profile-tab-posts">
        <?php if ($posts_query->have_posts()):
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
                                <i class="fa fa-at"></i><?php the_author(); ?>
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
        <?php if ($shuoshuo_query->have_posts()):
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
                            <i class="fa fa-at"></i><?php echo esc_html($profile_user->display_name); ?>
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
        <?php if (!empty($comments)):
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
                        <?php if (user_can($comment->user_id, 'administrator')): ?>
                            <span class="comment-badge"><?php echo get_boxmoe('boxmoe_comment_blogger_tag') ?: '博主'; ?></span>
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
                    <div class="comment-from-post" style="margin-top:6px;">
                        <small class="text-muted">
                            评论于：
                            <a href="<?php echo esc_url(get_permalink($comment->comment_post_ID) . '#comment-' . $comment->comment_ID); ?>">
                                <?php echo esc_html(get_the_title($comment->comment_post_ID)); ?>
                            </a>
                        </small>
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
    }

    function getHashTab() {
        var h = window.location.hash.replace('#', '');
        return (h === 'posts' || h === 'shuoshuo' || h === 'comments') ? h : 'about';
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
        var raw = editorTextarea ? editorTextarea.value : '';
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
        });
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
                    // 更新 textarea 原始值
                    if (editorTextarea) editorTextarea.value = content;
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
})();
</script>
