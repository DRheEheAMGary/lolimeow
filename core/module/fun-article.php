<?php
/**
 * @link https://www.boxmoe.com
 * @package lolimeow
 */

// 安全设置--------------------------boxmoe.com--------------------------
if(!defined('ABSPATH')){
    echo'Look your sister';
    exit;
}

// 文章新窗口打开开关--------------------------boxmoe.com--------------------------
function boxmoe_article_new_window() {
    return get_boxmoe('boxmoe_article_new_window_switch')?'target="_blank"':'';
}

// 开启所有文章形式支持--------------------------boxmoe.com--------------------------
if(get_boxmoe('boxmoe_article_support_switch')){
    add_theme_support('post-formats', array('image', 'video', 'audio', 'quote', 'link'));
}

//开启特色文章缩略图
    add_theme_support('post-thumbnails');
	

// 缩略图尺寸设定--------------------------boxmoe.com--------------------------
if(get_boxmoe('boxmoe_article_thumbnail_size_switch')){
function boxmoe_article_thumbnail_size($size) {
    $width  = intval(get_boxmoe('boxmoe_article_thumbnail_width')) ?: 300; 
    $height = intval(get_boxmoe('boxmoe_article_thumbnail_height')) ?: 200;
    return array($width, $height); 
}
add_filter('post_thumbnail_size', 'boxmoe_article_thumbnail_size');
}

// ====== 说说文章屏蔽 AI Summary Generator 插件（精确移除） ======
// 该插件唯一自动入口: AI_Summary_Generator_Admin::save_post_post 优先级20
// 插件 fe meta: _ai_summary_generator_summary + _ai_summary_generator_last_modified
// 插件前端: AI_Summary_Generator_Frontend::the_content + ::get_the_excerpt

// Step 1: save_post_post 最早介入，说说时移除插件钩子，不说说不干涉
function boxmoe_shuoshuo_block_ai_save($post_id) {
    if (!has_category('shuoshuo', $post_id)) {
        return; // 非说说文章，让插件正常工作
    }
    // 移除插件 save_post_post，阻止自动生成 AI 摘要
    remove_action('save_post_post', array('AI_Summary_Generator_Admin', 'ai_summary_generator_update_on_post_save'), 20);
    // 临时让 option 返回 no，防止其他入口调用
    add_filter('pre_option_ai_summary_generator_update_on_post_update', function(){ return 'no'; }, 9999);
    // 清理已有 AI 元数据
    delete_post_meta($post_id, '_ai_summary_generator_summary');
    delete_post_meta($post_id, '_ai_summary_generator_last_modified');
}
add_action('save_post_post', 'boxmoe_shuoshuo_block_ai_save', 1);

// Step 2: 仪表盘快速发说说附加拦截
function boxmoe_shuoshuo_dashboard_block_ai() {
    add_filter('pre_option_ai_summary_generator_update_on_post_update', function(){ return 'no'; }, 9999);
    remove_action('save_post_post', array('AI_Summary_Generator_Admin', 'ai_summary_generator_update_on_post_save'), 20);
}

// Step 3: 说说文章单页移除 AI blockquote（_get_excerpt 已处理列表摘要）
function boxmoe_shuoshuo_undo_ai_content($content) {
    if (has_category('shuoshuo')) {
        // 移除插件追加的 .ai-summary blockquote（在内容最前面）
        $content = preg_replace('/^\s*<div class="ai-summary">.*?<\/div>\s*/s', '', $content);
    }
    return $content;
}
add_filter('the_content', 'boxmoe_shuoshuo_undo_ai_content', 15);

// get_the_excerpt：插件优先级10 → 替换为 AI 摘要 → 我优先级15，说说时回退
function boxmoe_shuoshuo_undo_ai_excerpt($excerpt) {
    if (has_category('shuoshuo')) {
        // 直接返回手动摘要或自动截取（绕过插件/递归）
        $manual = get_post_field('post_excerpt', get_the_ID());
        if (!empty($manual)) return $manual;
        return wp_trim_words(get_post_field('post_content', get_the_ID()), 55);
    }
    return $excerpt;
}
add_filter('get_the_excerpt', 'boxmoe_shuoshuo_undo_ai_excerpt', 15);

// 文章缩略图逻辑--------------------------boxmoe.com--------------------------
function boxmoe_article_thumbnail_src() {
    // 随机图片
    if(get_boxmoe('boxmoe_article_thumbnail_random_api')){
        $src = get_boxmoe('boxmoe_article_thumbnail_random_api_url');
        $src = $src ?: boxmoe_theme_url().'/assets/images/default-thumbnail.jpg';
    }else{
        $random_images = glob(get_template_directory().'/assets/images/random/*.{jpg,jpeg,png,gif,webp}', GLOB_BRACE);   
        if (!empty($random_images)) {
            shuffle($random_images);
            $src = str_replace(get_template_directory(), get_template_directory_uri(), $random_images[0]);
        } else {
            $src = boxmoe_theme_url().'/assets/images/default-thumbnail.jpg';
        }
    }
    // 每次调用附加不同随机字符串，防止浏览器/缓存层复用同一 URL
    return $src . '?v=' . boxmoe_random_string(6);
}

//文章点击数换算K--------------------------boxmoe.com--------------------------
function restyle_text($number){
    if ($number >= 1000) {
                  return round($number / 1000, 2) . 'k';
              } else {
                  return $number;
              }
  }
  //文章点击数--------------------------boxmoe.com--------------------------
  function getPostViews($postID){
      $count_key = 'post_views_count';
      $count = get_post_meta($postID, $count_key, true);
      if($count==''){
          delete_post_meta($postID, $count_key);
          add_post_meta($postID, $count_key, '0');
          return "0 View";
      }
      return restyle_text($count);
  }
  function setPostViews($postID) {
      $count_key = 'post_views_count';
      $count = get_post_meta($postID, $count_key, true);
      if($count==''){
          $count = 0;
          delete_post_meta($postID, $count_key);
          add_post_meta($postID, $count_key, '0');
      }else{
          $count++;
          update_post_meta($postID, $count_key, $count);
      }
  }


//修剪标记--------------------------boxmoe.com--------------------------
function _str_cut($str, $start, $width, $trimmarker) {
	$output = preg_replace('/^(?:[\x00-\x7F]|[\xC0-\xFF][\x80-\xBF]+){0,' . $start . '}((?:[\x00-\x7F]|[\xC0-\xFF][\x80-\xBF]+){0,' . $width . '}).*/s', '\1', $str);
	return $output . $trimmarker;
}

//自定义段长度--------------------------boxmoe.com--------------------------
function custom_excerpt_length( $length ){
return 200;
}
add_filter( 'excerpt_length', 'custom_excerpt_length');

//文章、评论内容缩短--------------------------boxmoe.com--------------------------
function _get_excerpt($limit = 60, $after = '...') { 
	// 非说说文章：优先使用 AI 摘要（绕过插件 is_home() 限制）
	if (!has_category('shuoshuo') && class_exists('AI_Summary_Generator_Core')) {
		$ai = AI_Summary_Generator_Core::get_summary(get_the_ID());
		if ($ai) {
			$text = wp_strip_all_tags($ai);
			if (mb_strlen($text) > $limit) {
				return _str_cut($text, 0, $limit, $after);
			}
			return $text;
		}
	}
	$excerpt = get_the_excerpt();
	if (mb_strlen($excerpt) > $limit) {
		return _str_cut(strip_tags($excerpt), 0, $limit, $after);
	} else {
		return $excerpt;
	}
}

// 表格替换--------------------------boxmoe.com--------------------------
function boxmoe_table_replace($text){
	$replace = array( '<table>' => '<div class="table-responsive"><table class="table" >','</table>' => '</table></div>' );
	$text = str_replace(array_keys($replace), $replace, $text);
	return $text;}
add_filter('the_content', 'boxmoe_table_replace');

//防止代码转义--------------------------boxmoe.com--------------------------
function boxmoe_prettify_esc_html($content){
    $regex = '/(<pre\s+[^>]*?class\s*?=\s*?[",\'].*?prettyprint.*?[",\'].*?>)(.*?)(<\/pre>)/sim';
    return preg_replace_callback($regex, 'boxmoe_prettify_esc_callback', $content);}
function boxmoe_prettify_esc_callback($matches){
    $tag_open = $matches[1];
    $content = $matches[2];
    $tag_close = $matches[3];
    $content = esc_html($content);
    return $tag_open . $content . $tag_close;}
add_filter('the_content', 'boxmoe_prettify_esc_html', 2);
add_filter('comment_text', 'boxmoe_prettify_esc_html', 2);

//强制兼容--------------------------boxmoe.com--------------------------
function boxmoe_prettify_replace($text){
	$replace = array( '<pre>' => '<pre class="prettyprint linenums" >','<pre class="prettyprint">' => '<pre class="prettyprint linenums" >' );
	$text = str_replace(array_keys($replace), $replace, $text);
	return $text;}
add_filter('the_content', 'boxmoe_prettify_replace');

// 自动设置特色图片--------------------------boxmoe.com--------------------------
function autoset_featured_image() {
    global $post;
    if (!is_object($post)) return;
    $already_has_thumb = has_post_thumbnail($post->ID);
    if (!$already_has_thumb)  {
        $attached_image = get_children( "post_parent=$post->ID&post_type=attachment&post_mime_type=image&numberposts=1" );
        if ($attached_image) {
            foreach ($attached_image as $attachment_id => $attachment) {
                set_post_thumbnail($post->ID, $attachment_id);
            }
        }
    }
}
add_action( 'the_post', 'autoset_featured_image' );
add_action( 'save_post', 'autoset_featured_image' );
add_action( 'draft_to_publish', 'autoset_featured_image' );
add_action( 'new_to_publish', 'autoset_featured_image' );
add_action( 'pending_to_publish', 'autoset_featured_image' );
add_action( 'future_to_publish', 'autoset_featured_image' );


// 自适应图片--------------------------boxmoe.com--------------------------
function boxmoe_remove_width_height($content) {
    preg_match_all('/<[img|IMG].*?src=[\'|"](.*?(?:[\.gif|\.jpg|\.png\.bmp\.webp]))[\'|"].*?[\/]?>/', $content, $images);
    if (!empty($images)) {
        foreach ($images[0] as $index => $value) {
            $new_img = preg_replace('/(width|height)="\d*"\s/', "", $images[0][$index]);
            $content = str_replace($images[0][$index], $new_img, $content);
        }
    }
    return $content;
}
add_filter('the_content', 'boxmoe_remove_width_height', 99);


// 图片懒加载--------------------------boxmoe.com--------------------------
function boxmoe_lazy_content_load_images($content) {
    $content = preg_replace_callback('/<img([^>]*?)src=([\'"])([^\'"]+)\2/i', 
        function($matches) {
            if (strpos($matches[0], 'data-src') !== false) {
                return $matches[0];
            }
            return '<img' . $matches[1] 
                . ' src="' . boxmoe_lazy_load_images() . '"' 
                . ' data-src="' . $matches[3] . '"'
                . ' class="lazy"'
                . ' loading="lazy"';
        },
        $content);
    return $content;
}
if(!is_admin()){
    add_filter('the_content', 'boxmoe_lazy_content_load_images', 99);
}

// fancybox--------------------------boxmoe.com--------------------------
function boxmoe_fancybox_replace ($content) {
    global $post;
    $pattern = "/<a(.*?)href=('|\")([A-Za-z0-9\/_\.\~\:-]*?)(-\d+x\d+)?(\.(?:bmp|gif|jpeg|png|jpg|webp))('|\")([^\>]*?)>/i";
    $replacement = '<a$1href=$2$3$5$6$7 class="fancybox" data-fancybox="gallery" data-src="$3$5">';
    $content = preg_replace($pattern, $replacement, $content);
    return $content;
}
add_filter('the_content', 'boxmoe_fancybox_replace', 99);

// fancybox-erphpdown
//add_filter('the_content', 'erphpdownbuy_replace', 99);
function erphpdownbuy_replace ($content) {
	global $post;
	$pattern = "/<a(.*?)class=\"erphpdown-iframe erphpdown-buy\"(.*?)>/i";
	$replacement = '<a$1$2$3$4$5$6 class="fancybox" data-fancybox data-type="iframe" class="erphpdown-buy">';
	$content = preg_replace($pattern, $replacement, $content);
	return $content;
}

// 分页导航函数--------------------------boxmoe.com--------------------------
if ( ! function_exists( 'boxmoe_pagination' ) ) :
function boxmoe_pagination($query = null) {
    $paging_type = get_boxmoe('boxmoe_article_paging_type');
    if($paging_type == 'multi'){
        $p = 1;
        if ( is_singular() ) return;
        global $wp_query, $paged;
        $max_page = $wp_query->max_num_pages;
        echo '<div class="col-lg-12 col-md-12 pagenav">';
        echo '<nav class="d-flex justify-content-center">';
        echo '<ul class="pagination">';
        if ( empty( $paged ) ) $paged = 1;
        if($paged !== 1 ) p_link(0);
        $start = max(1, $paged - $p);
        $end = min($paged + ($p * 1), $max_page);
        if ($start > 1) {
            p_link(1);
            if ($start > 1) echo "<li class=\"page-item\"><a class=\"page-link\">···</a></li>";
        }
        for( $i = $start; $i <= $end; $i++ ) { 
            if ( $i > 0 && $i <= $max_page ) {
                $i == $paged ? print "<li class=\"page-item active\"><a class=\"page-link\" href=\"#\">{$i}</a></li>" : p_link( $i );
            }
        }
        if ($end < $max_page) {
            if ($end < $max_page - 1) echo "<li class=\"page-item\"><a class=\"page-link\">···</a></li>";
            p_link($max_page, '', 1);
        }
        echo '</ul>
        </nav>
      </div>';
    }elseif($paging_type == 'next'){
        global $wp_query;
        $query = $query ?: $wp_query;
        $current = max(1, get_query_var('paged'));
        $total = $query->max_num_pages;
        
        echo '<nav class="pagination-next-prev"><ul class="pagination justify-content-center">';
        if ($current > 1) {
            echo '<li class="page-item">';
            previous_posts_link('<span class="page-link"><i class="fa fa-arrow-left"></i> '.__('上一页', 'boxmoe').'</span>');
            echo '</li>';
        }
        if ($current < $total) {
            echo '<li class="page-item ms-2">';
            next_posts_link('<span class="page-link">'.__('下一页', 'boxmoe').' <i class="fa fa-arrow-right"></i></span>', $total);
            echo '</li>';
        }
        echo '</ul></nav>';
    }elseif($paging_type == 'loadmore'){
    }
}
function p_link( $i, $title = '', $w='' ) {
    if ( $title == '' ) $title = __('页', 'boxmoe-com')." {$i}";
    $itext = $i;
    if( $i == 0 ){
        $itext = __('<i class="fa fa-angle-double-left"></i>', 'boxmoe-com');
    }
    if( $w ){
        $itext = __('<i class="fa fa-angle-double-right"></i>', 'boxmoe-com');
    }
    echo "<li class=\"page-item\"><a class=\"page-link\" href='", esc_html( get_pagenum_link( $i ) ), "'>{$itext}</a></li>";
}
endif;


// 文章点赞数获取
function getPostLikes($postID) {
    $count_key = 'post_likes_count';
    $count = get_post_meta($postID, $count_key, true);
    if($count == ''){
        delete_post_meta($postID, $count_key);
        add_post_meta($postID, $count_key, '0');
        return "0";
    }
    return $count;
}

function boxmoe_post_like() {
    $post_id = isset($_POST['post_id']) ? absint($_POST['post_id']) : 0;
    
    if (!$post_id) {
        wp_send_json_error(['message' => 'Invalid post ID']);
        return;
    }

    if (!get_post($post_id)) {
        wp_send_json_error(['message' => '文章不存在']);
        return;
    }

    $user_ip = $_SERVER['REMOTE_ADDR'];
    $transient_key = 'post_like_' . $post_id . '_' . md5($user_ip);

    if (false === get_transient($transient_key)) {
        $count = (int)get_post_meta($post_id, 'post_likes_count', true);
        $count++;
        update_post_meta($post_id, 'post_likes_count', $count);
        set_transient($transient_key, '1', DAY_IN_SECONDS);
        
        wp_send_json_success([
            'count' => $count,
            'message' => '点赞成功'
        ]);
    } else {
        wp_send_json_error(['message' => '您已经点过赞了']);
    }
}

add_action('wp_ajax_post_like', 'boxmoe_post_like');
add_action('wp_ajax_nopriv_post_like', 'boxmoe_post_like');

// 检查文章是否被收藏
function isPostFavorited($post_id) {
    if (!is_user_logged_in()) return false;
    
    $user_id = get_current_user_id();
    $favorites = get_user_meta($user_id, 'user_favorites', true);
    
    if (!is_array($favorites)) {
        $favorites = array();
    }
    
    return in_array($post_id, $favorites);
}

// 处理文章收藏
function boxmoe_post_favorite() {
    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => '请先登录']);
        return;
    }

    $post_id = isset($_POST['post_id']) ? absint($_POST['post_id']) : 0;
    
    if (!$post_id) {
        wp_send_json_error(['message' => '无效的文章ID']);
        return;
    }

    if (!get_post($post_id)) {
        wp_send_json_error(['message' => '文章不存在']);
        return;
    }

    $user_id = get_current_user_id();
    $favorites = get_user_meta($user_id, 'user_favorites', true);
    
    if (!is_array($favorites)) {
        $favorites = array();
    }

    $is_favorited = in_array($post_id, $favorites);
    
    if ($is_favorited) {
        $favorites = array_diff($favorites, array($post_id));
        $message = '取消收藏成功';
        $status = false;
    } else {
        $favorites[] = $post_id;
        $message = '收藏成功';
        $status = true;
    }
    update_user_meta($user_id, 'user_favorites', array_values($favorites));
    wp_send_json_success([
        'message' => $message,
        'status' => $status
    ]);
}

add_action('wp_ajax_post_favorite', 'boxmoe_post_favorite');

// 处理删除收藏
function boxmoe_delete_favorite() {
    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => '请先登录']);
        return;
    }

    $post_id = isset($_POST['post_id']) ? absint($_POST['post_id']) : 0;
    
    if (!$post_id) {
        wp_send_json_error(['message' => '无效的文章ID']);
        return;
    }

    $user_id = get_current_user_id();
    $favorites = get_user_meta($user_id, 'user_favorites', true);
    
    if (!is_array($favorites)) {
        wp_send_json_error(['message' => '没有找到收藏记录']);
        return;
    }
    $favorites = array_diff($favorites, array($post_id));
        update_user_meta($user_id, 'user_favorites', array_values($favorites));
    wp_send_json_success([
        'message' => '删除收藏成功'
    ]);
}

add_action('wp_ajax_delete_favorite', 'boxmoe_delete_favorite');

// 前端发布说说
function boxmoe_submit_shuoshuo() {
    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => '请先登录']);
    }
    if (!current_user_can('publish_posts')) {
        wp_send_json_error(['message' => '当前账号没有作者权限，无法发布说说'], 403);
    }
    if (!check_ajax_referer('shuoshuo_nonce', 'security', false)) {
        wp_send_json_error(['message' => '安全验证失败，请刷新页面重试']);
    }
    $markdown = isset($_POST['markdown']) ? trim(wp_unslash($_POST['markdown'])) : '';
    $content = isset($_POST['content']) ? wp_kses_post(wp_unslash($_POST['content'])) : '';
    if ($markdown === '') {
        wp_send_json_error(['message' => '内容不能为空']);
    }
    if (mb_strlen($markdown) > 5000) {
        wp_send_json_error(['message' => '内容不能超过5000字']);
    }
    if (trim($content) === '') {
        wp_send_json_error(['message' => 'Markdown 内容无法解析']);
    }
    // 频率限制：30秒内只能发一条
    $user_id = get_current_user_id();
    $last_time = get_user_meta($user_id, '_last_shuoshuo_time', true);
    if ($last_time && (time() - $last_time) < 30) {
        wp_send_json_error(['message' => '发送太快了，请稍后再试']);
    }
    $shuoshuo_cat = get_category_by_slug('shuoshuo');
    if (!$shuoshuo_cat) {
        wp_send_json_error(['message' => '说说分类不存在，请先创建slug为shuoshuo的分类']);
    }
    $title = wp_trim_words(wp_strip_all_tags($content), 8, '...');
    if (empty($title)) $title = '无标题说说';
    $post_data = array(
        'post_title'   => $title,
        'post_content' => $content,
        'post_status'  => 'publish',
        'post_author'  => $user_id,
        'post_category' => array($shuoshuo_cat->term_id),
    );
    $post_id = wp_insert_post($post_data, true);
    if (is_wp_error($post_id)) {
        wp_send_json_error(['message' => '发布失败：' . $post_id->get_error_message()]);
    }
    update_post_meta($post_id, '_boxmoe_shuoshuo_markdown', $markdown);
    update_user_meta($user_id, '_last_shuoshuo_time', time());
    wp_send_json_success([
        'message' => '发布成功！',
        'post_id' => $post_id,
        'url' => get_permalink($post_id),
    ]);
}
add_action('wp_ajax_submit_shuoshuo', 'boxmoe_submit_shuoshuo');

/**
 * Load the Markdown publisher only where it can be displayed.
 */
function boxmoe_has_markdown_shuoshuo_block($blocks) {
    foreach ($blocks as $block) {
        if (
            ($block['blockName'] ?? '') === 'core/legacy-widget' &&
            ($block['attrs']['idBase'] ?? '') === 'boxmoe_widget_markdown_shuoshuo'
        ) {
            return true;
        }
        if (!empty($block['innerBlocks']) && boxmoe_has_markdown_shuoshuo_block($block['innerBlocks'])) {
            return true;
        }
    }
    return false;
}

function boxmoe_has_active_markdown_shuoshuo_widget() {
    if (is_active_widget(false, false, 'boxmoe_widget_markdown_shuoshuo', true)) {
        return true;
    }

    $sidebars = wp_get_sidebars_widgets();
    $block_widgets = get_option('widget_block', []);
    foreach ($sidebars as $sidebar => $widget_ids) {
        if ($sidebar === 'wp_inactive_widgets' || !is_array($widget_ids)) continue;
        foreach ($widget_ids as $widget_id) {
            if (!preg_match('/^block-(\d+)$/', $widget_id, $matches)) continue;
            $content = $block_widgets[(int)$matches[1]]['content'] ?? '';
            if ($content && boxmoe_has_markdown_shuoshuo_block(parse_blocks($content))) {
                return true;
            }
        }
    }
    return false;
}

function boxmoe_markdown_shuoshuo_assets() {
    if (!is_user_logged_in()) return;

    $publisher_page = is_page_template([
        'page/p-shuoshuo.php',
        'page/p-markdown-shuoshuo.php',
    ]);
    $publisher_widget = boxmoe_has_active_markdown_shuoshuo_widget();
    if (!$publisher_page && !$publisher_widget) return;

    wp_enqueue_style(
        'boxmoe-markdown-shuoshuo',
        boxmoe_theme_url() . '/assets/css/markdown-shuoshuo.css',
        [],
        filemtime(get_template_directory() . '/assets/css/markdown-shuoshuo.css')
    );
    wp_enqueue_script(
        'marked',
        'https://cdn.jsdelivr.net/npm/marked/marked.min.js',
        [],
        null,
        true
    );
    wp_enqueue_script(
        'boxmoe-markdown-shuoshuo',
        boxmoe_theme_url() . '/assets/js/markdown-shuoshuo.js',
        ['marked'],
        filemtime(get_template_directory() . '/assets/js/markdown-shuoshuo.js'),
        true
    );
}
add_action('wp_enqueue_scripts', 'boxmoe_markdown_shuoshuo_assets');

/**
 * Render a Markdown publisher shared by page templates and sidebar widgets.
 */
function boxmoe_render_markdown_shuoshuo_publisher($compact = false) {
    if (!is_user_logged_in()) {
        echo '<div class="markdown-shuoshuo-login text-center">';
        echo '<p class="text-muted">登录后可以发布说说</p>';
        echo '<a class="btn btn-primary btn-sm" href="' . esc_url(boxmoe_sign_in_link_page()) . '"><i class="fa fa-sign-in"></i> 登录</a>';
        echo '</div>';
        return;
    }

    $editor_id = wp_unique_id('markdown-shuoshuo-');
    $can_publish = current_user_can('publish_posts');
    ?>
    <form class="markdown-shuoshuo-editor<?php echo $compact ? ' is-compact' : ''; ?>" data-markdown-shuoshuo data-can-publish="<?php echo $can_publish ? 'true' : 'false'; ?>">
        <input type="hidden" name="security" value="<?php echo esc_attr(wp_create_nonce('shuoshuo_nonce')); ?>">
        <div class="markdown-shuoshuo-toolbar" role="toolbar" aria-label="Markdown 编辑工具">
            <button type="button" data-md-before="**" data-md-after="**" title="粗体"><i class="fa fa-bold"></i></button>
            <button type="button" data-md-before="*" data-md-after="*" title="斜体"><i class="fa fa-italic"></i></button>
            <button type="button" data-md-line="## " title="标题"><i class="fa fa-header"></i></button>
            <button type="button" data-md-line="> " title="引用"><i class="fa fa-quote-left"></i></button>
            <button type="button" data-md-line="- " title="无序列表"><i class="fa fa-list-ul"></i></button>
            <button type="button" data-md-before="[" data-md-after="](https://)" title="链接"><i class="fa fa-link"></i></button>
            <button type="button" data-md-before="`" data-md-after="`" title="行内代码"><i class="fa fa-code"></i></button>
            <button type="button" class="markdown-preview-toggle" data-md-preview title="预览"><i class="fa fa-eye"></i></button>
        </div>
        <textarea id="<?php echo esc_attr($editor_id); ?>" class="markdown-shuoshuo-input" name="markdown" rows="<?php echo $compact ? 5 : 12; ?>" maxlength="5000" placeholder="使用 Markdown 写点什么..."></textarea>
        <div class="markdown-shuoshuo-preview markdown-body" hidden></div>
        <div class="markdown-shuoshuo-footer">
            <span class="markdown-shuoshuo-count">0/5000</span>
            <span class="markdown-shuoshuo-message<?php echo $can_publish ? '' : ' is-error'; ?>" role="status"><?php echo $can_publish ? '' : '需要作者权限才能发布'; ?></span>
            <button type="submit" class="btn btn-primary btn-sm markdown-shuoshuo-submit"<?php disabled(!$can_publish); ?> title="<?php echo esc_attr($can_publish ? '发布说说' : '当前账号没有作者权限'); ?>">
                <i class="fa fa-paper-plane"></i> 发布
            </button>
        </div>
    </form>
    <?php if ($compact): ?>
    <a class="markdown-shuoshuo-page-link" href="<?php echo esc_url(home_url('/publish-shuoshuo/')); ?>">
        <span><i class="fa fa-pencil-square-o" aria-hidden="true"></i> 前往完整发布页面</span>
        <i class="fa fa-angle-right" aria-hidden="true"></i>
    </a>
    <?php endif; ?>
    <?php
}

function boxmoe_footnotes_assets() {
    if (is_single()) {
        wp_enqueue_script('boxmoe-footnotes', boxmoe_theme_url() . '/assets/js/footnotes.js', [], THEME_VERSION, true);
    }
}
add_action('wp_enqueue_scripts', 'boxmoe_footnotes_assets');


// ==================== 说说标题自动设置 ====================

/**
 * 发表/更新说说时，自动将标题设为 shuoshuo{ID}
 */
function boxmoe_shuoshuo_auto_title($post_id, $post, $update) {
    // 跳过自动保存和修订
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
    if (wp_is_post_revision($post_id)) return;
    if ($post->post_type !== 'post') return;

    // 检查是否在说说分类中
    $shuoshuo_cat = get_category_by_slug('shuoshuo');
    if (!$shuoshuo_cat) return;
    if (!has_category($shuoshuo_cat->term_id, $post_id)) return;

    // 标题已是 shuoshuo{id} 格式则跳过
    $expected = 'shuoshuo' . $post_id;
    if ($post->post_title === $expected) return;

    // 设为新标题
    remove_action('save_post', 'boxmoe_shuoshuo_auto_title', 20);
    wp_update_post(array(
        'ID'         => $post_id,
        'post_title' => $expected,
        'post_name'  => $expected,
    ));
    add_action('save_post', 'boxmoe_shuoshuo_auto_title', 20, 3);
}
add_action('save_post', 'boxmoe_shuoshuo_auto_title', 20, 3);

// ==================== 后台禁用说说分类选择 ====================

/**
 * 在文章编辑页禁用说说分类复选框（禁止将普通文章归为说说）
 * 说说本身也是 post，发布后再选分类会触发自动改标题
 */
function boxmoe_admin_disable_shuoshuo_cat() {
    $screen = get_current_screen();
    if (!$screen || $screen->base !== 'post') return;
    ?>
<style>
    /* 只会影响编辑页的分类勾选，不影响快速编辑等其他地方 */
    #categorydiv #shuoshuo-cat { pointer-events:none;opacity:0.5; }
    #categorydiv #shuoshuo-cat input { pointer-events:none; }
</style>
<script>
jQuery(function($) {
    // 找到说说分类的复选框并禁用
    var shuoshuoLabel = $('#categorydiv label').filter(function() {
        return $(this).text().trim() === '说说' || $(this).text().trim().startsWith('说说');
    });
    if (shuoshuoLabel.length) {
        shuoshuoLabel.css({opacity:0.5,pointerEvents:'none'});
        shuoshuoLabel.find('input').prop('disabled', true);
    }
});
</script>
    <?php
}
add_action('admin_head', 'boxmoe_admin_disable_shuoshuo_cat');
