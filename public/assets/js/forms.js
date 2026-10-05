/**
 * Formularios de captura optimizados.
 * Los catálogos grandes se consultan bajo demanda mediante /lookups.
 */
(function () {
    'use strict';

    const selectedOption = (select) => {
        const option = select?.selectedOptions?.[0];
        return option && option.value ? option : null;
    };

    const setOption = (select, value, label, dispatch = true) => {
        if (!select) return;
        window.BP_SearchableSelects?.setOption?.(select, value ? {
            value: String(value),
            label: String(label || value),
            meta: {},
        } : null, dispatch);
        if (!window.BP_SearchableSelects?.setOption) {
            select.value = value ? String(value) : '';
            if (dispatch) select.dispatchEvent(new Event('change', {bubbles:true}));
        }
    };

    function wireUbigeo(scope) {
        const depSelect = scope.querySelector('[data-role="departamento"]');
        const provSelect = scope.querySelector('[data-role="provincia"]');
        const distSelect = scope.querySelector('[data-role="distrito"]');
        if (!depSelect || !provSelect || !distSelect) return;

        depSelect.addEventListener('change', () => {
            setOption(provSelect, '', '', false);
            setOption(distSelect, '', '', false);
        });
        provSelect.addEventListener('change', () => {
            setOption(distSelect, '', '', false);
        });
    }

    function clientMeta(clienteSelect) {
        const option = selectedOption(clienteSelect);
        if (!option) return null;
        return {
            sellerId: option.dataset.sellerId || '',
            sellerLabel: option.dataset.sellerLabel || '',
            branchId: option.dataset.branchId || '',
            branchLabel: option.dataset.branchLabel || '',
            departmentId: option.dataset.departmentId || '',
            departmentLabel: option.dataset.departmentLabel || '',
            provinceId: option.dataset.provinceId || '',
            provinceLabel: option.dataset.provinceLabel || '',
            districtId: option.dataset.districtId || '',
            districtLabel: option.dataset.districtLabel || '',
        };
    }

    function applyClientDefaults(clienteSelect, force = false) {
        const form = clienteSelect.closest('form');
        const meta = clientMeta(clienteSelect);
        if (!form || !meta) return;

        const seller = form.querySelector('[name="vendedor_id"]');
        const branch = form.querySelector('[name="sucursal_id"]');
        const dep = form.querySelector('[data-role="departamento"]');
        const prov = form.querySelector('[data-role="provincia"]');
        const dist = form.querySelector('[data-role="distrito"]');

        if (seller && (force || !seller.value) && meta.sellerId) {
            setOption(seller, meta.sellerId, meta.sellerLabel || meta.sellerId);
        }
        if (branch && (force || !branch.value) && meta.branchId) {
            setOption(branch, meta.branchId, meta.branchLabel || meta.branchId);
        }

        const geoEmpty = !dep?.value && !prov?.value && !dist?.value;
        if (dep && prov && dist && (force || geoEmpty) &&
            meta.departmentId && meta.provinceId && meta.districtId) {
            setOption(dep, meta.departmentId, meta.departmentLabel || meta.departmentId, false);
            setOption(prov, meta.provinceId, meta.provinceLabel || meta.provinceId, false);
            setOption(dist, meta.districtId, meta.districtLabel || meta.districtId, false);
            window.BP_SearchableSelects?.refresh?.(dep);
            window.BP_SearchableSelects?.refresh?.(prov);
            window.BP_SearchableSelects?.refresh?.(dist);
        }
    }

    function wireClienteSuggestions(clienteSelect) {
        clienteSelect.addEventListener('change', () => applyClientDefaults(clienteSelect, true));
        if (clienteSelect.value) applyClientDefaults(clienteSelect, false);
    }

    function wireDestinationEditor(scope) {
        const form = scope.closest('form');
        const clienteSelect = form?.querySelector('[data-role="cliente"]');
        const depSelect = scope.querySelector('[data-role="departamento"]');
        const provSelect = scope.querySelector('[data-role="provincia"]');
        const distSelect = scope.querySelector('[data-role="distrito"]');
        const fields = scope.querySelector('[data-destination-fields]');
        const toggle = scope.querySelector('[data-destination-toggle]');
        const summary = scope.querySelector('[data-destination-summary]');
        const note = scope.querySelector('[data-destination-note]');
        const saveClient = scope.querySelector('[data-destination-save-client]');
        const endpoint = scope.dataset.clientLocationUrl || '';
        if (!clienteSelect || !depSelect || !provSelect || !distSelect || !fields || !toggle || !summary) return;

        let manual = false;

        const meta = () => clientMeta(clienteSelect);
        const clientHasDestination = client => Boolean(
            client?.departmentId && client?.provinceId && client?.districtId
        );
        const selectedText = select => selectedOption(select)?.textContent.trim() || '';

        function refresh() {
            const hasClient = Boolean(clienteSelect.value);
            const client = meta();
            const automatic = clientHasDestination(client);
            const complete = Boolean(depSelect.value && provSelect.value && distSelect.value);

            if (!hasClient) {
                manual = false;
                fields.hidden = true;
                toggle.hidden = true;
                summary.textContent = 'Selecciona un cliente para completar el destino.';
                if (note) note.textContent = 'La ubicación se completa automáticamente cuando existe en la ficha del cliente.';
                if (saveClient) saveClient.hidden = true;
                return;
            }

            if (complete) {
                summary.textContent = [selectedText(distSelect), selectedText(provSelect), selectedText(depSelect)]
                    .filter(Boolean).join(' · ');
                fields.hidden = !manual;
                toggle.hidden = !automatic;
                toggle.textContent = manual ? 'Restaurar destino del cliente' : 'Cambiar destino';
                if (note) note.textContent = manual
                    ? 'Destino ajustado para esta guía.'
                    : (automatic ? 'Destino completado desde la ficha del cliente.' : 'Destino definido para esta guía.');

                if (saveClient) {
                    const differs = !automatic
                        || String(client.departmentId) !== String(depSelect.value)
                        || String(client.provinceId) !== String(provSelect.value)
                        || String(client.districtId) !== String(distSelect.value);
                    saveClient.hidden = !differs;
                    saveClient.innerHTML = automatic
                        ? '<i class="bi bi-link-45deg"></i> Actualizar ubicación del cliente'
                        : '<i class="bi bi-link-45deg"></i> Guardar ubicación en cliente';
                }
                return;
            }

            manual = true;
            fields.hidden = false;
            toggle.hidden = true;
            summary.textContent = 'Completa el destino de entrega.';
            if (saveClient) saveClient.hidden = true;
            if (note) note.textContent = automatic
                ? 'Puedes completar el destino o restaurar la ubicación registrada del cliente.'
                : 'Este cliente no tiene una ubicación completa registrada.';
        }

        function restoreAutomaticDestination() {
            const client = meta();
            if (!clientHasDestination(client)) return false;
            manual = false;
            setOption(depSelect, client.departmentId, client.departmentLabel || client.departmentId, false);
            setOption(provSelect, client.provinceId, client.provinceLabel || client.provinceId, false);
            setOption(distSelect, client.districtId, client.districtLabel || client.districtId, false);
            [depSelect,provSelect,distSelect].forEach(select => window.BP_SearchableSelects?.refresh?.(select));
            refresh();
            return true;
        }

        saveClient?.addEventListener('click', async () => {
            if (!endpoint || !clienteSelect.value || !depSelect.value || !provSelect.value || !distSelect.value) return;
            const csrf = form.querySelector('input[name="_csrf"]')?.value || '';
            const data = new FormData();
            data.append('_csrf', csrf);
            data.append('cliente_id', clienteSelect.value);
            data.append('departamento_id', depSelect.value);
            data.append('provincia_id', provSelect.value);
            data.append('distrito_id', distSelect.value);

            const original = saveClient.innerHTML;
            saveClient.disabled = true;
            saveClient.innerHTML = '<span class="spinner-border spinner-border-sm" aria-hidden="true"></span> Guardando…';
            try {
                const response = await fetch(endpoint, {
                    method: 'POST', body: data, credentials: 'same-origin',
                    headers: {'Accept':'application/json','X-Requested-With':'XMLHttpRequest'}
                });
                const payload = await response.json().catch(() => ({}));
                if (!response.ok || !payload.success) throw new Error(payload.message || 'No se pudo vincular la ubicación.');

                const option = selectedOption(clienteSelect);
                if (option) {
                    option.dataset.departmentId = String(depSelect.value);
                    option.dataset.departmentLabel = selectedText(depSelect);
                    option.dataset.provinceId = String(provSelect.value);
                    option.dataset.provinceLabel = selectedText(provSelect);
                    option.dataset.districtId = String(distSelect.value);
                    option.dataset.districtLabel = selectedText(distSelect);
                }
                manual = false;
                if (note) note.textContent = payload.message || 'Ubicación guardada en la ficha del cliente.';
                refresh();
            } catch (error) {
                if (note) note.textContent = error?.message || 'No se pudo guardar la ubicación del cliente.';
                saveClient.innerHTML = original;
            } finally {
                saveClient.disabled = false;
            }
        });

        toggle.addEventListener('click', () => {
            if (manual) {
                restoreAutomaticDestination();
                return;
            }
            manual = true;
            refresh();
            depSelect.closest('.incremental-select')?.querySelector('input')?.focus({preventScroll:true});
        });

        clienteSelect.addEventListener('change', () => {
            manual = false;
            window.setTimeout(() => {
                const client = meta();
                if (clientHasDestination(client)) restoreAutomaticDestination();
                else refresh();
            }, 0);
        });

        [depSelect,provSelect,distSelect].forEach(select => select.addEventListener('change', refresh));
        refresh();
    }

    function wireProductUnit(row) {
        const productSelect = row.querySelector('[data-role="producto"]');
        const unitSelect = row.querySelector('select[name$="[unidad_id]"]');
        if (!productSelect || !unitSelect) return;

        unitSelect.classList.add('auto-unit-select');
        unitSelect.setAttribute('aria-readonly', 'true');
        unitSelect.tabIndex = -1;

        function refresh(force) {
            if (!force && unitSelect.value) return;
            const option = selectedOption(productSelect);
            const unitId = option?.dataset.unitId || '';
            const unitLabel = option?.dataset.unitLabel || unitId;
            setOption(unitSelect, unitId, unitLabel, false);
        }

        productSelect.addEventListener('change', () => refresh(true));
        refresh(false);
    }

    function wireLoteCascade(row) {
        const productSelect = row.querySelector('[data-role="producto"]');
        const loteSelect = row.querySelector('[data-role="lote"]');
        if (!productSelect || !loteSelect) return;
        productSelect.addEventListener('change', () => setOption(loteSelect, '', '', false));
    }

    function numericQuantity(row) {
        const input = row.querySelector('input[name$="[cantidad]"]');
        if (!input) return 1;
        const value = Number.parseInt(input.value || '1', 10);
        return Number.isFinite(value) && value >= 0 ? value : 1;
    }

    function setQuantity(row, value) {
        const input = row.querySelector('input[name$="[cantidad]"]');
        if (input) input.value = String(value);
    }

    function mergeDuplicateRows(body, config, forceBlankLot) {
        const seen = new Map();
        Array.from(body.rows).forEach(row => {
            if (!row.isConnected) return;
            const product = row.querySelector('[data-role="producto"]')?.value || '';
            if (!product) return;
            let key = product;
            if (config.hasLote) {
                const lote = row.querySelector('[data-role="lote"]')?.value || '';
                if (!lote && !forceBlankLot) return;
                key += ':' + (lote || 'SIN_LOTE');
            }
            if (!seen.has(key)) {
                seen.set(key,row);
                return;
            }
            const first=seen.get(key);
            setQuantity(first,numericQuantity(first)+numericQuantity(row));
            row.remove();
            first.querySelector('input[name$="[cantidad]"]')?.focus({preventScroll:true});
        });
    }

    function initDetailRepeater(config) {
        const table=document.getElementById(config.tableId);
        const template=document.getElementById(config.templateId);
        const addButton=document.getElementById(config.addButtonId);
        if(!table||!template||!addButton) return;
        const body=table.tBodies[0];
        const form=table.closest('form');
        let index=body.rows.length;

        function attachRow(row) {
            if(config.hasLote) wireLoteCascade(row);
            wireProductUnit(row);
            const product=row.querySelector('[data-role="producto"]');
            const lote=row.querySelector('[data-role="lote"]');
            product?.addEventListener('change',()=>mergeDuplicateRows(body,config,false));
            lote?.addEventListener('change',()=>mergeDuplicateRows(body,config,false));
            row.querySelector('.remove-line-btn')?.addEventListener('click',()=>{
                if(body.rows.length>1) row.remove();
            });
        }

        Array.from(body.rows).forEach(attachRow);
        addButton.addEventListener('click',()=>{
            const html=template.innerHTML.replace(/__IDX__/g,String(index++));
            const holder=document.createElement('tbody');
            holder.innerHTML=html;
            const row=holder.firstElementChild;
            body.appendChild(row);
            attachRow(row);
            window.BP_SearchableSelects?.enhanceAll(row);
            row.querySelector('[data-role="producto"]')?.closest('.incremental-select')?.querySelector('input')?.focus();
        });
        form?.addEventListener('submit',()=>mergeDuplicateRows(body,config,true),{capture:true});
    }

    function init() {
        document.querySelectorAll('[data-ubigeo-scope]').forEach(wireUbigeo);
        document.querySelectorAll('[data-role="cliente"]').forEach(wireClienteSuggestions);
        document.querySelectorAll('[data-destination-shell]').forEach(wireDestinationEditor);
        if(window.BP_DETAIL_REPEATER) initDetailRepeater(window.BP_DETAIL_REPEATER);
    }

    if(document.readyState==='loading') document.addEventListener('DOMContentLoaded',init,{once:true});
    else init();
})();
