<?php
/**
 * 个人设置内容模板
 * @link https://www.boxmoe.com
 * @package lolimeow
 */
if(!defined('ABSPATH')){echo'Look your sister';exit;}

$user_id = get_current_user_id();
$user = wp_get_current_user();
$social_links = get_user_meta($user_id, 'user_social_links', true) ?: array('email'=>'','github'=>'','qq'=>'','wechat'=>'','weibo'=>'');
if (empty($social_links['email'])) $social_links['email'] = $user->user_email;
$last_login_time = get_user_meta($user_id, 'last_login_time', true) ?: '暂无记录';
$last_login_ip = get_user_meta($user_id, 'last_login_ip', true) ?: '暂无记录';
?>

<div class="user-profile-page">

    <!-- ====== Tab 导航（tag-cloud 风格，与用户主页一致） ====== -->
    <div class="single-category mb-3" style="display:flex;gap:10px;flex-wrap:wrap;">
        <a href="#profile" class="tag-cloud settings-tab-link active" data-tab="profile">
            <i class="fa fa-user-circle"></i>个人资料
        </a>
        <a href="#security" class="tag-cloud settings-tab-link" data-tab="security">
            <i class="fa fa-shield"></i>账号安全
        </a>
        <a href="#privacy" class="tag-cloud settings-tab-link" data-tab="privacy">
            <i class="fa fa-eye"></i>隐私设置
        </a>
    </div>

    <div class="post-single">

        <!-- ==================== Tab 1: 个人资料 ==================== -->
        <div class="settings-tab-pane active" data-tab="profile" id="settings-tab-profile">
            <form id="profileForm" class="settings-form" onsubmit="return false;">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">昵称</label>
                        <input type="text" class="form-control" value="<?php echo esc_attr($user->display_name); ?>" readonly disabled>
                        <div class="form-text">
                            昵称由统一认证管理，
                            <a href="https://sso.dreamgary.cn/account/" target="_blank" rel="noopener">前往修改</a>
                        </div>
                        <!-- 保留同名隐藏字段，主题原有的提交脚本读的就是它；后台会还原改动 -->
                        <input type="hidden" id="displayName" value="<?php echo esc_attr($user->display_name); ?>">
                    </div>
                    <div class="col-md-6">
                        <label for="userUrl" class="form-label">个人网站</label>
                        <input type="url" class="form-control" id="userUrl"
                               value="<?php echo esc_url($user->user_url); ?>"
                               placeholder="https://example.com">
                    </div>
                </div>

                <!-- 社交链接 -->
                <div class="mt-4">
                    <label class="form-label fw-semibold mb-3">社交链接 & 联系方式</label>
                    <?php
                    // 社交链接字段定义
                    $sl_fields = array(
                        array('id'=>'email',   'icon'=>'fa-envelope', 'placeholder'=>'展示用邮箱地址',   'type'=>'email', 'value'=>!empty($social_links['email']) ? $social_links['email'] : $user->user_email),
                        array('id'=>'github',  'icon'=>'fa-github',   'placeholder'=>'GitHub 用户名',        'type'=>'text',  'value'=>$social_links['github']),
                        array('id'=>'qq',      'icon'=>'fa-qq',       'placeholder'=>'QQ 号',              'type'=>'text',  'value'=>$social_links['qq']),
                        array('id'=>'wechat',  'icon'=>'fa-wechat',   'placeholder'=>'微信号',              'type'=>'text',  'value'=>$social_links['wechat']),
                        array('id'=>'weibo',   'icon'=>'fa-weibo',    'placeholder'=>'微博个人主页链接',    'type'=>'text',  'value'=>$social_links['weibo']),
                    );
                    foreach ($sl_fields as $f):
                        $show_key = $f['id'] . '_show';
                        $checked = isset($social_links[$show_key]) ? ($social_links[$show_key] === '1') : true;
                    ?>
                    <div class="social-link-row">
                        <span class="social-icon"><i class="fa <?php echo $f['icon']; ?>"></i></span>
                        <input type="<?php echo $f['type']; ?>" class="form-control" id="sl<?php echo ucfirst($f['id']); ?>"
                               value="<?php echo esc_attr($f['value']); ?>"
                               placeholder="<?php echo $f['placeholder']; ?>">
                        <label class="social-show-checkbox">
                            <input type="checkbox" id="sl<?php echo ucfirst($f['id']); ?>Show" <?php checked($checked); ?>>
                            <span class="checkmark"></span>
                            在主页展示
                        </label>
                    </div>
                    <?php endforeach; ?>
                </div>

                <div class="mt-4">
                    <button type="submit" class="btn btn-primary" id="profileSaveBtn">
                        <i class="fa fa-check"></i> 保存资料
                    </button>
                </div>
            </form>
        </div>

        <!-- ==================== Tab 2: 账号安全 ==================== -->
        <div class="settings-tab-pane" data-tab="security" id="settings-tab-security">
            <!-- 账号安全（由统一认证管理） -->
            <div class="mb-4">
                <h6 class="fw-semibold mb-3 pb-2" style="border-bottom:1px dashed var(--bs-border-color, #e0e0e0);">登录密码</h6>
                <div class="alert alert-info mb-0" style="border-radius:10px;">
                    <i class="fa fa-shield"></i>
                    你的账号由 <strong>DRheEheAM 统一认证</strong> 管理，
                    密码与邮箱请前往
                    <a href="https://sso.dreamgary.cn/account/" target="_blank" rel="noopener" class="alert-link">账号中心</a>
                    修改，本站不再单独保存。
                </div>
            </div>

            <!-- 登录记录 -->
            <div>
                <h6 class="fw-semibold mb-3 pb-2" style="border-bottom:1px dashed var(--bs-border-color, #e0e0e0);">最近登录记录</h6>
                <div class="d-flex flex-column gap-2">
                    <div class="d-flex align-items-center justify-content-between p-3 rounded-3">
                        <span class="text-muted small"><i class="fa fa-clock-o me-2"></i>最近登录时间</span>
                        <span class="fw-semibold small"><?php echo esc_html($last_login_time); ?></span>
                    </div>
                    <div class="d-flex align-items-center justify-content-between p-3 rounded-3">
                        <span class="text-muted small"><i class="fa fa-map-marker me-2"></i>最近登录 IP</span>
                        <span class="fw-semibold small"><?php echo esc_html($last_login_ip); ?></span>
                    </div>
                </div>
            </div>
        </div>

        <!-- ==================== Tab 3: 隐私设置 ==================== -->
        <div class="settings-tab-pane" data-tab="privacy" id="settings-tab-privacy">
            <form id="privacyForm" class="settings-form" onsubmit="return false;">
                <div class="d-flex flex-column gap-3">
                    <?php
                    $privacy_options = array(
                        array('id'=>'prProfileVisibility', 'icon'=>'fa-home', 'label'=>'个人主页可见性', 'desc'=>'控制谁可以查看您的个人主页', 'value'=>get_user_meta($user_id,'profile_visibility',true)),
                        array('id'=>'prAboutVisibility', 'icon'=>'fa-user', 'label'=>'简介可见性', 'desc'=>'控制谁可以查看您的个人简介', 'value'=>get_user_meta($user_id,'about_visibility',true)),
                        array('id'=>'prPostsVisibility', 'icon'=>'fa-file-text', 'label'=>'文章列表可见性', 'desc'=>'控制谁可以查看您的文章列表', 'value'=>get_user_meta($user_id,'posts_visibility',true)),
                        array('id'=>'prCommentsVisibility', 'icon'=>'fa-comments', 'label'=>'评论列表可见性', 'desc'=>'控制谁可以查看您的评论记录', 'value'=>get_user_meta($user_id,'comments_visibility',true)),
                        array('id'=>'prFavoritesVisibility', 'icon'=>'fa-heart', 'label'=>'收藏列表可见性', 'desc'=>'控制谁可以查看您的收藏内容', 'value'=>get_user_meta($user_id,'favorites_visibility',true)),
                        array('id'=>'prFollowingVisibility', 'icon'=>'fa-users', 'label'=>'关注列表可见性', 'desc'=>'控制谁可以查看您的关注列表', 'value'=>get_user_meta($user_id,'following_visibility',true)),
                        array('id'=>'prFollowersVisibility', 'icon'=>'fa-user-plus', 'label'=>'粉丝列表可见性', 'desc'=>'控制谁可以查看您的粉丝列表', 'value'=>get_user_meta($user_id,'followers_visibility',true)),
                    );
                    foreach ($privacy_options as $opt):
                    ?>
                    <div class="d-flex align-items-center justify-content-between p-3 rounded-3">
                        <div class="d-flex align-items-center gap-3">
                            <span class="d-flex align-items-center justify-content-center rounded-2" style="width:40px;height:40px;color:var(--bs-primary);">
                                <i class="fa <?php echo $opt['icon']; ?>"></i>
                            </span>
                            <div>
                                <strong style="font-size:0.9rem;"><?php echo $opt['label']; ?></strong>
                                <small class="text-muted d-block"><?php echo $opt['desc']; ?></small>
                            </div>
                        </div>
                        <select class="form-select" id="<?php echo $opt['id']; ?>" style="width:160px;">
                            <option value="public" <?php selected($opt['value'], 'public'); ?>>所有人可见</option>
                            <option value="members" <?php selected($opt['value'], 'members'); ?>>仅注册会员</option>
                            <option value="private" <?php selected($opt['value'], 'private'); ?>>仅自己</option>
                        </select>
                    </div>
                    <?php endforeach; ?>

                    <!-- 是否允许被关注 -->
                    <div class="d-flex align-items-center justify-content-between p-3 rounded-3">
                        <div class="d-flex align-items-center gap-3">
                            <span class="d-flex align-items-center justify-content-center rounded-2" style="width:40px;height:40px;color:var(--bs-primary);">
                                <i class="fa fa-user-plus"></i>
                            </span>
                            <div>
                                <strong style="font-size:0.9rem;">允许被关注</strong>
                                <small class="text-muted d-block">关闭后其他用户无法关注你</small>
                            </div>
                        </div>
                        <select class="form-select" id="prAllowFollow" style="width:160px;">
                            <option value="yes" <?php selected(get_user_meta($user_id,'boxmoe_allow_follow',true), 'yes'); ?>>允许</option>
                            <option value="no" <?php selected(get_user_meta($user_id,'boxmoe_allow_follow',true), 'no'); ?>>禁止</option>
                        </select>
                    </div>

                    <!-- 搜索引擎 -->
                    <div class="d-flex align-items-center justify-content-between p-3 rounded-3">
                        <div class="d-flex align-items-center gap-3">
                            <span class="d-flex align-items-center justify-content-center rounded-2" style="width:40px;height:40px;background:var(--bs-white);color:var(--bs-primary);box-shadow:0 1px 4px rgba(0,0,0,0.06);">
                                <i class="fa fa-search"></i>
                            </span>
                            <div>
                                <strong style="font-size:0.9rem;">搜索引擎索引</strong>
                                <small class="text-muted d-block">是否允许搜索引擎收录您的个人主页</small>
                            </div>
                        </div>
                        <select class="form-select" id="prSearchIndex" style="width:160px;">
                            <option value="yes" <?php selected(get_user_meta($user_id,'allow_search_index',true), 'yes'); ?>>允许收录</option>
                            <option value="no" <?php selected(get_user_meta($user_id,'allow_search_index',true), 'no'); ?>>禁止收录</option>
                        </select>
                    </div>
                </div>

                <div class="mt-4">
                    <button type="submit" class="btn btn-primary" id="privacySaveBtn">
                        <i class="fa fa-check"></i> 保存隐私设置
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
.settings-tab-pane { display:none; }
.settings-tab-pane.active { display:block; }
.user-profile-page .post-single { background:transparent !important; }
.social-show-checkbox { display:inline-flex;align-items:center;gap:6px;flex-shrink:0;cursor:pointer;font-size:0.8rem;color:#888;white-space:nowrap;user-select:none; }
.social-show-checkbox input { display:none; }
.social-show-checkbox .checkmark { width:18px;height:18px;border:2px solid #ccc;border-radius:4px;display:inline-flex;align-items:center;justify-content:center;transition:all 0.2s; }
.social-show-checkbox .checkmark::after { content:'';width:6px;height:10px;border:solid #fff;border-width:0 2px 2px 0;transform:rotate(45deg) translateY(-1px);display:none; }
.social-show-checkbox input:checked ~ .checkmark { background:var(--bs-primary);border-color:var(--bs-primary); }
.social-show-checkbox input:checked ~ .checkmark::after { display:block; }
@keyframes toastIn { from{opacity:0;transform:translateY(-10px);} to{opacity:1;transform:translateY(0);} }
@keyframes toastOut { from{opacity:1;} to{opacity:0;transform:translateY(-6px);} }
</style>

<script>
var _settingsNonce = '<?php echo wp_create_nonce('wp_rest'); ?>';
(function() {
    var tabs = document.querySelectorAll('.settings-tab-link');
    var panes = document.querySelectorAll('.settings-tab-pane');

    function activateTab(name) {
        panes.forEach(function(p) {
            p.classList.toggle('active', p.getAttribute('data-tab') === name);
        });
        tabs.forEach(function(a) {
            var isActive = a.getAttribute('data-tab') === name;
            a.style.background = isActive ? 'var(--bs-primary)' : '';
            a.style.color = isActive ? '#fff' : '';
        });
    }

    function getHashTab() {
        var h = window.location.hash.replace('#', '');
        return (h === 'profile' || h === 'security' || h === 'privacy') ? h : 'profile';
    }

    activateTab(getHashTab());

    tabs.forEach(function(a) {
        a.addEventListener('click', function(e) {
            e.preventDefault();
            var name = this.getAttribute('data-tab');
            var url = window.location.pathname + '#' + name;
            history.replaceState(null, '', url);
            activateTab(name);
        });
    });

    window.addEventListener('hashchange', function() {
        activateTab(getHashTab());
    });

    // ====== 表单提交 ======
    var API = '/wp-json/lolimeow/v1/settings';
    function nonce() { return _settingsNonce; }
    function toast(msg, ok) {
        var box = document.getElementById('settings-toast-box');
        if (!box) {
            box = document.createElement('div');
            box.id = 'settings-toast-box';
            box.style.cssText = 'position:fixed;top:20px;left:50%;transform:translateX(-50%);z-index:99999;display:flex;flex-direction:column;align-items:center;gap:8px;pointer-events:none;';
            document.body.appendChild(box);
        }
        var el = document.createElement('div');
        el.textContent = msg;
        el.style.cssText = 'padding:10px 28px;border-radius:8px;color:#fff;font-size:0.9rem;background:var(--bs-dark,#333);box-shadow:0 4px 20px rgba(0,0,0,0.15);animation:toastIn 0.35s ease,toastOut 0.35s ease 2.5s forwards;pointer-events:auto;';
        if (ok === false) el.style.borderLeft = '4px solid #f5576c';
        box.appendChild(el);
        setTimeout(function(){ el.remove(); }, 3000);
    }
    function loading(btn, on) {
        if (on) { btn._html = btn.innerHTML; btn.disabled = true; btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>保存中...'; }
        else { btn.disabled = false; btn.innerHTML = btn._html || btn.innerHTML; }
    }

    // 个人资料
    var pf = document.getElementById('profileForm');
    if (pf) pf.addEventListener('submit', function() {
        if (!this.checkValidity()) { this.classList.add('was-validated'); return; }
        var btn = document.getElementById('profileSaveBtn'); loading(btn, true);
        var body = { display_name: document.getElementById('displayName').value.trim(), user_url: document.getElementById('userUrl').value.trim(), social_links: { email: document.getElementById('slEmail').value.trim(), email_show: document.getElementById('slEmailShow').checked ? '1' : '0', github: document.getElementById('slGithub').value.trim(), github_show: document.getElementById('slGithubShow').checked ? '1' : '0', qq: document.getElementById('slQq').value.trim(), qq_show: document.getElementById('slQqShow').checked ? '1' : '0', wechat: document.getElementById('slWechat').value.trim(), wechat_show: document.getElementById('slWechatShow').checked ? '1' : '0', weibo: document.getElementById('slWeibo').value.trim(), weibo_show: document.getElementById('slWeiboShow').checked ? '1' : '0' } };
        fetch(API + '/profile', { method: 'PUT', headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': nonce() }, credentials: 'same-origin', body: JSON.stringify(body) })
            .then(function(r){ return r.json(); }).then(function(d){ toast(d.message, d.success); }).catch(function(){ toast('保存失败', false); }).finally(function(){ loading(btn, false); });
    });


    // 隐私
    var prv = document.getElementById('privacyForm');
    if (prv) prv.addEventListener('submit', function() {
        var btn = document.getElementById('privacySaveBtn'); loading(btn, true);
        var body = { profile_visibility: document.getElementById('prProfileVisibility').value, about_visibility: document.getElementById('prAboutVisibility').value, posts_visibility: document.getElementById('prPostsVisibility').value, comments_visibility: document.getElementById('prCommentsVisibility').value, favorites_visibility: document.getElementById('prFavoritesVisibility').value, following_visibility: document.getElementById('prFollowingVisibility').value, followers_visibility: document.getElementById('prFollowersVisibility').value, allow_follow: document.getElementById('prAllowFollow').value, allow_search_index: document.getElementById('prSearchIndex').value };
        fetch(API + '/privacy', { method: 'PUT', headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': nonce() }, credentials: 'same-origin', body: JSON.stringify(body) })
            .then(function(r){ return r.json(); }).then(function(d){ toast(d.message, d.success); }).catch(function(){ toast('保存失败', false); }).finally(function(){ loading(btn, false); });
    });
})();
</script>
