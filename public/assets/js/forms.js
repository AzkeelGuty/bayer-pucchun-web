/**
 * Comportamiento compartido de formularios de captura (Guías/Stock).
 * Prioriza automatización: cliente -> vendedor/sucursal/destino,
 * producto -> unidad, y evita líneas duplicadas.
 */
(function () {
    'use strict';

    function rebuildOptions(select, rows, valueKey, labelKey, parentKey, parentValue, placeholder) {
        const current = select.value;
        select.innerHTML = '';
        const empty = document.createElement('option');
        empty.value = '';
        empty.textContent = placeholder;
        select.appendChild(empty);

        rows.filter(function (row) {
            return !parentValue || String(row[parentKey]) === String(parentValue);
        }).forEach(function (row) {
            const option = document.createElement('option');
            option.value = row[valueKey];
            option.textContent = row[labelKey];
            select.appendChild(option);
        });

        if ([...select.options].some(function (option) { return option.value === current; })) {
            select.value = current;
        }
    }

    function wireUbigeo(scope) {
        const geo = window.BP_GEO;
        const depSelect = scope.querySelector('[data-role="departamento"]');
        const provSelect = scope.querySelector('[data-role="provincia"]');
        const distSelect = scope.querySelector('[data-role="distrito"]');
        if (!geo || !depSelect || !provSelect || !distSelect) return;

        function refreshDistritos() {
            rebuildOptions(distSelect, geo.distritos, 'id', 'nombre', 'provincia_id', provSelect.value, 'Seleccione…');
        }

        function refreshProvincias() {
            rebuildOptions(provSelect, geo.provincias, 'id', 'nombre', 'departamento_id', depSelect.value, 'Seleccione…');
            refreshDistritos();
        }

        depSelect.addEventListener('change', refreshProvincias);
        provSelect.addEventListener('change', refreshDistritos);

        if (depSelect.dataset.old) depSelect.value = depSelect.dataset.old;
        refreshProvincias();
        if (provSelect.dataset.old) provSelect.value = provSelect.dataset.old;
        refreshDistritos();
        if (distSelect.dataset.old) distSelect.value = distSelect.dataset.old;
    }

    function wireClienteSuggestions(clienteSelect) {
        const clientes = window.BP_CLIENTES;
        const form = clienteSelect.closest('form');
        if (!clientes || !form) return;

        const depSelect = form.querySelector('[data-role="departamento"]');
        const provSelect = form.querySelector('[data-role="provincia"]');
        const distSelect = form.querySelector('[data-role="distrito"]');
        const sellerSelect = form.querySelector('[name="vendedor_id"]');
        const branchSelect = form.querySelector('[name="sucursal_id"]');

        function apply(force) {
            const cliente = clientes[clienteSelect.value];
            if (!cliente) return;

            if (sellerSelect && (force || !sellerSelect.value)) sellerSelect.value = cliente.vendedor_sugerido_id || '';
            if (branchSelect && (force || !branchSelect.value)) branchSelect.value = cliente.sucursal_sugerida_id || '';

            const geoEmpty = !depSelect?.value && !provSelect?.value && !distSelect?.value;
            if (depSelect && provSelect && distSelect && (force || geoEmpty)) {
                depSelect.value = cliente.departamento_id || '';
                depSelect.dispatchEvent(new Event('change', {bubbles: true}));
                provSelect.value = cliente.provincia_id || '';
                provSelect.dispatchEvent(new Event('change', {bubbles: true}));
                distSelect.value = cliente.distrito_id || '';
                distSelect.dispatchEvent(new Event('change', {bubbles: true}));
            }
        }

        clienteSelect.addEventListener('change', function () { apply(true); });
        if (clienteSelect.value) apply(false);
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
        if (!clienteSelect || !depSelect || !provSelect || !distSelect || !fields || !toggle || !summary) return;

        let manual = false;
        let restoringAutomatic = false;

        const getClient = () => (window.BP_CLIENTES || {})[clienteSelect.value] || null;
        const clientHasDestination = (client) => Boolean(
            client?.departamento_id && client?.provincia_id && client?.distrito_id
        );

        const selectedText = (select) => {
            const option = select.selectedOptions?.[0];
            return option && option.value ? option.textContent.trim() : '';
        };

        function refresh() {
            const hasClient = Boolean(clienteSelect.value);
            const client = getClient();
            const hasAutomatic = clientHasDestination(client);
            const complete = Boolean(depSelect.value && provSelect.value && distSelect.value);

            if (!hasClient) {
                manual = false;
                fields.hidden = true;
                toggle.hidden = true;
                summary.textContent = 'Selecciona un cliente para completar el destino.';
                if (note) note.textContent = 'La ubicación se completa automáticamente cuando existe en la ficha del cliente.';
                return;
            }

            if (complete) {
                summary.textContent = [selectedText(distSelect), selectedText(provSelect), selectedText(depSelect)]
                    .filter(Boolean).join(' · ');

                if (note) {
                    note.textContent = manual
                        ? 'Destino ajustado para esta guía.'
                        : (hasAutomatic
                            ? 'Destino completado desde la ficha del cliente.'
                            : 'Destino definido para esta guía.');
                }

                fields.hidden = !manual;
                toggle.hidden = !hasAutomatic;
                if (hasAutomatic) {
                    toggle.textContent = manual ? 'Restaurar destino del cliente' : 'Cambiar destino';
                }
                return;
            }

            manual = true;
            fields.hidden = false;
            toggle.hidden = true;
            summary.textContent = 'Completa el destino de entrega.';
            if (note) note.textContent = hasAutomatic
                ? 'Puedes completar el destino o restaurar la ubicación registrada del cliente.'
                : 'Este cliente no tiene una ubicación completa registrada.';
        }

        function restoreAutomaticDestination() {
            const client = getClient();
            if (!clientHasDestination(client)) return false;

            restoringAutomatic = true;
            manual = false;

            depSelect.value = String(client.departamento_id);
            depSelect.dispatchEvent(new Event('change', {bubbles: true}));

            provSelect.value = String(client.provincia_id);
            provSelect.dispatchEvent(new Event('change', {bubbles: true}));

            distSelect.value = String(client.distrito_id);
            distSelect.dispatchEvent(new Event('change', {bubbles: true}));

            restoringAutomatic = false;
            refresh();
            return true;
        }

        toggle.addEventListener('click', function () {
            if (manual) {
                restoreAutomaticDestination();
                return;
            }

            manual = true;
            refresh();
            depSelect.focus({preventScroll: true});
        });

        clienteSelect.addEventListener('change', function () {
            manual = false;
            window.setTimeout(() => {
                const client = getClient();
                const complete = Boolean(depSelect.value && provSelect.value && distSelect.value);
                if (clientHasDestination(client) && !complete) {
                    restoreAutomaticDestination();
                } else {
                    refresh();
                }
            }, 0);
        });

        [depSelect, provSelect, distSelect].forEach(function (select) {
            select.addEventListener('change', function () {
                if (restoringAutomatic) return;
                refresh();
            });
        });

        refresh();
    }

    function wireProductUnit(row) {
        const productos = window.BP_PRODUCTS || {};
        const productSelect = row.querySelector('[data-role="producto"]');
        const unitSelect = row.querySelector('select[name$="[unidad_id]"]');
        if (!productSelect || !unitSelect) return;

        unitSelect.classList.add('auto-unit-select');
        unitSelect.setAttribute('aria-readonly', 'true');
        unitSelect.tabIndex = -1;

        function refresh(force) {
            const product = productos[productSelect.value];
            if (!product || !product.unidad_base_id || (!force && unitSelect.value)) return;
            unitSelect.value = String(product.unidad_base_id);
        }

        productSelect.addEventListener('change', function () { refresh(true); });
        refresh(false);
    }

    function wireAutoNumber(form) {
        const input = form.querySelector('[data-number-input]');
        const mode = form.querySelector('[data-number-mode]');
        const manual = form.querySelector('[data-number-manual]');
        const help = form.querySelector('[data-number-help]');
        if (!input || !mode || !manual) return;

        function refresh() {
            const isManual = manual.checked;
            mode.value = isManual ? 'manual' : 'auto';
            input.readOnly = !isManual;
            if (!isManual) input.value = input.dataset.autoNumber || '';
            if (help) {
                help.textContent = isManual
                    ? 'Modo manual para una guía que ya existe fuera del sistema.'
                    : 'El correlativo se genera automáticamente al guardar.';
            }
            if (isManual) {
                input.focus();
                input.select();
            }
        }

        manual.addEventListener('change', refresh);
        refresh();
    }

    function wireLoteCascade(row) {
        const lotes = window.BP_LOTES || [];
        const productoSelect = row.querySelector('[data-role="producto"]');
        const loteSelect = row.querySelector('[data-role="lote"]');
        if (!productoSelect || !loteSelect) return;

        function refresh() {
            rebuildOptions(loteSelect, lotes, 'id', 'codigo_lote', 'producto_id', productoSelect.value, 'Sin lote');
        }

        productoSelect.addEventListener('change', refresh);
        refresh();
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
        Array.from(body.rows).forEach(function (row) {
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
                seen.set(key, row);
                return;
            }

            const first = seen.get(key);
            setQuantity(first, numericQuantity(first) + numericQuantity(row));
            row.remove();
            first.querySelector('input[name$="[cantidad]"]')?.focus({preventScroll: true});
        });
    }

    function initDetailRepeater(config) {
        const table = document.getElementById(config.tableId);
        const template = document.getElementById(config.templateId);
        const addButton = document.getElementById(config.addButtonId);
        if (!table || !template || !addButton) return;

        const body = table.tBodies[0];
        const form = table.closest('form');
        let index = body.rows.length;

        function attachRow(row) {
            if (config.hasLote) wireLoteCascade(row);
            wireProductUnit(row);

            const product = row.querySelector('[data-role="producto"]');
            const lote = row.querySelector('[data-role="lote"]');
            if (product && !config.hasLote) {
                product.addEventListener('change', function () {
                    mergeDuplicateRows(body, config, false);
                });
            }
            if (lote && config.hasLote) {
                lote.addEventListener('change', function () {
                    mergeDuplicateRows(body, config, false);
                });
            }

            const removeBtn = row.querySelector('.remove-line-btn');
            if (removeBtn) {
                removeBtn.addEventListener('click', function () {
                    if (body.rows.length > 1) row.remove();
                });
            }
        }

        Array.from(body.rows).forEach(attachRow);

        addButton.addEventListener('click', function () {
            const html = template.innerHTML.replace(/__IDX__/g, String(index++));
            const holder = document.createElement('tbody');
            holder.innerHTML = html;
            const row = holder.firstElementChild;
            body.appendChild(row);
            attachRow(row);
            window.BP_SearchableSelects?.enhanceAll(row);
            row.querySelector('[data-role="producto"]')?.closest('.incremental-select')?.querySelector('input')?.focus();
        });

        if (form) {
            form.addEventListener('submit', function () {
                mergeDuplicateRows(body, config, true);
            }, {capture: true});
        }
    }

    function init() {
        document.querySelectorAll('[data-ubigeo-scope]').forEach(wireUbigeo);
        document.querySelectorAll('[data-role="cliente"]').forEach(wireClienteSuggestions);
        document.querySelectorAll('[data-destination-shell]').forEach(wireDestinationEditor);
        document.querySelectorAll('form').forEach(wireAutoNumber);
        if (window.BP_DETAIL_REPEATER) initDetailRepeater(window.BP_DETAIL_REPEATER);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init, {once: true});
    } else {
        init();
    }
})();
