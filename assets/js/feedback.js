(function () {
    'use strict';

    var form = document.getElementById('boxmoe-feedback-form');
    if (!form) return;

    var typeInputs = form.querySelectorAll('input[name="type"]');
    var conditionalFields = form.querySelectorAll('.feedback-conditional');
    var subject = form.querySelector('[name="subject"]');
    var textarea = form.querySelector('[name="message"]');
    var count = document.getElementById('feedback-message-count');
    var messageBox = document.getElementById('feedback-message-box');
    var submit = form.querySelector('.feedback-submit');
    var submitting = false;

    function updateFields() {
        var selected = form.querySelector('input[name="type"]:checked');
        var type = selected ? selected.value : 'issue';
        subject.placeholder = type === 'other' ? subject.getAttribute('data-other-placeholder') : subject.getAttribute('data-default-placeholder');
        conditionalFields.forEach(function (field) {
            var visible = field.getAttribute('data-feedback-types').split(' ').indexOf(type) !== -1;
            field.hidden = !visible;
            field.querySelectorAll('input, textarea, select').forEach(function (input) {
                input.disabled = !visible;
                input.required = visible && input.parentElement.querySelector('label span') !== null;
            });
        });
    }

    typeInputs.forEach(function (input) { input.addEventListener('change', updateFields); });
    textarea.addEventListener('input', function () { count.textContent = textarea.value.length; });
    updateFields();

    form.addEventListener('submit', function (event) {
        event.preventDefault();
        if (submitting || !form.reportValidity()) return;

        var token = form.querySelector('[name="cf-turnstile-response"]');
        if (!token || !token.value) {
            messageBox.className = 'is-error';
            messageBox.textContent = '请先完成人机验证';
            return;
        }

        submitting = true;
        submit.disabled = true;
        submit.innerHTML = '<i class="fa fa-spinner fa-spin"></i><span>提交中</span>';
        messageBox.textContent = '';

        fetch(ajax_object.ajaxurl, {
            method: 'POST',
            credentials: 'same-origin',
            body: new FormData(form)
        })
            .then(function (response) { return response.json(); })
            .then(function (result) {
                if (!result.success) throw new Error(result.data && result.data.message ? result.data.message : '提交失败');
                messageBox.className = 'is-success';
                messageBox.textContent = result.data.message;
                window.setTimeout(function () { window.location.reload(); }, 1200);
            })
            .catch(function (error) {
                messageBox.className = 'is-error';
                messageBox.textContent = error.message || '网络错误，请稍后重试';
                if (window.turnstile) window.turnstile.reset();
            })
            .finally(function () {
                submitting = false;
                submit.disabled = false;
                submit.innerHTML = '<i class="fa fa-paper-plane"></i><span>提交反馈</span>';
            });
    });
})();
