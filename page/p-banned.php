<?php
/**
 * 封禁提示页 /user/banned
 * @package lolimeow
 */
if(!defined('ABSPATH')){echo'Look your sister';exit;}

get_header();
?>

<div class="<?php echo boxmoe_layout_setting(); ?> blog-post">
    <div class="post-single text-center py-7">
        <div class="mb-4">
            <i class="fa fa-ban" style="font-size:5rem;color:#dc3232;"></i>
        </div>
        <h2>账号已被封禁</h2>
        <p class="text-muted mt-3" style="max-width:400px;margin:0 auto;">
            你的账号已被管理员封禁，如有疑问请联系站点管理员。
        </p>
    </div>
</div>

<?php get_sidebar(); get_footer(); ?>