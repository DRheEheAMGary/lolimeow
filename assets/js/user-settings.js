/**
 * 个人设置页面 - 前端交互
 * @link https://www.boxmoe.com
 * @package lolimeow
 */
(function () {
    'use strict';

    var API_BASE = '/wp-json/lolimeow/v1/settings';

    function getNonce() {
        return wpApiSettings && wpApiSettings.nonce ? wpApiSettings.nonce : '';
    }

    function toast(message, success) {
        if (typeof showToast === 'function') { showToast(message, success); return; }
        var bg = success === false ? 'linear-gradient(135deg, #f093fb, #f5576c)' : 'linear-gradient(135deg, #667eea, #764ba2)';
        var el = document.createElement('div');
        el.textContent = message;
        el.style.cssText = 'position:fixed;top:20px;right:20px;z-index:99999;padding:12px 24px;border-radius:8px;color:#fff;font-size:14px;background:' + bg + ';box-shadow:0 4px 15px rgba(0,0,0,0.2);';
        document.body.appendChild(el);
        setTimeout(function () { el.remove(); }, 3000);
    }

    function setBtnLoading(btn, loading) {
        if (loading) {
            btn._origHTML = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>保存中...';
        } else {
            btn.disabled = false;
            btn.innerHTML = btn._origHTML || btn.innerHTML;
        }
    }

    // ==================== Tab 切换（与用户主页 tag-cloud 风格一致） ====================
    var tabs = document.querySelectorAll('.settings-tab-link');
    var panes = document.querySelectorAll('.settings-tab-pane');

    function activateTab(name) {
        panes.forEach(function (p) {
            p.style.display = p.getAttribute('data-tab') === name ? '' : 'none';
        });
        tabs.forEach(function (a) {
            var isActive = a.getAttribute('data-tab') === name;
            a.style.background = isActive ? 'var(--bs-primary)' : '';
            a.style.color = isActive ? '#fff' : '';
        });
    }

    tabs.forEach(function (a) {
        a.addEventListener('click', function (e) {
            e.preventDefault();
            var name = this.getAttribute('data-tab');
            var url = window.location.pathname + '#' + name;
            history.replaceState(null, '', url);
            activateTab(name);
        });
    });

    // 页面加载时根据 hash 激活对应 tab
    (function () {
        var h = window.location.hash.replace('#', '');
        var valid = ['profile', 'security', 'privacy'];
        activateTab(valid.indexOf(h) !== -1 ? h : 'profile');
    })();

    // ==================== 个人资料表单 ====================
    var profileForm = document.getElementById('profileForm');
    if (profileForm) {
        profileForm.addEventListener('submit', function () {
            if (!this.checkValidity()) { this.classList.add('was-validated'); return; }
            var btn = document.getElementById('profileSaveBtn');
            setBtnLoading(btn, true);

            var body = {
                display_name: document.getElementById('displayName').value.trim(),
                description: document.getElementById('userDescription').value.trim(),
                user_url: document.getElementById('userUrl').value.trim(),
                social_links: {
                    github: document.getElementById('slGithub').value.trim(),
                    qq: document.getElementById('slQQ').value.trim(),
                    wechat: document.getElementById('slWechat').value.trim(),
                    weibo: document.getElementById('slWeibo').value.trim()
                }
            };

            fetch(API_BASE + '/profile', {
                method: 'PUT',
                headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': getNonce() },
                credentials: 'same-origin',
                body: JSON.stringify(body)
            })
                .then(function (r) { return r.json(); })
                .then(function (data) { toast(data.message, data.success); })
                .catch(function () { toast('保存失败，请重试', false); })
                .finally(function () { setBtnLoading(btn, false); });
        });
    }

    // ==================== 密码表单 ====================
    var passwordForm = document.getElementById('passwordForm');
    if (passwordForm) {
        passwordForm.addEventListener('submit', function () {
            if (!this.checkValidity()) { this.classList.add('was-validated'); return; }
            var newPw = document.getElementById('newPassword').value;
            var confirmPw = document.getElementById('confirmPassword').value;
            if (newPw !== confirmPw) { toast('新密码与确认密码不一致', false); return; }
            if (newPw.length < 6) { toast('新密码长度至少需要6个字符', false); return; }

            var btn = document.getElementById('passwordSaveBtn');
            setBtnLoading(btn, true);

            var body = {
                old_password: document.getElementById('oldPassword').value,
                new_password: newPw,
                confirm_password: confirmPw
            };

            fetch(API_BASE + '/password', {
                method: 'PUT',
                headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': getNonce() },
                credentials: 'same-origin',
                body: JSON.stringify(body)
            })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    toast(data.message, data.success);
                    if (data.success) { passwordForm.reset(); passwordForm.classList.remove('was-validated'); }
                })
                .catch(function () { toast('保存失败，请重试', false); })
                .finally(function () { setBtnLoading(btn, false); });
        });
    }

    // ==================== 邮箱验证码 ====================
    var sendCodeBtn = document.getElementById('sendEmailCodeBtn');
    var codeTimer = null;
    var countdown = 0;

    if (sendCodeBtn) {
        sendCodeBtn.addEventListener('click', function () {
            if (countdown > 0) return;
            var email = document.getElementById('newEmail').value.trim();
            if (!email || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
                toast('请先输入有效的新邮箱地址', false); return;
            }

            countdown = 60;
            sendCodeBtn.disabled = true;
            sendCodeBtn.textContent = countdown + 's 后重发';
            codeTimer = setInterval(function () {
                countdown--;
                if (countdown <= 0) { clearInterval(codeTimer); sendCodeBtn.disabled = false; sendCodeBtn.textContent = '获取验证码'; }
                else { sendCodeBtn.textContent = countdown + 's 后重发'; }
            }, 1000);

            fetch('/wp-json/lolimeow/v1/settings/email-code', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': getNonce() },
                credentials: 'same-origin',
                body: JSON.stringify({ email: email })
            })
                .then(function (r) { return r.json(); })
                .then(function (data) { toast(data.message, data.success); })
                .catch(function () { toast('发送失败，请重试', false); });
        });
    }

    // ==================== 邮箱表单 ====================
    var emailForm = document.getElementById('emailForm');
    if (emailForm) {
        emailForm.addEventListener('submit', function () {
            if (!this.checkValidity()) { this.classList.add('was-validated'); return; }
            var btn = document.getElementById('emailSaveBtn');
            setBtnLoading(btn, true);

            var body = {
                new_email: document.getElementById('newEmail').value.trim(),
                verification_code: document.getElementById('emailCode').value.trim()
            };

            fetch(API_BASE + '/email', {
                method: 'PUT',
                headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': getNonce() },
                credentials: 'same-origin',
                body: JSON.stringify(body)
            })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    toast(data.message, data.success);
                    if (data.success) { emailForm.reset(); emailForm.classList.remove('was-validated'); }
                })
                .catch(function () { toast('保存失败，请重试', false); })
                .finally(function () { setBtnLoading(btn, false); });
        });
    }

    // ==================== 隐私设置表单 ====================
    var privacyForm = document.getElementById('privacyForm');
    if (privacyForm) {
        privacyForm.addEventListener('submit', function () {
            var btn = document.getElementById('privacySaveBtn');
            setBtnLoading(btn, true);

            var body = {
                profile_visibility: document.getElementById('prProfileVisibility').value,
                posts_visibility: document.getElementById('prPostsVisibility').value,
                comments_visibility: document.getElementById('prCommentsVisibility').value,
                favorites_visibility: document.getElementById('prFavoritesVisibility').value,
                allow_search_index: document.getElementById('prSearchIndex').value
            };

            fetch(API_BASE + '/privacy', {
                method: 'PUT',
                headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': getNonce() },
                credentials: 'same-origin',
                body: JSON.stringify(body)
            })
                .then(function (r) { return r.json(); })
                .then(function (data) { toast(data.message, data.success); })
                .catch(function () { toast('保存失败，请重试', false); })
                .finally(function () { setBtnLoading(btn, false); });
        });
    }

})();
