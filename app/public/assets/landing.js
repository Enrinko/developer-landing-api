/**
 * Landing interactions: live /api/health probe in the hero terminal and the
 * contact form that renders the real API response in the "response" window.
 */
(function () {
    'use strict';

    /**
     * Pretty-print + minimal syntax highlight for a JSON value.
     * XSS contract: every character of the payload is HTML-escaped BEFORE any
     * markup is added, so the produced string is safe for innerHTML.
     */
    function highlightJson(value) {
        const json = JSON.stringify(value, null, 2)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');

        return json.replace(
            /("(?:[^"\\]|\\.)*")(\s*:)?|\b(-?\d+(?:\.\d+)?)\b|\b(true|false|null)\b/g,
            function (match, str, colon, num, keyword) {
                if (str !== undefined) {
                    return colon
                        ? '<span class="j-key">' + str + '</span>' + colon
                        : '<span class="j-str">' + str + '</span>';
                }
                if (num !== undefined) return '<span class="j-num">' + num + '</span>';
                return '<span class="j-null">' + keyword + '</span>';
            }
        );
    }

    /* --- Hero: live health check ------------------------------------- */
    const healthOutput = document.getElementById('health-output');
    if (healthOutput) {
        fetch('/api/health')
            .then(function (response) { return response.json(); })
            .then(function (data) { healthOutput.innerHTML = highlightJson(data); })
            .catch(function () { healthOutput.textContent = '// сервис не ответил — попробуйте обновить страницу'; });
    }

    /* --- Contact form -------------------------------------------------- */
    const form = document.getElementById('contact-form');
    if (!form) return;

    const submitBtn = document.getElementById('submit-btn');
    const statusLine = document.getElementById('response-status');
    const output = document.getElementById('response-output');
    const formStatus = document.getElementById('form-status');

    function clearFieldErrors() {
        form.querySelectorAll('.form__field').forEach(function (field) {
            field.classList.remove('invalid');
        });
        form.querySelectorAll('.form__error').forEach(function (el) {
            el.textContent = '';
        });
    }

    function showFieldErrors(errors) {
        Object.keys(errors || {}).forEach(function (name) {
            const errorEl = form.querySelector('[data-error-for="' + name + '"]');
            if (errorEl) {
                errorEl.textContent = errors[name].join(' ');
                errorEl.closest('.form__field').classList.add('invalid');
            }
        });
    }

    function setStatus(text, kind) {
        statusLine.textContent = text;
        statusLine.className = 't-status' + (kind ? ' ' + kind : '');
    }

    form.addEventListener('submit', function (event) {
        event.preventDefault();

        clearFieldErrors();
        formStatus.textContent = '';
        formStatus.className = 'form__status';
        submitBtn.disabled = true;
        setStatus('// отправляю запрос…', null);
        output.innerHTML = '';

        const payload = {
            name: form.elements.name.value,
            email: form.elements.email.value,
            phone: form.elements.phone.value,
            comment: form.elements.comment.value
        };

        fetch('/api/contact', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        })
            .then(function (response) {
                return response.json().then(function (data) {
                    return { status: response.status, retryAfter: response.headers.get('Retry-After'), data: data };
                });
            })
            .then(function (result) {
                output.innerHTML = highlightJson(result.data);

                if (result.status === 201) {
                    setStatus('HTTP/1.1 201 Created', 'ok');
                    formStatus.textContent = 'Сообщение отправлено — копия придёт вам на почту.';
                    formStatus.classList.add('ok');
                    form.reset();
                } else if (result.status === 422) {
                    setStatus('HTTP/1.1 422 Unprocessable Entity', 'err');
                    formStatus.textContent = 'Проверьте выделенные поля.';
                    formStatus.classList.add('err');
                    showFieldErrors(result.data.errors);
                } else if (result.status === 429) {
                    setStatus('HTTP/1.1 429 Too Many Requests', 'err');
                    formStatus.textContent = 'Слишком много запросов — попробуйте через '
                        + (result.retryAfter ? result.retryAfter + ' сек.' : 'пару минут.');
                    formStatus.classList.add('err');
                } else {
                    setStatus('HTTP/1.1 ' + result.status, 'err');
                    formStatus.textContent = 'Что-то пошло не так — попробуйте ещё раз позже.';
                    formStatus.classList.add('err');
                }
            })
            .catch(function () {
                setStatus('// сеть недоступна', 'err');
                formStatus.textContent = 'Не удалось связаться с сервером — проверьте соединение.';
                formStatus.classList.add('err');
            })
            .finally(function () {
                submitBtn.disabled = false;
            });
    });
})();
