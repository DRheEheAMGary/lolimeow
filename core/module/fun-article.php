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

// Step 1: save_post_post 最早介入，移除插件钩子 + 屏蔽 option
function boxmoe_shuoshuo_block_ai_save($post_id) {
    // 移除插件 save_post_post（无论是不是说说，尽早卸掉）
    remove_action('save_post_post', array('AI_Summary_Generator_Admin', 'ai_summary_generator_update_on_post_save'), 20);
    
    if (has_category('shuoshuo', $post_id)) {
        // 临时让 option 返回 no，防止其他入口调用
        add_filter('pre_option_ai_summary_generator_update_on_post_update', function(){ return 'no'; }, 9999);
        // 清理元数据
        delete_post_meta($post_id, '_ai_summary_generator_summary');
        delete_post_meta($post_id, '_ai_summary_generator_last_modified');
    }
}
add_action('save_post_post', 'boxmoe_shuoshuo_block_ai_save', 1);

// Step 2: 仪表盘快速发说说附加拦截
function boxmoe_shuoshuo_dashboard_block_ai() {
    add_filter('pre_option_ai_summary_generator_update_on_post_update', function(){ return 'no'; }, 9999);
    remove_action('save_post_post', array('AI_Summary_Generator_Admin', 'ai_summary_generator_update_on_post_save'), 20);
}

// Step 3: 前端：说说文章撤销插件的 AI 摘要（比插件晚执行，说说则回退原内容）
// the_content：插件优先级10 → 追加 AI blockquote → 我优先级15，说说时去掉
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
    if (!check_ajax_referer('shuoshuo_nonce', 'security', false)) {
        wp_send_json_error(['message' => '安全验证失败，请刷新页面重试']);
    }
    $content = isset($_POST['content']) ? wp_kses_post(trim($_POST['content'])) : '';
    if (empty($content)) {
        wp_send_json_error(['message' => '内容不能为空']);
    }
    if (mb_strlen(strip_tags($content)) > 5000) {
        wp_send_json_error(['message' => '内容不能超过5000字']);
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
    $title = wp_trim_words(strip_tags($content), 8, '...');
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
    update_user_meta($user_id, '_last_shuoshuo_time', time());
    wp_send_json_success(['message' => '发布成功！', 'post_id' => $post_id]);
}
add_action('wp_ajax_submit_shuoshuo', 'boxmoe_submit_shuoshuo');
