<?php
/**
 * Template Name: 账号与登录
 * @package lolimeow
 *
 * 说明本站账号与 DRheEheAM 统一认证（SSO）的对应关系。
 */
if (!defined('ABSPATH')) { echo 'Look your sister'; exit; }

$loginUrl    = function_exists('sso_login_link') ? sso_login_link('/') : wp_login_url();
$registerUrl = 'https://sso.dreamgary.cn/register/';
?>
<html <?php language_attributes(); ?>>
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
  <title>账号与登录 · <?php echo esc_html(get_bloginfo('name')); ?></title>
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
            <h2 class="mb-1 mt-3">账号与登录</h2>
            <p class="mb-0 text-muted">本站账号由 DRheEheAM 统一认证管理</p>
          </div>

          <div class="alert alert-info" role="alert">
            本站使用 <strong>DRheEheAM 统一认证（SSO）</strong> 登录，
            账号、密码与邮箱均由统一认证管理。
          </div>

          <h5 class="mt-4 mb-2">如何登录</h5>
          <p class="text-muted mb-2">
            在登录页点击「使用 DRheEheAM 账号登录」，会跳转到统一认证完成登录，随后自动回到本站。
          </p>

          <h5 class="mt-4 mb-2">账号与资料</h5>
          <ul class="text-muted mb-2">
            <li>密码、邮箱、昵称、头像在统一认证的账号中心管理。</li>
            <li>本站展示个人主页地址、头像等信息。</li>
          </ul>

          <h5 class="mt-4 mb-2">还没有账号</h5>
          <p class="text-muted mb-2">
            先在统一认证注册（邮箱验证码），再回本站登录。首次登录会自动创建博客账号，并引导完善资料。
          </p>

          <h5 class="mt-4 mb-2">已有博客账号</h5>
          <ul class="text-muted mb-4">
            <li>使用与博客账号关联的邮箱注册或登录统一认证，即可对应到已有博客账号。</li>
            <li>对应后，博客上的文章、评论、权限都保留。</li>
            <li>使用其他邮箱登录会创建新的博客账号。</li>
          </ul>

          <div class="d-grid gap-2">
            <a class="btn btn-primary w-100" href="<?php echo esc_url($loginUrl); ?>">
              <i class="fa fa-shield"></i> 使用 DRheEheAM 账号登录
            </a>
            <a class="btn btn-outline-primary w-100" href="<?php echo esc_url($registerUrl); ?>">
              还没有账号？去统一认证注册
            </a>
            <a class="btn btn-link btn-sm text-muted" href="<?php echo esc_url(home_url('/')); ?>">
              返回首页
            </a>
          </div>

          <div class="text-center mt-5">
            <div class="small mb-3 mb-lg-0 text-body-tertiary">
              Copyright © <?php echo esc_html(date_i18n('Y')); ?>
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
