<?php
/**
 * @link https://www.boxmoe.com
 * @package lolimeow
 */
//boxmoe.com===安全设置=阻止直接访问主题文件
if(!defined('ABSPATH')){echo'Look your sister';exit;}
?>
<?php if(get_boxmoe('boxmoe_blog_layout')=='two'): ?>
    <div class="col-lg-4 blog-sidebar">
          <div class="position-sticky top">
            <div class="offcanvas-lg offcanvas-end" id="blog-sidebar" tabindex="-1" aria-labelledby="blog-sidebar">
              <div class="offcanvas-header">
                <h5 class="offcanvas-title h4" id="blog-sidebar">
                <?php echo boxmoe_logo(); ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="offcanvas" data-bs-target="#blog-sidebar"></button>
              </div>
              <div class="offcanvas-body flex-column">
              <?php
                    // 用户主页贡献值
                    if (get_query_var('boxmoe_user_profile') && function_exists('boxmoe_get_contribution')):
                        $pu = boxmoe_get_profile_user(get_query_var('username'));
                        $profile_user_id = $pu ? $pu->ID : 0;
                        $contrib_hidden = ($profile_user_id && function_exists('boxmoe_can_view_content') && !boxmoe_can_view_content($profile_user_id, 'contribution_visibility'));
                        // 封禁用户遮盖
                        if ($profile_user_id && function_exists('boxmoe_is_user_banned') && boxmoe_is_user_banned($profile_user_id) && !current_user_can('manage_options')) {
                            $contrib_hidden = true;
                        }
                    ?>
                    <style>@keyframes contribIn{from{opacity:0;transform:translateY(12px);}to{opacity:1;transform:translateY(0);}}</style>
                    <?php if ($profile_user_id): ?>
                    <div class="widget widget-contribution" style="background:transparent;box-shadow:none;">
                        <div class="widget-title"><i class="fa fa-star"></i> 贡献值</div>
                        <?php
                        // 贡献构成直接读账本聚合（不再受旧 usermeta 日志 200 条上限影响）
                        $breakdown = function_exists('boxmoe_contrib_ledger_breakdown')
                            ? boxmoe_contrib_ledger_breakdown($profile_user_id, 30)
                            : array();
                        if (empty($breakdown) && boxmoe_get_contribution($profile_user_id) != 0) {
                            $breakdown = array('历史贡献（迁移基线）' => boxmoe_get_contribution($profile_user_id));
                        }
                        ?>
                        <div class="text-center py-2">
                            <?php
                            $level = boxmoe_get_level(boxmoe_get_contribution($profile_user_id));
                            $color_map = [0=>'#64748b',1=>'#64748b',2=>'#64748b',3=>'#4b5563',4=>'#4b5563',5=>'#2563eb',6=>'#2563eb',7=>'#2563eb',8=>'#b45309',9=>'#b45309',10=>'#b45309',11=>'#4f46e5',12=>'#4f46e5',13=>'#4f46e5',14=>'#4f46e5',15=>'#4f46e5',16=>'#e9d5ff',17=>'#e9d5ff',18=>'#e9d5ff',19=>'#e9d5ff',20=>'#e9d5ff',21=>'#e9d5ff',22=>'#e9d5ff',23=>'#e9d5ff',24=>'#e9d5ff',25=>'#e9d5ff'];
                            $tag_color = $level >= 26 ? '#92400e' : ($color_map[$level] ?? '#64748b');
                            ?>
                            <div style="font-size:2.5rem;font-weight:700;line-height:1;color:<?php echo $tag_color; ?>;"><?php echo $contrib_hidden ? '-' : number_format(boxmoe_get_contribution($profile_user_id), 2); ?></div>
                        </div>
                        <?php if (!empty($breakdown)): ?>
                        <hr>
                        <div class="small">
                            <?php foreach ($breakdown as $reason => $total): ?>
                            <div class="d-flex justify-content-between py-1" style="font-size:0.8rem;">
                                <span class="text-muted"><?php echo esc_html($reason); ?></span>
                                <span style="color:<?php echo $tag_color; ?>;"><?php echo $contrib_hidden ? '-' : number_format($total, 2); ?></span>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                        <?php if ($contrib_hidden): ?>
                        <div class="small text-center text-muted mt-2">抱歉，根据隐私设置，该用户拒绝显示贡献值</div>
                        <?php endif; ?>
                        <?php if (!$contrib_hidden && get_current_user_id() === $profile_user_id): ?>
                        <?php
                        $total = boxmoe_get_contribution($profile_user_id);
                        $level = boxmoe_get_level($total);
                        $next_min = boxmoe_get_level_min($level + 1);
                        ?>
                        <hr>
                        <div class="small">
                            <div class="d-flex justify-content-between align-items-center py-1">
                                <span class="text-muted">当前</span>
                                <span><?php echo boxmoe_get_user_tag_badge($profile_user_id); ?></span>
                            </div>
                            <div class="d-flex justify-content-between align-items-center py-1">
                                <span class="text-muted">下一级</span>
                                <span>
                                <?php
                                $next_level = $level + 1;
                                $next_tag = boxmoe_get_user_tag($profile_user_id);
                                $custom   = get_user_meta($profile_user_id, 'boxmoe_contrib_tag', true);
                                $tag_name_class = $custom ? 'tag-name custom' : 'tag-name';
                                $tl_class = ($next_level >= 26) ? 'tl-h' : ('tl-' . min($next_level, 25));
                                if ($next_level >= 301):
                                    echo '<span class="contrib-tag-badge ' . $tl_class . '"><span class="tag-level">lv.inf</span><span class="' . $tag_name_class . '">' . ucfirst($next_tag) . '</span></span>';
                                else:
                                    echo '<span class="contrib-tag-badge ' . $tl_class . '"><span class="tag-level">lv' . $next_level . '</span><span class="' . $tag_name_class . '">' . ucfirst($next_tag) . '</span></span>';
                                endif;
                                ?>
                                </span>
                            </div>
                            <div class="d-flex justify-content-between py-1">
                                <span class="text-muted">还需贡献</span>
                                <span style="color:<?php echo $tag_color; ?>;"><?php echo number_format(max(0, $next_min - $total), 2); ?></span>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                    <script>
                    (function() {
                        var contrib = document.querySelector('.widget-contribution');
                        if (!contrib) return;
                        function toggleContrib() {
                            var h = window.location.hash.replace('#', '');
                            if (!h || h === 'about') {
                            contrib.style.visibility = '';
                            contrib.style.opacity = '1';
                            contrib.style.animation = 'contribIn 0.35s ease';
                        } else {
                            contrib.style.visibility = 'hidden';
                            contrib.style.opacity = '0';
                        }
                        }
                        toggleContrib();
                        window.addEventListener('hashchange', toggleContrib);
                        window.addEventListener('popstate', function() {
                            setTimeout(toggleContrib, 50);
                        });
                        // 拦截 tab 点击
                        document.querySelectorAll('.profile-tab-link').forEach(function(a) {
                            a.addEventListener('click', function() {
                                setTimeout(toggleContrib, 50);
                            });
                        });
                    })();
                    </script>
                    <?php endif; ?>
              <?php
                    if (function_exists('dynamic_sidebar') && dynamic_sidebar('widget_site_sidebar')) : endif;
                    if (is_single()){
	                        if (function_exists('dynamic_sidebar') && dynamic_sidebar('widget_post_sidebar')) : endif; 
                        }else if (is_page()){
	                        if (function_exists('dynamic_sidebar') && dynamic_sidebar('widget_page_sidebar')) : endif; 
                        }else if (is_home()){
	                        if (function_exists('dynamic_sidebar') && dynamic_sidebar('widget_home_sidebar')) : endif; 
                        }
                ?>
              </div>
            </div>
          </div>
        </div>
<?php endif; ?>