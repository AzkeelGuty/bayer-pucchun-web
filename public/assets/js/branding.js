(function () {
    'use strict';

    if (window.BP_BRANDING_BOUND) return;
    window.BP_BRANDING_BOUND = true;

    const DEFAULTS = {
        primary_color: '#075B9F',
        accent_color: '#168C5B',
        sidebar_color: '#0A2F55',
        background_color: '#F4F7FB',
        sidebar_theme: 'dark',
        ui_density: 'comfortable',
        corner_style: 'rounded',
        shadow_style: 'soft',
        sidebar_size: 'normal',
        topbar_style: 'glass'
    };

    const getForm = () => document.querySelector('[data-branding-form]');

    function colorValue(form, name) {
        return String(form?.querySelector('#' + name)?.value || '').toUpperCase();
    }

    function setColor(form, name, value) {
        const normalized = String(value || '').toUpperCase();
        const code = form.querySelector('#' + name);
        const picker = form.querySelector('[data-color-picker="' + name + '"]');
        if (code) code.value = normalized;
        if (picker) picker.value = normalized;
    }

    function replacePreviewClass(element, prefix, value) {
        if (!element) return;
        [...element.classList]
            .filter(cls => cls.startsWith(prefix))
            .forEach(cls => element.classList.remove(cls));
        element.classList.add(prefix + value);
    }

    function updateAppearancePreview(form) {
        const preview = document.getElementById('brandingPreview');
        const login = document.getElementById('brandingLoginPreview');
        if (!form || !preview) return;

        const read = (name, fallback) =>
            form.querySelector('input[name="' + name + '"]:checked')?.value || fallback;

        const sidebarTheme = read('sidebar_theme', DEFAULTS.sidebar_theme);
        const density = read('ui_density', DEFAULTS.ui_density);
        const corners = read('corner_style', DEFAULTS.corner_style);
        const shadow = read('shadow_style', DEFAULTS.shadow_style);
        const sidebarSize = read('sidebar_size', DEFAULTS.sidebar_size);
        const topbar = read('topbar_style', DEFAULTS.topbar_style);

        replacePreviewClass(preview, 'sidebar-', sidebarTheme);
        replacePreviewClass(preview, 'preview-density-', density);
        replacePreviewClass(preview, 'preview-corners-', corners);
        replacePreviewClass(preview, 'preview-shadow-', shadow);
        replacePreviewClass(preview, 'preview-sidebar-', sidebarSize);
        replacePreviewClass(preview, 'preview-topbar-', topbar);
        replacePreviewClass(login, 'preview-corners-', corners);
    }

    function updateColorPreview(form) {
        if (!form) return;
        const preview = document.getElementById('brandingPreview');
        const login = document.getElementById('brandingLoginPreview');

        const values = {
            primary: colorValue(form, 'primary_color'),
            accent: colorValue(form, 'accent_color'),
            sidebar: colorValue(form, 'sidebar_color'),
            bg: colorValue(form, 'background_color')
        };

        if (preview) {
            preview.style.setProperty('--preview-primary', values.primary);
            preview.style.setProperty('--preview-accent', values.accent);
            preview.style.setProperty('--preview-sidebar', values.sidebar);
            preview.style.setProperty('--preview-bg', values.bg);
        }
        if (login) {
            login.style.setProperty('--preview-primary', values.primary);
            login.style.setProperty('--preview-accent', values.accent);
            login.style.setProperty('--preview-sidebar', values.sidebar);
        }

        document.querySelectorAll('.brand-preset').forEach(button => {
            const active =
                String(button.dataset.primary || '').toUpperCase() === values.primary &&
                String(button.dataset.accent || '').toUpperCase() === values.accent &&
                String(button.dataset.sidebar || '').toUpperCase() === values.sidebar &&
                String(button.dataset.background || '').toUpperCase() === values.bg;
            button.classList.toggle('is-active', active);
            button.setAttribute('aria-pressed', active ? 'true' : 'false');
        });
    }

    function updateBrowserTitle(form) {
        if (!form) return;
        const system = form.querySelector('#system_name')?.value.trim() || 'Sistema';
        const internal = form.querySelector('#internal_title')?.value.trim() || 'Aplicación';
        const target = document.getElementById('preview_browser_title');
        if (target) target.textContent = system + ' · ' + internal;
    }

    function setRadio(form, name, value) {
        const input = form.querySelector('input[name="' + name + '"][value="' + value + '"]');
        if (input) input.checked = true;
    }

    function applyDefaults(form) {
        Object.entries(DEFAULTS).forEach(([name, value]) => {
            if (name.endsWith('_color')) setColor(form, name, value);
            else setRadio(form, name, value);
        });
        updateColorPreview(form);
        updateAppearancePreview(form);
    }

    function assetElements(field) {
        const box = document.querySelector('[data-brand-asset="' + field + '"]');
        if (!box) return null;
        const file = box.querySelector('[data-brand-file="' + field + '"]');
        const remove = box.querySelector('[data-brand-remove="' + field + '"]');
        const hidden = box.querySelector('#remove_' + field);
        const status = box.querySelector('[data-brand-status="' + field + '"]');
        const current = document.getElementById('current_' + field + '_preview');

        if (!box.dataset.originalSrc) {
            box.dataset.originalSrc = current?.getAttribute('src') || '';
        }

        return {box, file, remove, hidden, status, current, original: box.dataset.originalSrc || ''};
    }

    function assetPreviewTargets(field) {
        if (field === 'logo_primary') {
            return [
                document.getElementById('current_logo_primary_preview'),
                document.getElementById('preview_logo_primary'),
                document.getElementById('preview_login_logo_primary')
            ].filter(Boolean);
        }
        if (field === 'logo_partner') {
            return [
                document.getElementById('current_logo_partner_preview'),
                document.getElementById('preview_login_logo_partner')
            ].filter(Boolean);
        }
        return [
            document.getElementById('current_favicon_preview'),
            document.getElementById('preview_browser_favicon')
        ].filter(Boolean);
    }

    function toggleFallback(field, visible) {
        if (field === 'logo_primary') {
            document.getElementById('preview_logo_primary_fallback')?.classList.toggle('is-hidden', !visible);
        }
        if (field === 'favicon') {
            document.getElementById('preview_browser_favicon_fallback')?.classList.toggle('is-hidden', !visible);
        }
    }

    function showAsset(field, src) {
        assetPreviewTargets(field).forEach(img => {
            img.src = src;
            img.classList.toggle('is-hidden', !src);
        });
        toggleFallback(field, !src);
    }

    function restoreAsset(field) {
        const els = assetElements(field);
        if (!els) return;
        showAsset(field, els.original);
        els.box.classList.remove('has-new-file', 'marked-remove');
        if (els.hidden) els.hidden.value = '0';
        if (els.status) {
            els.status.textContent = els.original ? 'Archivo actual activo' : 'Sin archivo personalizado';
            els.status.classList.remove('is-remove');
        }
        if (els.remove) {
            els.remove.classList.remove('is-marked');
            els.remove.innerHTML = '<i class="bi bi-trash3"></i> Quitar actual';
            els.remove.classList.toggle('d-none', !els.original);
        }
    }

    function previewNewAsset(field, fileObject) {
        const els = assetElements(field);
        if (!els || !fileObject) return;

        if (els.box.dataset.objectUrl) URL.revokeObjectURL(els.box.dataset.objectUrl);
        const objectUrl = URL.createObjectURL(fileObject);
        els.box.dataset.objectUrl = objectUrl;

        showAsset(field, objectUrl);
        els.box.classList.add('has-new-file');
        els.box.classList.remove('marked-remove');
        if (els.hidden) els.hidden.value = '0';
        if (els.status) {
            els.status.textContent = 'Nuevo archivo listo para guardar';
            els.status.classList.remove('is-remove');
        }
        if (els.remove) {
            els.remove.classList.remove('d-none', 'is-marked');
            els.remove.innerHTML = '<i class="bi bi-x-circle"></i> Descartar selección';
        }
    }

    function removeAsset(field) {
        const els = assetElements(field);
        if (!els) return;

        if (els.file?.files?.length) {
            els.file.value = '';
            restoreAsset(field);
            return;
        }

        if (!els.original) return;

        const marked = els.hidden?.value === '1';
        if (marked) {
            restoreAsset(field);
            return;
        }

        if (els.hidden) els.hidden.value = '1';
        showAsset(field, '');
        els.box.classList.add('marked-remove');
        els.box.classList.remove('has-new-file');
        if (els.status) {
            els.status.textContent = 'Se eliminará al guardar los cambios';
            els.status.classList.add('is-remove');
        }
        if (els.remove) {
            els.remove.classList.add('is-marked');
            els.remove.innerHTML = '<i class="bi bi-arrow-counterclockwise"></i> Deshacer eliminación';
        }
    }

    document.addEventListener('click', event => {
        const form = getForm();
        if (!form) return;

        const preset = event.target.closest('.brand-preset');
        if (preset) {
            event.preventDefault();
            setColor(form, 'primary_color', preset.dataset.primary);
            setColor(form, 'accent_color', preset.dataset.accent);
            setColor(form, 'sidebar_color', preset.dataset.sidebar);
            setColor(form, 'background_color', preset.dataset.background);
            updateColorPreview(form);
            return;
        }

        if (event.target.closest('#brandingDefaults')) {
            event.preventDefault();
            applyDefaults(form);
            return;
        }

        const remove = event.target.closest('[data-brand-remove]');
        if (remove) {
            event.preventDefault();
            removeAsset(remove.dataset.brandRemove);
        }
    });

    document.addEventListener('input', event => {
        const form = getForm();
        if (!form) return;

        const picker = event.target.closest('[data-color-picker]');
        if (picker) {
            setColor(form, picker.dataset.colorPicker, picker.value);
            updateColorPreview(form);
            return;
        }

        const code = event.target.closest('[data-color-code]');
        if (code) {
            const value = code.value.trim();
            if (/^#[0-9A-Fa-f]{6}$/.test(value)) {
                const pickerForCode = form.querySelector('[data-color-picker="' + code.id + '"]');
                if (pickerForCode) pickerForCode.value = value;
                code.value = value.toUpperCase();
                updateColorPreview(form);
            }
            return;
        }

        const liveText = event.target.closest('.brand-live-text');
        if (liveText) {
            const targetId = liveText.dataset.preview;
            const target = targetId ? document.getElementById(targetId) : null;
            if (target) target.textContent = liveText.value;
            if (liveText.id === 'system_name' || liveText.id === 'internal_title') updateBrowserTitle(form);
        }
    });

    document.addEventListener('change', event => {
        const form = getForm();
        if (!form) return;

        const appearance = event.target.closest('[data-appearance-option]');
        if (appearance) {
            updateAppearancePreview(form);
            return;
        }

        const fileInput = event.target.closest('[data-brand-file]');
        if (fileInput) {
            const fileObject = fileInput.files?.[0];
            if (!fileObject) {
                restoreAsset(fileInput.dataset.brandFile);
                return;
            }
            if (fileObject.size > 2 * 1024 * 1024) {
                fileInput.setCustomValidity('El archivo supera el máximo de 2 MB.');
                fileInput.reportValidity();
                fileInput.value = '';
                restoreAsset(fileInput.dataset.brandFile);
                return;
            }
            fileInput.setCustomValidity('');
            previewNewAsset(fileInput.dataset.brandFile, fileObject);
        }
    });

    function initializeCurrentBranding() {
        const form = getForm();
        if (!form) return;
        updateColorPreview(form);
        updateAppearancePreview(form);
        updateBrowserTitle(form);
    }

    document.addEventListener('DOMContentLoaded', initializeCurrentBranding);
    document.addEventListener('bp:navigation:complete', initializeCurrentBranding);
})();
