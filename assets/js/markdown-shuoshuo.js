(function () {
    'use strict';

    function renderMarkdown(source) {
        if (typeof marked === 'undefined') return '';
        marked.setOptions({ breaks: true, gfm: true });
        var template = document.createElement('template');
        template.innerHTML = marked.parse(source);
        template.content.querySelectorAll('script, style, iframe, object, embed').forEach(function (node) {
            node.remove();
        });
        template.content.querySelectorAll('*').forEach(function (node) {
            Array.from(node.attributes).forEach(function (attribute) {
                var name = attribute.name.toLowerCase();
                var value = attribute.value.trim().toLowerCase();
                if (name.indexOf('on') === 0 || ((name === 'href' || name === 'src') && value.indexOf('javascript:') === 0)) {
                    node.removeAttribute(attribute.name);
                }
            });
        });
        return template.innerHTML;
    }

    function insertMarkdown(textarea, before, after, linePrefix) {
        var start = textarea.selectionStart;
        var end = textarea.selectionEnd;
        var selected = textarea.value.substring(start, end);
        var replacement;

        if (linePrefix) {
            var lineStart = textarea.value.lastIndexOf('\n', start - 1) + 1;
            var block = textarea.value.substring(lineStart, end || start);
            replacement = block.split('\n').map(function (line) {
                return linePrefix + line;
            }).join('\n');
            textarea.setRangeText(replacement, lineStart, end || start, 'end');
        } else {
            replacement = before + (selected || '文本') + after;
            textarea.setRangeText(replacement, start, end, 'select');
            textarea.selectionStart = start + before.length;
            textarea.selectionEnd = start + before.length + (selected || '文本').length;
        }

        textarea.focus();
        textarea.dispatchEvent(new Event('input', { bubbles: true }));
    }

    document.querySelectorAll('[data-markdown-shuoshuo]').forEach(function (form) {
        var textarea = form.querySelector('.markdown-shuoshuo-input');
        var preview = form.querySelector('.markdown-shuoshuo-preview');
        var count = form.querySelector('.markdown-shuoshuo-count');
        var message = form.querySelector('.markdown-shuoshuo-message');
        var submit = form.querySelector('.markdown-shuoshuo-submit');
        var canPublish = form.getAttribute('data-can-publish') === 'true';
        var previewing = false;
        var submitting = false;

        textarea.addEventListener('input', function () {
            count.textContent = textarea.value.length + '/5000';
            if (previewing) preview.innerHTML = renderMarkdown(textarea.value);
        });

        form.querySelectorAll('[data-md-before], [data-md-line]').forEach(function (button) {
            button.addEventListener('click', function () {
                insertMarkdown(
                    textarea,
                    button.getAttribute('data-md-before') || '',
                    button.getAttribute('data-md-after') || '',
                    button.getAttribute('data-md-line') || ''
                );
            });
        });

        form.querySelector('[data-md-preview]').addEventListener('click', function () {
            previewing = !previewing;
            preview.hidden = !previewing;
            textarea.hidden = previewing;
            this.classList.toggle('active', previewing);
            if (previewing) {
                preview.innerHTML = renderMarkdown(textarea.value) || '<p class="text-muted">暂无内容</p>';
            }
        });

        form.addEventListener('submit', function (event) {
            event.preventDefault();
            if (!canPublish) {
                message.textContent = '当前账号没有作者权限，无法发布说说';
                message.className = 'markdown-shuoshuo-message is-error';
                return;
            }
            if (submitting) return;
            var markdown = textarea.value.trim();
            if (!markdown) {
                message.textContent = '请输入内容';
                message.className = 'markdown-shuoshuo-message is-error';
                return;
            }

            var html = renderMarkdown(markdown);
            if (!html) {
                message.textContent = 'Markdown 解析器加载失败，请刷新后重试';
                message.className = 'markdown-shuoshuo-message is-error';
                return;
            }

            var data = new FormData();
            data.append('action', 'submit_shuoshuo');
            data.append('security', form.querySelector('[name="security"]').value);
            data.append('markdown', markdown);
            data.append('content', html);

            submitting = true;
            submit.disabled = true;
            submit.innerHTML = '<i class="fa fa-spinner fa-spin"></i> 发布中';
            message.textContent = '';

            fetch(ajax_object.ajaxurl, {
                method: 'POST',
                body: data,
                credentials: 'same-origin'
            })
                .then(function (response) { return response.json(); })
                .then(function (result) {
                    if (!result.success) throw new Error(result.data && result.data.message ? result.data.message : '发布失败');
                    message.textContent = result.data.message;
                    message.className = 'markdown-shuoshuo-message is-success';
                    textarea.value = '';
                    textarea.dispatchEvent(new Event('input', { bubbles: true }));
                    if (previewing) {
                        form.querySelector('[data-md-preview]').click();
                    }
                    window.setTimeout(function () { window.location.reload(); }, 600);
                })
                .catch(function (error) {
                    message.textContent = error.message || '网络错误，请重试';
                    message.className = 'markdown-shuoshuo-message is-error';
                })
                .finally(function () {
                    submitting = false;
                    submit.disabled = false;
                    submit.innerHTML = '<i class="fa fa-paper-plane"></i> 发布';
                });
        });
    });
})();
