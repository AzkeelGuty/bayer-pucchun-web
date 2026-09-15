document.addEventListener('DOMContentLoaded', () => {
    const form = document.querySelector('[data-documents-form]');
    if (form) {
        const body = form.querySelector('[data-details-body]');
        const add = form.querySelector('[data-add-detail]');
        const template = body.querySelector('[data-detail-row]').cloneNode(true);
        const refresh = () => {
            const count = body.querySelectorAll('[data-detail-row]').length;
            body.querySelectorAll('[data-remove-detail]').forEach(button => button.disabled = count === 1);
            add.disabled = count >= 200;
            form.querySelector('[data-detail-feedback]').textContent = `${count} ${count === 1 ? 'producto' : 'productos'}. Máximo 200 líneas.`;
        };
        add.addEventListener('click', () => {
            if (body.children.length >= 200) return;
            const indexes = [...body.querySelectorAll('[name]')].map(field => Number(field.name.match(/^details\[(\d+)\]/)?.[1] ?? -1));
            const index = Math.max(-1, ...indexes) + 1;
            const row = template.cloneNode(true);
            row.querySelectorAll('.invalid-feedback').forEach(node => node.remove());
            row.querySelectorAll('input,select').forEach(field => {
                const oldId = field.id;
                field.name = field.name.replace(/details\[\d+\]/, `details[${index}]`);
                field.id = field.id.replace(/details-\d+-/, `details-${index}-`);
                row.querySelectorAll('label').forEach(label => { if(label.htmlFor === oldId) label.htmlFor = field.id; });
                field.value = field.name.endsWith('[valor_unitario]') ? '0' : '';
                field.classList.remove('is-invalid'); field.setAttribute('aria-invalid','false'); field.removeAttribute('aria-describedby');
            });
            body.appendChild(row); refresh(); row.querySelector('select').focus();
        });
        body.addEventListener('click', event => {
            const button = event.target.closest('[data-remove-detail]');
            if (!button || body.children.length <= 1) return;
            const row = button.closest('[data-detail-row]');
            const focusRow = row.nextElementSibling || row.previousElementSibling;
            row.remove(); refresh(); focusRow?.querySelector('select').focus();
        });
        form.addEventListener('submit', () => {
            const save = form.querySelector('[data-save]'); save.disabled = true; save.textContent = 'Guardando…';
        });
        window.addEventListener('pageshow', () => { const save=form.querySelector('[data-save]');save.disabled=false;save.textContent='Guardar borrador'; });
        refresh(); document.querySelector('[data-error-summary]')?.focus();
    }
    const dialog = document.querySelector('[data-workflow-dialog]');
    if (dialog) {
        document.querySelectorAll('[data-workflow-target]').forEach(button => button.addEventListener('click', () => {
            const target=button.dataset.workflowTarget;
            dialog.querySelector('[data-workflow-state]').value=target;
            dialog.querySelector('#workflow-title').textContent=button.dataset.workflowLabel;
            const reason=dialog.querySelector('textarea');reason.value='';reason.required=['OBSERVADO','ANULADO'].includes(target);
            dialog.querySelector('[data-reason-wrap]').hidden=!reason.required;
            dialog.showModal();
        }));
        dialog.querySelector('[data-close-dialog]').addEventListener('click', () => dialog.close());
    }
});
