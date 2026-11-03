<?php
/**
 * Template Name: 反馈中心
 *
 * @package lolimeow
 */
if (!defined('ABSPATH')) { echo 'Look your sister'; exit; }
get_header();
$user = wp_get_current_user();
?>
<div class="col-lg-10 mx-auto blog-post">
    <section class="feedback-page blog-border">
        <header class="feedback-header">
            <div>
                <h1><?php the_title(); ?></h1>
                <p>选择反馈类型并填写必要信息，提交内容将发送给站主。</p>
            </div>
            <i class="fa fa-commenting-o" aria-hidden="true"></i>
        </header>

        <form id="boxmoe-feedback-form" class="feedback-form" data-logged-in="<?php echo is_user_logged_in() ? 'true' : 'false'; ?>">
            <input type="hidden" name="action" value="boxmoe_submit_feedback">
            <input type="hidden" name="security" value="<?php echo esc_attr(wp_create_nonce('boxmoe_feedback')); ?>">

            <fieldset class="feedback-type-grid">
                <legend>反馈类型</legend>
                <?php
                $icons = ['issue'=>'bug','suggestion'=>'lightbulb-o','report_user'=>'user-times','report_content'=>'flag','author_application'=>'pencil-square-o','other'=>'ellipsis-h'];
                foreach (boxmoe_feedback_types() as $key => $label):
                    $locked = !is_user_logged_in() && in_array($key, boxmoe_feedback_login_required_types(), true);
                ?>
                <label class="feedback-type<?php echo $locked ? ' is-locked' : ''; ?>">
                    <input type="radio" name="type" value="<?php echo esc_attr($key); ?>"<?php checked($key, 'issue'); ?><?php disabled($locked); ?>>
                    <i class="fa fa-<?php echo esc_attr($icons[$key]); ?>"></i>
                    <span><?php echo esc_html($label); ?></span>
                    <?php if ($locked): ?><small>需登录</small><?php endif; ?>
                </label>
                <?php endforeach; ?>
            </fieldset>

            <?php if (!is_user_logged_in()): ?>
            <div class="feedback-login-note"><i class="fa fa-info-circle"></i> 举报和作者申请需要<a href="<?php echo esc_url(boxmoe_sign_in_link_page()); ?>">登录</a>后提交。</div>
            <?php endif; ?>

            <div class="feedback-fields">
                <div class="feedback-field">
                    <label for="feedback-subject">主题 <span>*</span></label>
                    <input id="feedback-subject" type="text" name="subject" maxlength="100" required placeholder="简要说明本次反馈" data-default-placeholder="简要说明本次反馈" data-other-placeholder="请填写其他反馈的主题">
                </div>
                <div class="feedback-field">
                    <label for="feedback-email">联系邮箱 <span>*</span></label>
                    <input id="feedback-email" type="email" name="email" required value="<?php echo esc_attr($user->user_email ?? ''); ?>" placeholder="用于必要时联系你">
                </div>
                <div class="feedback-field feedback-conditional" data-feedback-types="report_user">
                    <label for="feedback-target-user">被举报用户 <span>*</span></label>
                    <input id="feedback-target-user" type="text" name="target_user" placeholder="用户名或用户主页地址">
                </div>
                <div class="feedback-field feedback-conditional" data-feedback-types="report_content">
                    <label for="feedback-related-url">被举报内容地址 <span>*</span></label>
                    <input id="feedback-related-url" type="url" name="related_url" placeholder="文章、说说或评论链接">
                </div>
                <div class="feedback-field feedback-conditional" data-feedback-types="issue suggestion report_user other">
                    <label for="feedback-evidence-url">相关页面或证据链接</label>
                    <input id="feedback-evidence-url" type="url" name="related_url" placeholder="可选，填写公开可访问的链接">
                </div>
                <div class="feedback-field feedback-conditional" data-feedback-types="author_application">
                    <label for="feedback-expertise">擅长领域 <span>*</span></label>
                    <input id="feedback-expertise" type="text" name="expertise" placeholder="例如：后端开发、摄影、游戏评测">
                </div>
                <div class="feedback-field feedback-conditional" data-feedback-types="author_application">
                    <label for="feedback-portfolio">作品链接 <span>*</span></label>
                    <input id="feedback-portfolio" type="url" name="portfolio_url" placeholder="个人主页、文章或作品集链接">
                </div>
                <div class="feedback-field feedback-field-wide">
                    <label for="feedback-message">详细说明 <span>*</span></label>
                    <textarea id="feedback-message" name="message" rows="8" minlength="20" maxlength="5000" required placeholder="请描述发生了什么、期望结果，以及有助于处理的信息"></textarea>
                    <small><span id="feedback-message-count">0</span>/5000</small>
                </div>
            </div>

            <div class="feedback-actions">
                <div class="feedback-turnstile">
                    <?php if (function_exists('cfturnstile_field_show')): ?>
                        <?php cfturnstile_field_show('', '', 'boxmoe-feedback', '-feedback-' . wp_rand()); ?>
                    <?php else: ?>
                        <p class="feedback-error">人机验证服务暂不可用。</p>
                    <?php endif; ?>
                </div>
                <div id="feedback-message-box" role="status"></div>
                <button type="submit" class="btn btn-primary feedback-submit"<?php disabled(!function_exists('cfturnstile_field_show')); ?>>
                    <i class="fa fa-paper-plane"></i><span>提交反馈</span>
                </button>
            </div>
        </form>
    </section>
</div>
<?php get_footer(); ?>
