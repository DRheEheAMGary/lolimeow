<?php
/**
 * Template Name: 个人设置页面 (/usersettings)
 * @link https://www.boxmoe.com
 * @package lolimeow
 */
if(!defined('ABSPATH')){echo'Look your sister';exit;}

if (!is_user_logged_in()) {
    wp_safe_redirect(home_url('/sign-in'));
    exit;
}

$GLOBALS['boxmoe_breadcrumb'] = 'USER SETTINGS';
get_header();
?>

<link rel="stylesheet" href="<?php echo boxmoe_theme_url(); ?>/assets/css/user_center.css">

<div class="<?php echo boxmoe_layout_setting(); ?> blog-post user-profile-page">
    <?php require_once(get_template_directory() . '/page/template/user-settings.php'); ?>
</div>

<?php
get_sidebar();
get_footer();
?>
