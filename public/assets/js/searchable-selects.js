(function () {
    'use strict';

    const enhanced = new WeakMap();
    let openWidget = null;

    const normalize = (value) => String(value || '')
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .toLowerCase()
        .trim();

    function positionMenu(widget) {
        if (!widget || !widget.menu.classList.contains('open')) return;
        const rect = widget.input.getBoundingClientRect();
        const gap = 5;
        const below = window.innerHeight - rect.bottom;
        const preferAbove = below < 220 && rect.top > below;
        widget.menu.style.left = Math.max(8, rect.left) + 'px';
        widget.menu.style.width = Math.max(220, rect.width) + 'px';
        widget.menu.style.maxWidth = Math.max(220, window.innerWidth - 16) + 'px';
        if (preferAbove) {
            widget.menu.style.top = 'auto';
            widget.menu.style.bottom = Math.max(8, window.innerHeight - rect.top + gap) + 'px';
        } else {
            widget.menu.style.bottom = 'auto';
            widget.menu.style.top = Math.min(window.innerHeight - 8, rect.bottom + gap) + 'px';
        }
    }

    function close(widget) {
        if (!widget) return;
        widget.menu.classList.remove('open');
        widget.input.setAttribute('aria-expanded', 'false');
        widget.activeIndex = -1;
        if (openWidget === widget) openWidget = null;
    }

    function splitLabel(label) {
        const parts = String(label || '').split(' · ');
        if (parts.length < 2) return {code: '', text: String(label || '')};
        return {code: parts.shift(), text: parts.join(' · ')};
    }

    function optionMarkup(button, item) {
        const parts = splitLabel(item.label);
        if (parts.code) {
            const strong = document.createElement('strong');
            strong.textContent = parts.code;
            button.appendChild(strong);

            const label = document.createElement('span');
            label.className = 'incremental-select-option-label';
            label.textContent = parts.text;
            button.appendChild(label);
        } else {
            button.textContent = parts.text;
        }
    }

    function selectedMarkup(widget, label) {
        widget.selectedDisplay.innerHTML = '';
        const parts = splitLabel(label);
        if (!label) return;

        if (parts.code) {
            const code = document.createElement('strong');
            code.className = 'incremental-select-selected-code';
            code.textContent = parts.code;
            widget.selectedDisplay.appendChild(code);

            const text = document.createElement('span');
            text.className = 'incremental-select-selected-label';
            text.textContent = parts.text;
            widget.selectedDisplay.appendChild(text);
        } else {
            const text = document.createElement('span');
            text.className = 'incremental-select-selected-label';
            text.textContent = parts.text;
            widget.selectedDisplay.appendChild(text);
        }
    }

    function render(widget) {
        const term = normalize(widget.input.value);
        widget.menu.innerHTML = '';
        widget.rendered = [];
        widget.activeIndex = -1;

        if (term.length < widget.minChars) {
            const info = document.createElement('div');
            info.className = 'incremental-select-empty';
            info.textContent = 'Escribe para buscar entre ' + widget.items.length + ' opciones.';
            widget.menu.appendChild(info);
        } else {
            const allMatches = widget.items.filter(item => item.search.includes(term));
            const matches = allMatches.slice(0, widget.limit);
            widget.rendered = matches;

            if (!matches.length) {
                const empty = document.createElement('div');
                empty.className = 'incremental-select-empty';
                empty.textContent = 'No se encontraron coincidencias.';
                widget.menu.appendChild(empty);
            } else {
                matches.forEach((item, index) => {
                    const button = document.createElement('button');
                    button.type = 'button';
                    button.className = 'incremental-select-option';
                    button.setAttribute('role', 'option');
                    button.dataset.index = String(index);
                    optionMarkup(button, item);
                    button.addEventListener('mousedown', event => event.preventDefault());
                    button.addEventListener('click', () => choose(widget, item));
                    widget.menu.appendChild(button);
                });

                if (allMatches.length > widget.limit) {
                    const info = document.createElement('div');
                    info.className = 'incremental-select-empty';
                    info.textContent = 'Mostrando las primeras ' + widget.limit + ' coincidencias. Escribe más caracteres para afinar la búsqueda.';
                    widget.menu.appendChild(info);
                }
            }
        }

        widget.menu.classList.add('open');
        widget.input.setAttribute('aria-expanded', 'true');
        openWidget = widget;
        positionMenu(widget);
    }

    function choose(widget, item) {
        widget.select.value = item.value;
        widget.input.value = item.label;
        widget.input.setCustomValidity('');
        widget.wrapper.classList.toggle('has-value', item.value !== '');
        close(widget);
        widget.select.dispatchEvent(new Event('change', {bubbles: true}));
        widget.input.focus();
    }

    function sync(widget) {
        const option = widget.select.selectedOptions && widget.select.selectedOptions[0];
        const label = option && option.value ? option.textContent.trim() : '';
        widget.input.value = label;
        selectedMarkup(widget, label);
        widget.input.setCustomValidity('');
        widget.wrapper.classList.toggle('has-value', Boolean(option && option.value));
    }

    function refresh(select) {
        const widget = enhanced.get(select);
        if (!widget) {
            enhance(select);
            return;
        }
        widget.items = itemsFor(select);
        sync(widget);
        if (widget.menu.classList.contains('open')) render(widget);
    }

    function moveActive(widget, step) {
        if (!widget.rendered.length) return;
        if (widget.activeIndex < 0) {
            widget.activeIndex = step > 0 ? 0 : widget.rendered.length - 1;
        } else {
            widget.activeIndex = Math.max(0, Math.min(widget.rendered.length - 1, widget.activeIndex + step));
        }
        widget.menu.querySelectorAll('.incremental-select-option').forEach((node, index) => {
            node.classList.toggle('active', index === widget.activeIndex);
            if (index === widget.activeIndex) node.scrollIntoView({block: 'nearest'});
        });
    }

    function itemsFor(select) {
        return Array.from(select.options)
            .filter(option => option.value !== '')
            .map(option => ({
                value: option.value,
                label: option.textContent.trim(),
                search: normalize(option.textContent + ' ' + (option.dataset.search || '')),
            }));
    }

    function enhance(select) {
        if (!select || enhanced.has(select) || select.dataset.searchEnhanced === '1') return;
        select.dataset.searchEnhanced = '1';

        const items = itemsFor(select);

        const wrapper = document.createElement('div');
        wrapper.className = 'incremental-select';
        select.parentNode.insertBefore(wrapper, select);
        wrapper.appendChild(select);

        select.classList.add('incremental-select-native');
        select.tabIndex = -1;
        select.setAttribute('aria-hidden', 'true');

        const inputWrap = document.createElement('div');
        inputWrap.className = 'incremental-select-input-wrap';

        const input = document.createElement('input');
        input.type = 'text';
        input.className = select.classList.contains('form-select-sm')
            ? 'form-control form-control-sm incremental-select-input'
            : 'form-control incremental-select-input';
        input.placeholder = select.dataset.searchPlaceholder || 'Escribe para buscar...';
        input.autocomplete = 'off';
        input.spellcheck = false;
        input.setAttribute('role', 'combobox');
        input.setAttribute('aria-autocomplete', 'list');
        input.setAttribute('aria-expanded', 'false');
        inputWrap.appendChild(input);

        const selectedDisplay = document.createElement('div');
        selectedDisplay.className = 'incremental-select-selected';
        selectedDisplay.setAttribute('aria-hidden', 'true');
        inputWrap.appendChild(selectedDisplay);

        wrapper.appendChild(inputWrap);

        const menu = document.createElement('div');
        menu.className = 'incremental-select-menu';
        menu.setAttribute('role', 'listbox');
        document.body.appendChild(menu);

        const widget = {
            select,
            wrapper,
            input,
            selectedDisplay,
            menu,
            items,
            rendered: [],
            activeIndex: -1,
            minChars: Math.max(0, Number(select.dataset.searchMin || 1)),
            limit: Math.max(10, Number(select.dataset.searchLimit || 40)),
        };
        enhanced.set(select, widget);
        sync(widget);

        input.addEventListener('focus', () => {
            if (select.value) input.select();
            render(widget);
        });
        input.addEventListener('input', () => {
            const current = select.selectedOptions && select.selectedOptions[0];
            const currentLabel = current && current.value ? current.textContent.trim() : '';
            if (input.value !== currentLabel && select.value !== '') {
                select.value = '';
                select.dispatchEvent(new Event('change', {bubbles: true}));
            }
            input.setCustomValidity('');
            wrapper.classList.remove('has-value');
            render(widget);
        });

        input.addEventListener('keydown', event => {
            if (event.key === 'ArrowDown') {
                event.preventDefault();
                if (!menu.classList.contains('open')) render(widget);
                moveActive(widget, 1);
            } else if (event.key === 'ArrowUp') {
                event.preventDefault();
                if (!menu.classList.contains('open')) render(widget);
                moveActive(widget, -1);
            } else if (event.key === 'Enter' && menu.classList.contains('open') && widget.activeIndex >= 0) {
                event.preventDefault();
                choose(widget, widget.rendered[widget.activeIndex]);
            } else if (event.key === 'Escape') {
                close(widget);
            }
        });

        select.addEventListener('change', () => sync(widget));
        select.addEventListener('invalid', event => {
            event.preventDefault();
            input.setCustomValidity('Selecciona una opción válida de la lista.');
            input.reportValidity();
            input.focus();
            render(widget);
        });

        const form = select.closest('form');
        if (form && !form.dataset.incrementalValidation) {
            form.dataset.incrementalValidation = '1';
            form.addEventListener('submit', event => {
                let firstInvalid = null;
                form.querySelectorAll('select[data-search-select]').forEach(nativeSelect => {
                    const w = enhanced.get(nativeSelect);
                    if (!w) return;
                    if (nativeSelect.required && !nativeSelect.value) {
                        w.input.setCustomValidity('Selecciona una opción válida de la lista.');
                        firstInvalid ||= w;
                    } else {
                        w.input.setCustomValidity('');
                    }
                });
                if (firstInvalid) {
                    event.preventDefault();
                    firstInvalid.input.reportValidity();
                    firstInvalid.input.focus();
                    render(firstInvalid);
                }
            });
        }
    }

    function enhanceAll(root) {
        if (!root) return;
        if (root.matches && root.matches('select[data-search-select]')) enhance(root);
        if (root.querySelectorAll) root.querySelectorAll('select[data-search-select]').forEach(enhance);
    }

    document.addEventListener('click', event => {
        if (!openWidget) return;
        if (openWidget.wrapper.contains(event.target) || openWidget.menu.contains(event.target)) return;
        close(openWidget);
    });

    window.addEventListener('resize', () => positionMenu(openWidget));
    window.addEventListener('scroll', () => positionMenu(openWidget), true);

    document.addEventListener('DOMContentLoaded', () => {
        // Espera un ciclo para que los scripts de formularios creen primero sus plantillas.
        setTimeout(() => {
            enhanceAll(document);
            const observer = new MutationObserver(records => {
                records.forEach(record => {
                    if (record.target instanceof HTMLSelectElement && enhanced.has(record.target)) {
                        refresh(record.target);
                    }
                    record.addedNodes.forEach(node => {
                        if (node.nodeType === 1) enhanceAll(node);
                    });
                });
            });
            observer.observe(document.body, {childList: true, subtree: true});
        }, 0);
    });

    function reset() {
        close(openWidget);
        document.querySelectorAll('.incremental-select-menu').forEach(menu => menu.remove());
    }

    window.BP_SearchableSelects = {enhance, enhanceAll, refresh, reset};
})();
