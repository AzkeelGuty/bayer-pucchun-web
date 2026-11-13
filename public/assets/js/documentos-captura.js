(() => {
    const init = () => {
    const form = document.querySelector('[data-documents-form]');
    if (form) {
        // La referencia identifica la operación financiera, no es el número SUNAT.
        // Explicar el campo según el medio elegido sin hacerlo obligatorio.
        const paymentMethod = form.querySelector('#doc-medio-pago');
        const paymentReference = form.querySelector('#doc-referencia-pago');
        const paymentHelp = form.querySelector('[data-payment-reference-help]');
        const paymentHints = {
            EFECTIVO: ['No requerido para efectivo', 'En efectivo puedes dejarlo vacío. Es un dato opcional de control comercial.'],
            YAPE: ['Ej.: número de operación Yape', 'Ingresa el número de operación que muestra Yape al confirmar el pago.'],
            PLIN: ['Ej.: número de operación Plin', 'Ingresa el número de operación que muestra Plin al confirmar el pago.'],
            TRANSFERENCIA: ['Ej.: número de operación bancaria', 'Ingresa el código o identificador de la transferencia bancaria.'],
            TARJETA: ['Ej.: código de autorización', 'Ingresa el código de autorización o referencia del pago con tarjeta.'],
            OTRO: ['Ej.: referencia de la operación', 'Ingresa el identificador disponible para este medio de pago.']
        };
        const refreshPaymentHelp = () => {
            if (!paymentMethod || !paymentReference || !paymentHelp) return;
            const [placeholder, description] = paymentHints[paymentMethod.value] ||
                ['N.º de operación, si corresponde', 'Código que identifica el pago. Es opcional y no reemplaza el número del comprobante.'];
            paymentReference.placeholder = placeholder;
            paymentHelp.textContent = description;
        };
        paymentMethod?.addEventListener('change', refreshPaymentHelp);
        refreshPaymentHelp();

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

        // El número mostrado es una vista previa: el servidor reserva el definitivo
        // durante INSERT bajo bloqueo transaccional, incluso con usuarios simultáneos.
        if (form.hasAttribute('data-auto-number-form')) {
            const type = form.querySelector('[name="header[tipo_documento_id]"]');
            const number = form.querySelector('[data-internal-number]');
            const endpoint = form.dataset.numberPreviewUrl;
            let requestId = 0;
            const preview = async () => {
                if (!type || !number || !endpoint) return;
                const thisRequest = ++requestId;
                number.value = 'Calculando...';
                if (!type.value) { number.value = ''; return; }
                try {
                    const separator = endpoint.includes('?') ? '&' : '?';
                    const response = await fetch(endpoint + separator + 'tipo_documento_id=' + encodeURIComponent(type.value),
                        {headers: {'Accept': 'application/json'}, credentials: 'same-origin', cache: 'no-store'});
                    if (!response.ok) throw new Error('Vista previa no disponible');
                    const data = await response.json();
                    if (thisRequest === requestId) number.value = String(data.numero || '');
                } catch (_) {
                    if (thisRequest === requestId) number.value = 'Se asignará al guardar';
                }
            };
            type?.addEventListener('change', preview);
        }

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

            if (seller && (force || !seller.value)) {
                window.BP_SearchableSelects?.setOption?.(seller, sellerId ? {
                    value: sellerId,
                    label: option.dataset.sellerLabel || sellerId,
                    meta: {}
                } : null);
            }
            if (branch && (force || !branch.value)) {
                window.BP_SearchableSelects?.setOption?.(branch, branchId ? {
                    value: branchId,
                    label: option.dataset.branchLabel || branchId,
                    meta: {}
                } : null);
            }
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
            const unitId = option?.dataset.unitId || '';
            const unitLabel = option?.dataset.unitLabel || unitId;
            if (window.BP_SearchableSelects?.setOption) {
                window.BP_SearchableSelects.setOption(unit, unitId ? {
                    value: unitId,
                    label: unitLabel,
                    meta: {}
                } : null, false);
            } else {
                unit.value = unitId;
            }
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
