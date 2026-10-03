(() => {
    const init = () => {
    const form = document.querySelector('[data-documents-form]');
    if (form) {
        const body = form.querySelector('[data-details-body]');
        const add = form.querySelector('[data-add-detail]');
        const template = body.querySelector('[data-detail-row]').cloneNode(true);

        // Si la búsqueda incremental alcanzó a mejorar la fila antes de cargar este
        // script (posible durante navegación interna), conservar una plantilla limpia.
        template.querySelectorAll('.incremental-select').forEach(wrapper => {
            const nativeSelect = wrapper.querySelector('select.incremental-select-native');
            if (!nativeSelect) return;
            nativeSelect.classList.remove('incremental-select-native');
            nativeSelect.removeAttribute('aria-hidden');
            nativeSelect.removeAttribute('tabindex');
            nativeSelect.removeAttribute('data-search-enhanced');
            wrapper.replaceWith(nativeSelect);
        });

        const client = form.querySelector('[name="header[cliente_id]"]');
        const seller = form.querySelector('[name="header[vendedor_id]"]');
        const branch = form.querySelector('[name="header[sucursal_id]"]');
        const applyClientSuggestions = (force = false) => {
            if (!client) return;
            const option = client.selectedOptions?.[0];
            if (!option || !option.value) {
                if (force) {
                    if (seller) seller.value = '';
                    if (branch) branch.value = '';
                }
                return;
            }

            const sellerId = option.dataset.sellerId || '';
            const branchId = option.dataset.branchId || '';

            if (seller && (force || !seller.value)) seller.value = sellerId;
            if (branch && (force || !branch.value)) branch.value = branchId;
        };

        const applyProductUnit = (row, force = false) => {
            if (!row) return;
            const product = row.querySelector('select[name$="[producto_id]"]');
            const unit = row.querySelector('select[name$="[unidad_id]"]');
            if (!product || !unit) return;

            unit.classList.add('auto-unit-select');
            unit.setAttribute('aria-readonly', 'true');
            unit.tabIndex = -1;

            if (!force && unit.value) return;
            const option = product.selectedOptions?.[0];
            unit.value = option?.dataset.unitId || '';
        };

        const mergeDuplicateProducts = () => {
            const seen = new Map();
            [...body.querySelectorAll('[data-detail-row]')].forEach(row => {
                if (!row.isConnected) return;
                const product = row.querySelector('select[name$="[producto_id]"]');
                if (!product?.value) return;

                if (!seen.has(product.value)) {
                    seen.set(product.value, row);
                    return;
                }

                const first = seen.get(product.value);
                const firstQty = first.querySelector('input[name$="[cantidad]"]');
                const rowQty = row.querySelector('input[name$="[cantidad]"]');
                const a = Math.max(1, Number.parseInt(firstQty?.value || '1', 10) || 1);
                const b = Math.max(1, Number.parseInt(rowQty?.value || '1', 10) || 1);
                if (firstQty) firstQty.value = String(a + b);
                row.remove();
                firstQty?.focus({preventScroll: true});
            });
            refresh();
        };

        const refresh = () => {
            const count = body.querySelectorAll('[data-detail-row]').length;
            body.querySelectorAll('[data-remove-detail]').forEach(button => button.disabled = count === 1);
            add.disabled = count >= 200;
            const feedback = form.querySelector('[data-detail-feedback]');
            if (feedback) {
                feedback.textContent = `${count} ${count === 1 ? 'producto' : 'productos'}. Máximo 200 líneas.`;
            }
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
                if (field.name.endsWith('[cantidad]')) field.value = '1';
                else if (field.name.endsWith('[valor_unitario]')) field.value = '0';
                else field.value = '';
                field.classList.remove('is-invalid'); field.setAttribute('aria-invalid','false'); field.removeAttribute('aria-describedby');
            });
            body.appendChild(row); refresh(); row.querySelector('select').focus();
        });
        if (client) {
            client.addEventListener('change', () => applyClientSuggestions(true));
            applyClientSuggestions(false);
        }

        body.querySelectorAll('[data-detail-row]').forEach(row => applyProductUnit(row, false));
        body.addEventListener('change', event => {
            const product = event.target.closest('select[name$="[producto_id]"]');
            if (!product) return;
            applyProductUnit(product.closest('[data-detail-row]'), true);
            mergeDuplicateProducts();
        });

        body.addEventListener('click', event => {
            const button = event.target.closest('[data-remove-detail]');
            if (!button || body.children.length <= 1) return;
            const row = button.closest('[data-detail-row]');
            const focusRow = row.nextElementSibling || row.previousElementSibling;
            row.remove(); refresh(); focusRow?.querySelector('select').focus();
        });
        form.addEventListener('submit', () => {
            mergeDuplicateProducts();
            const save = form.querySelector('[data-save]'); save.disabled = true; save.textContent = 'Guardando…';
        });
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
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init, {once:true});
    } else {
        init();
    }
})();
