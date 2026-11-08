<?php
/**
 * Template Name: 贡献排行榜
 * @link https://www.boxmoe.com
 * @package lolimeow
 */
if(!defined('ABSPATH')){echo'Look your sister';exit;}

$GLOBALS['boxmoe_breadcrumb'] = 'CONTRIBUTORS';
get_header();

global $wpdb;
$limit = 50;
$rows = $wpdb->get_results($wpdb->prepare(
    "SELECT user_id, meta_value FROM $wpdb->usermeta WHERE meta_key = %s ORDER BY CAST(meta_value AS DECIMAL(20,2)) DESC LIMIT %d",
    'boxmoe_contribution', $limit
));
?>

<div class="<?php echo boxmoe_layout_setting(); ?> blog-post">
    <div class="post-single">
        <h2 class="mb-4"><i class="fa fa-trophy"></i> 贡献排行榜</h2>

        <?php if (empty($rows)): ?>
            <p class="text-muted text-center py-5">暂无排行数据</p>
        <?php else: ?>
        <div class="contrib-rank-list">
            <?php
            $rank = 1;
            $colors = [1 => '#fbbf24', 2 => '#94a3b8', 3 => '#d97706'];
            foreach ($rows as $row):
                $uid   = (int)$row->user_id;
                $user  = get_userdata($uid);
                if (!$user) continue;
                $hidden = (function_exists('boxmoe_can_view_content') && !boxmoe_can_view_content($uid, 'contribution_visibility'));
                $val   = $hidden ? '-' : number_format((float)$row->meta_value, 2);
                $name  = $user->display_name;
                $rc    = isset($colors[$rank]) ? $colors[$rank] : 'var(--bs-secondary-color)';
            ?>
            <div class="d-flex align-items-center gap-3 py-3 border-bottom">
                <span class="fw-bold" style="width:28px;text-align:center;font-size:1.1rem;color:<?php echo $rc; ?>;"><?php echo $rank; ?></span>
                <a href="<?php echo esc_url(boxmoe_get_user_profile_url($uid)); ?>" class="text-reset text-decoration-none">
                    <?php echo boxmoe_avatar_img($uid, 36, array('class' => 'rounded-circle', 'alt' => '')); ?>
                </a>
                <span class="flex-fill">
                    <a href="<?php echo esc_url(boxmoe_get_user_profile_url($uid)); ?>" class="text-reset text-decoration-none fw-semibold"><?php echo esc_html($name); ?></a>
                    <?php echo boxmoe_get_user_tag_badge($uid); ?>
                </span>
                <span style="color:var(--bs-primary);font-weight:700;font-size:1rem;"><?php echo $val; ?></span>
            </div>
            <?php $rank++; endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php get_sidebar(); get_footer(); ?>