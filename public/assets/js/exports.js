/**
 * Exportaciones screen: lets the user preview how many PUBLICADO records
 * match the current filters before triggering the actual file download,
 * and gives basic feedback/error handling around both actions.
 */
(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', function () {
        const form = document.getElementById('export-form');
        const countBtn = document.getElementById('export-count-btn');
        const submitBtn = document.getElementById('export-submit-btn');
        const feedback = document.getElementById('export-feedback');
        if (!form || !countBtn || !submitBtn || !feedback) return;

        function setFeedback(message, variant) {
            feedback.textContent = message;
            feedback.className = 'small text-' + (variant || 'muted');
        }

        countBtn.addEventListener('click', function () {
            const params = new URLSearchParams(new FormData(form));
            const countUrl = form.action.replace(/\/export$/, '/export/count') + '?' + params.toString();
            setFeedback('Consultando…', 'muted');
            countBtn.disabled = true;
            fetch(countUrl, { headers: { Accept: 'application/json' } })
                .then(function (response) {
                    if (!response.ok) throw new Error('http_' + response.status);
                    return response.json();
                })
                .then(function (data) {
                    if (!data || !data.success) throw new Error('bad_response');
                    const n = Number(data.count) || 0;
                    setFeedback(
                        n + (n === 1 ? ' registro publicado encontrado.' : ' registros publicados encontrados.'),
                        n > 0 ? 'success' : 'warning'
                    );
                })
                .catch(function () {
                    setFeedback('No se pudo consultar el conteo. Intente nuevamente.', 'danger');
                })
                .finally(function () {
                    countBtn.disabled = false;
                });
        });

        form.addEventListener('submit', function () {
            setFeedback('Preparando descarga…', 'muted');
            submitBtn.disabled = true;
            // The download is a plain navigation, so there is no completion event to
            // listen for; re-enable shortly after so a failed/blocked download doesn't
            // leave the button stuck.
            setTimeout(function () { submitBtn.disabled = false; }, 2500);
        });
    });
})();