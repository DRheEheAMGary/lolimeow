(function init() {
    var links = document.querySelectorAll('sup.fn a[href^="#"]');
    if (!links.length) { setTimeout(init, 300); return; }

    links.forEach(function(a) {
        var id = a.getAttribute('href').substring(1);
        var fn = document.getElementById(id);
        if (!fn) return;
        a.style.cursor = 'help';

        // 获取脚注HTML内容（去掉回链）
        var html = fn.innerHTML.replace(/<a[^>]*aria-label="[^"]*跳转到[^"]*"[^>]*>.*?<\/a>\s*$/, '').trim();

        var tip = document.createElement('span');
        tip.className = 'fn-tooltip';
        tip.innerHTML = html;
        tip.style.cssText = 'display:none;position:fixed;z-index:99999;background:var(--bs-body-bg,#fff);color:var(--bs-body-color,#333);padding:10px 16px;border-radius:8px;font-size:0.82rem;max-width:380px;line-height:1.6;border:1px solid var(--bs-border-color,#ddd);box-shadow:0 4px 16px rgba(0,0,0,0.12);word-break:break-word;';
        tip.querySelectorAll('a').forEach(function(la) { la.target = '_blank'; });
        document.body.appendChild(tip);

        var hideTimer;
        function showTip() {
            clearTimeout(hideTimer);
            var r = a.getBoundingClientRect();
            tip.style.display = 'block';
            tip.style.left = Math.min(r.left, window.innerWidth - 400) + 'px';
            tip.style.top = (r.bottom + 6) + 'px';
        }
        function hideTip() {
            hideTimer = setTimeout(function() { tip.style.display = 'none'; }, 150);
        }

        a.addEventListener('mouseenter', showTip);
        a.addEventListener('mouseleave', hideTip);
        tip.addEventListener('mouseenter', showTip);
        tip.addEventListener('mouseleave', hideTip);
    });
})();
