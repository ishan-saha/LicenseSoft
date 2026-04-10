document.addEventListener('DOMContentLoaded', function () {
    // Confirm dialogs for forms with data-confirm attribute
    document.querySelectorAll('form[data-confirm]').forEach(function (form) {
        form.addEventListener('submit', function (e) {
            if (!confirm(form.getAttribute('data-confirm'))) {
                e.preventDefault();
            }
        });
    });

    // Copy to clipboard buttons
    document.querySelectorAll('[data-copy-target]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var targetId = btn.getAttribute('data-copy-target');
            copyToClipboard(targetId);
        });
    });
});

function copyToClipboard(elementId) {
    var el = document.getElementById(elementId);
    if (!el) return;

    var text = el.textContent || el.innerText;
    text = text.trim();

    if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(text).then(function () {
            showCopyFeedback(el);
        });
    } else {
        var textarea = document.createElement('textarea');
        textarea.value = text;
        textarea.style.position = 'fixed';
        textarea.style.opacity = '0';
        document.body.appendChild(textarea);
        textarea.select();
        document.execCommand('copy');
        document.body.removeChild(textarea);
        showCopyFeedback(el);
    }
}

function showCopyFeedback(el) {
    var original = el.style.outline;
    el.style.outline = '2px solid #22c55e';
    setTimeout(function () {
        el.style.outline = original;
    }, 1000);
}
