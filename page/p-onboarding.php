<?php
/**
 * Template Name: 完善账号资料
 * @package lolimeow
 *
 * SSO 首次登录后的资料引导页。
 * 由 mu-plugins/sso-login.php 在 sso_onboarded != 1 时强制跳转过来。
 */
if (!defined('ABSPATH')) { echo 'Look your sister'; exit; }

if (!is_user_logged_in()) {
    wp_safe_redirect(home_url('/sign-in/'));
    exit;
}

$ssoUserId = get_current_user_id();
$u         = wp_get_current_user();

$err = isset($_GET['err']) ? sanitize_text_field(wp_unslash((string) $_GET['err'])) : '';

// 头像：本地传过的优先，否则用 SSO 的
$avatar = (string) get_user_meta($ssoUserId, 'user_avatar', true);
if ($avatar === '') {
    $avatar = (string) get_user_meta($ssoUserId, 'sso_picture', true);
}
if ($avatar === '') {
    $avatar = get_avatar_url($ssoUserId, ['size' => 200]);
}
?>
<html <?php language_attributes(); ?>>
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
  <title>完善资料 · <?php echo esc_html(get_bloginfo('name')); ?></title>
  <link rel="icon" href="<?php echo boxmoe_favicon(); ?>" type="image/x-icon">
  <?php wp_head(); ?>
</head>
<body>
<main>
  <div class="position-relative h-100 login_register_page">
    <div class="container d-flex flex-wrap justify-content-center min-vh-100 align-items-center py-5">
      <div class="row justify-content-center w-100">
        <div class="col-12 col-md-8 col-lg-6">

          <div class="text-center mb-4">
            <a href="<?php echo esc_url(home_url('/')); ?>"><?php boxmoe_logo(); ?></a>
            <h2 class="mb-1 mt-3">完善你的账号</h2>
            <p class="mb-0 text-muted">还差一步就可以开始使用</p>
          </div>

          <?php if ($err !== ''): ?>
            <div class="alert alert-danger mb-3"><?php echo esc_html($err); ?></div>
          <?php endif; ?>

          <form method="post" enctype="multipart/form-data"
                action="<?php echo esc_url(admin_url('admin-post.php')); ?>">

            <input type="hidden" name="action" value="sso_onboard">
            <?php wp_nonce_field('sso_onboard'); ?>

            <!-- 头像 -->
            <div class="text-center mb-4">
              <label for="avatarInput" style="cursor:pointer;display:inline-block;position:relative">
                <img id="avatarPreview" src="<?php echo esc_url($avatar); ?>" alt="头像"
                     style="width:104px;height:104px;border-radius:50%;object-fit:cover;
                            border:3px solid var(--bs-border-color,#e6e6e6)">
                <span style="position:absolute;right:-4px;bottom:-4px;width:32px;height:32px;
                             border-radius:50%;background:var(--bs-primary,#2f88c9);color:#fff;
                             display:flex;align-items:center;justify-content:center">
                  <i class="fa fa-camera"></i>
                </span>
              </label>
              <input type="file" id="avatarInput" name="avatar" accept="image/*" class="d-none">
              <div class="text-muted small mt-2">点击头像更换 · 可选，不上传就用统一认证的头像</div>
            </div>

            <!-- 昵称（由统一认证管理） -->
            <div class="mb-3">
              <label class="form-label">昵称</label>
              <input type="text" class="form-control" value="<?php echo esc_attr($u->display_name); ?>" readonly disabled>
              <div class="form-text">显示在文章、评论和用户主页上，由统一认证管理</div>
            </div>

            <!-- 用户名 -->
            <div class="mb-3">
              <label for="userLogin" class="form-label">用户名 <span class="text-danger">*</span></label>
              <input type="text" class="form-control" id="userLogin" name="user_login"
                     value="<?php echo esc_attr($u->user_login); ?>" required maxlength="60">
              <div class="form-text">用于个人主页地址，注册后不可修改</div>
            </div>

            <!-- 邮箱（只读） -->
            <div class="mb-4">
              <label class="form-label">邮箱</label>
              <input type="email" class="form-control" value="<?php echo esc_attr($u->user_email); ?>" readonly disabled>
              <div class="form-text">由统一认证管理，需要修改请前往 sso.dreamgary.cn</div>
            </div>

            <button type="submit" class="btn btn-primary w-100" id="onboardBtn">完成，开始使用</button>
          </form>

        </div>
      </div>
    </div>
  </div>
</main>

<script>
(function () {
  var input   = document.getElementById('avatarInput');
  var preview = document.getElementById('avatarPreview');
  if (!input || !preview) return;

  input.addEventListener('change', function () {
    var file = this.files && this.files[0];
    if (!file) return;
    if (file.size > 2 * 1024 * 1024) {
      alert('图片不能超过 2 MB');
      this.value = '';
      return;
    }
    var reader = new FileReader();
    reader.onload = function (e) { preview.src = e.target.result; };
    reader.readAsDataURL(file);
  });

  var form = input.closest('form');
  if (form) {
    form.addEventListener('submit', function () {
      var btn = document.getElementById('onboardBtn');
      if (btn) { btn.disabled = true; btn.textContent = '保存中…'; }
    });
  }
})();
</script>

<?php wp_footer(); ?>
</body>
</html>
