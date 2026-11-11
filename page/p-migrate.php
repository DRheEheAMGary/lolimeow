<?php
/**
 * Template Name: 账号迁移说明
 * @package lolimeow
 *
 * 说明 2026/10/27 起停用 WordPress 本地账号、统一走 SSO。
 */
if (!defined('ABSPATH')) { echo 'Look your sister'; exit; }

$loginUrl    = function_exists('sso_login_link') ? sso_login_link('/') : wp_login_url();
$registerUrl = 'https://sso.dreamgary.cn/register/';
?>
<html <?php language_attributes(); ?>>
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
  <title>账号迁移说明 · <?php echo esc_html(get_bloginfo('name')); ?></title>
  <link rel="icon" href="<?php echo boxmoe_favicon(); ?>" type="image/x-icon">
  <style>
    .migrate-page ul { list-style-position: inside; padding-left: 0; margin-left: 0; }
    .migrate-page ul li { padding-left: 0; }
  </style>
  <?php wp_head(); ?>
</head>
<body>
<main>
  <div class="position-relative h-100 login_register_page">
    <div class="container d-flex flex-wrap justify-content-center min-vh-100 align-items-center w-lg-50 position-lg-absolute py-5">
      <div class="row justify-content-center">
        <div class="w-100 align-self-end col-12 mx-auto migrate-page" style="max-width: 30rem;">

          <div class="text-center mb-4">
            <a href="<?php echo esc_url(home_url('/')); ?>"><?php boxmoe_logo(); ?></a>
            <h2 class="mb-1 mt-3">账号迁移说明</h2>
            <p class="mb-0 text-muted">本站账号已并入 DRheEheAM 统一认证</p>
          </div>

          <div class="alert alert-warning" role="alert">
            自 <strong>2026 年 10 月 27 日</strong>起，本站不再提供 WordPress 账号注册与密码登录服务。
            现在所有登录都统一走 <strong>DRheEheAM 统一认证（SSO）</strong>。
          </div>

          <h5 class="mt-4 mb-2">现在怎么登录</h5>
          <p class="text-muted mb-2">
            在登录页点击「使用 DRheEheAM 账号登录」，会跳到统一认证完成登录，再自动回到本站。
          </p>
          <p class="text-muted mb-0">
            本站不再校验 WordPress 密码；站内密码登录入口已经关闭。
          </p>

          <h5 class="mt-4 mb-2">老 WordPress 账号怎么办</h5>
          <ul class="text-muted mb-2">
            <li>用<strong>与旧账号相同的邮箱</strong>注册或登录 SSO。</li>
            <li>首次通过 SSO 登录时，系统会按邮箱自动把本站旧账号绑定过去。</li>
            <li>绑定后，旧账号的文章、评论、权限都保留；旧密码作废，只能走 SSO。</li>
            <li>如果 SSO 用的是另一个邮箱，会被当成新用户，旧账号不会被绑定。</li>
          </ul>

          <h5 class="mt-4 mb-2">还没有 SSO 账号</h5>
          <p class="text-muted mb-2">
            先在统一认证注册（邮箱验证码），再回本站登录。首次登录会自动创建博客账号，并引导你完善资料。
          </p>

          <h5 class="mt-4 mb-2">资料与密码在哪改</h5>
          <ul class="text-muted mb-4">
            <li>密码、邮箱、昵称、头像：统一在 <strong>sso.dreamgary.cn</strong> 的账号中心管理。</li>
            <li>本站只保留个人主页地址、头像等展示信息。</li>
          </ul>

          <div class="d-grid gap-2">
            <a class="btn btn-primary w-100" href="<?php echo esc_url($loginUrl); ?>">
              <i class="fa fa-shield"></i> 使用 DRheEheAM 账号登录
            </a>
            <a class="btn btn-outline-primary w-100" href="<?php echo esc_url($registerUrl); ?>">
              还没有账号？去 SSO 注册
            </a>
            <a class="btn btn-link btn-sm text-muted" href="<?php echo esc_url(home_url('/')); ?>">
              返回首页
            </a>
          </div>

          <div class="text-center mt-5">
            <div class="small mb-3 mb-lg-0 text-body-tertiary">
              Copyright © 2025
              <span class="text-primary"><a href="<?php echo esc_url(home_url('/')); ?>"><?php echo esc_html(get_bloginfo('name')); ?></a></span>
              | Theme by
              <span class="text-primary"><a href="https://www.boxmoe.com">Boxmoe</a></span> powered by WordPress
            </div>
          </div>

        </div>
      </div>
    </div>
    <div class="position-fixed top-0 end-0 w-50 h-100 d-none d-lg-block vh-100" style="background-image: url(<?php echo get_boxmoe('boxmoe_user_login_bg') ? get_boxmoe('boxmoe_user_login_bg') : 'https://api.boxmoe.com/random.php'; ?>); background-position: center; background-repeat: no-repeat; background-size: cover;">
    </div>
  </div>
</main>
<?php wp_footer(); ?>
</body>
</html>
