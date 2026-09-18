/**
 * Shared behaviour for capture forms (Guías/Stock): repeatable detail lines
 * and dependent selects (departamento->provincia->distrito, producto->lote).
 * Data is read from window.BP_* globals set by the view (see guias/form.php,
 * stock/form.php) so this file stays generic across both forms.
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
        rows.filter(function (row) { return !parentValue || String(row[parentKey]) === String(parentValue); })
            .forEach(function (row) {
                const option = document.createElement('option');
                option.value = row[valueKey];
                option.textContent = row[labelKey];
                select.appendChild(option);
            });
        if ([...select.options].some(function (o) { return o.value === current; })) {
            select.value = current;
        }
    }

    function wireUbigeo(scope) {
        const geo = window.BP_GEO;
        const depSelect = scope.querySelector('[data-role="departamento"]');
        const provSelect = scope.querySelector('[data-role="provincia"]');
        const distSelect = scope.querySelector('[data-role="distrito"]');
        if (!geo || !depSelect || !provSelect || !distSelect) return;

        function refreshProvincias() {
            rebuildOptions(provSelect, geo.provincias, 'id', 'nombre', 'departamento_id', depSelect.value, 'Seleccione…');
            refreshDistritos();
        }
        function refreshDistritos() {
            rebuildOptions(distSelect, geo.distritos, 'id', 'nombre', 'provincia_id', provSelect.value, 'Seleccione…');
        }
        depSelect.addEventListener('change', refreshProvincias);
        provSelect.addEventListener('change', refreshDistritos);
        refreshProvincias();
        // Preserve values from a failed submit (old()), applied after options are rebuilt.
        if (depSelect.dataset.old) depSelect.value = depSelect.dataset.old;
        refreshProvincias();
        if (provSelect.dataset.old) provSelect.value = provSelect.dataset.old;
        refreshDistritos();
        if (distSelect.dataset.old) distSelect.value = distSelect.dataset.old;
    }

    /** When a Cliente is picked, pre-fill Ubicación from the cliente's own registered ubigeo. */
    function wireClienteUbigeo(clienteSelect) {
        const clientes = window.BP_CLIENTES;
        const form = clienteSelect.closest('form');
        if (!clientes || !form) return;
        const depSelect = form.querySelector('[data-role="departamento"]');
        const provSelect = form.querySelector('[data-role="provincia"]');
        const distSelect = form.querySelector('[data-role="distrito"]');
        if (!depSelect || !provSelect || !distSelect) return;

        clienteSelect.addEventListener('change', function () {
            const cliente = clientes[clienteSelect.value];
            if (!cliente) return;
            depSelect.value = cliente.departamento_id || '';
            depSelect.dispatchEvent(new Event('change'));
            provSelect.value = cliente.provincia_id || '';
            provSelect.dispatchEvent(new Event('change'));
            distSelect.value = cliente.distrito_id || '';
        });
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

    function initDetailRepeater(config) {
        const table = document.getElementById(config.tableId);
        const template = document.getElementById(config.templateId);
        const addButton = document.getElementById(config.addButtonId);
        if (!table || !template || !addButton) return;
        const body = table.tBodies[0];
        let index = body.rows.length;

        function attachRow(row) {
            if (config.hasLote) wireLoteCascade(row);
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
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('[data-ubigeo-scope]').forEach(wireUbigeo);
        document.querySelectorAll('[data-role="cliente"]').forEach(wireClienteUbigeo);
        if (window.BP_DETAIL_REPEATER) initDetailRepeater(window.BP_DETAIL_REPEATER);
    });
})();