(() => {
    'use strict';

    if (!document.querySelector('.app-shell')) return;

    const HTML_ACCEPT = 'text/html,application/xhtml+xml';
    const PAGE_GLOBALS = ['dashboardData','BP_GEO','BP_CLIENTES','BP_PRODUCTS','BP_DETAIL_REPEATER','BP_LOTES'];
    let controller = null;
    let navigating = false;
    let scrollTimer = null;

    if ('scrollRestoration' in history) history.scrollRestoration = 'manual';

    const currentState = () => ({
        ...(history.state || {}),
        bpSoftNavigation: true,
        scrollY: Math.max(0, Math.round(window.scrollY || 0))
    });

    const saveScroll = () => {
        try { history.replaceState(currentState(), '', location.href); } catch (_) {}
    };

    saveScroll();

    window.addEventListener('scroll', () => {
        if (scrollTimer) window.clearTimeout(scrollTimer);
        scrollTimer = window.setTimeout(saveScroll, 120);
    }, {passive: true});

    window.addEventListener('pagehide', saveScroll);

    const setBusy = (busy) => {
        navigating = busy;
        document.body.classList.toggle('bp-soft-navigating', busy);
        const main = document.querySelector('.app-main');
        if (main) {
            if (busy) main.setAttribute('aria-busy', 'true');
            else main.removeAttribute('aria-busy');
        }
    };

    const shouldUseNativeLink = (link, event) => {
        if (!link || !link.href) return true;
        if (event && (event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey)) return true;
        if (link.target && link.target !== '_self') return true;
        if (link.hasAttribute('download')) return true;
        if (link.matches('[data-native-navigation],[data-no-soft-nav]') || link.closest('[data-no-soft-nav]')) return true;
        if (link.classList.contains('export-format') || link.closest('.format-actions')) return true;

        const raw = link.getAttribute('href') || '';
        if (!raw || raw.startsWith('#') || /^(mailto:|tel:|javascript:)/i.test(raw)) return true;

        let url;
        try { url = new URL(link.href, location.href); } catch (_) { return true; }
        if (!/^https?:$/.test(url.protocol) || url.origin !== location.origin) return true;
        if (/\/api\/v\d+\//i.test(url.pathname)) return true;
        if (/\/export$/i.test(url.pathname)) return true;
        if (/\.(?:xlsx|xls|csv|pdf|zip|json|txt)(?:$|\?)/i.test(url.pathname + url.search)) return true;
        if (url.pathname === location.pathname && url.search === location.search && url.hash) return true;
        return false;
    };

    const extractScripts = (main, baseUrl) => {
        return Array.from(main.querySelectorAll('script')).map((node) => {
            const src = node.getAttribute('src');
            const descriptor = {
                src: src ? new URL(src, baseUrl).href : '',
                code: src ? '' : node.textContent || '',
                type: node.getAttribute('type') || '',
                attrs: Array.from(node.attributes)
                    .filter(attr => !['src','defer','async'].includes(attr.name))
                    .map(attr => [attr.name, attr.value])
            };
            node.remove();
            return descriptor;
        });
    };

    const runScript = (descriptor) => new Promise((resolve, reject) => {
        const script = document.createElement('script');
        descriptor.attrs.forEach(([name, value]) => script.setAttribute(name, value));
        if (descriptor.type) script.type = descriptor.type;
        script.dataset.bpPageScript = '1';

        if (descriptor.src) {
            script.src = descriptor.src;
            script.async = false;
            script.onload = () => { script.remove(); resolve(); };
            script.onerror = () => { script.remove(); reject(new Error('No se pudo cargar ' + descriptor.src)); };
            document.head.appendChild(script);
            return;
        }

        script.textContent = descriptor.code;
        document.head.appendChild(script);
        script.remove();
        resolve();
    });

    const syncShell = (freshDoc) => {
        document.title = freshDoc.title || document.title;

        const freshTheme = Array.from(freshDoc.querySelectorAll('style'))
            .find(node => node.textContent.includes('--brand-primary'));
        const currentTheme = Array.from(document.querySelectorAll('style'))
            .find(node => node.textContent.includes('--brand-primary'));
        if (freshTheme && currentTheme) currentTheme.textContent = freshTheme.textContent;

        const appearancePrefixes = [
            'sidebar-theme-',
            'ui-density-',
            'ui-corners-',
            'ui-shadow-',
            'sidebar-size-',
            'topbar-style-'
        ];
        appearancePrefixes.forEach((prefix) => {
            const freshClass = Array.from(freshDoc.body.classList).find(name => name.startsWith(prefix));
            Array.from(document.body.classList)
                .filter(name => name.startsWith(prefix))
                .forEach(name => document.body.classList.remove(name));
            if (freshClass) document.body.classList.add(freshClass);
        });

        const currentNav = document.getElementById('sidebarNav');
        const freshNav = freshDoc.getElementById('sidebarNav');
        if (currentNav && freshNav) {
            const top = currentNav.scrollTop;
            currentNav.innerHTML = freshNav.innerHTML;
            currentNav.dataset.scrollKey = freshNav.dataset.scrollKey || currentNav.dataset.scrollKey || 'default';
            currentNav.scrollTop = top;
        }

        const currentBrand = document.querySelector('.brand-block');
        const freshBrand = freshDoc.querySelector('.brand-block');
        if (currentBrand && freshBrand) currentBrand.innerHTML = freshBrand.innerHTML;

        const currentHeading = document.querySelector('.topbar-heading');
        const freshHeading = freshDoc.querySelector('.topbar-heading');
        if (currentHeading && freshHeading) currentHeading.innerHTML = freshHeading.innerHTML;

        const currentUser = document.querySelector('.topbar-right .user-chip');
        const freshUser = freshDoc.querySelector('.topbar-right .user-chip');
        if (currentUser && freshUser) currentUser.replaceWith(freshUser.cloneNode(true));

        const currentFooter = document.querySelector('.app-footer');
        const freshFooter = freshDoc.querySelector('.app-footer');
        if (currentFooter && freshFooter) currentFooter.innerHTML = freshFooter.innerHTML;

        const freshIcon = freshDoc.querySelector('link[rel="icon"]');
        const currentIcon = document.querySelector('link[rel="icon"]');
        if (freshIcon && currentIcon) {
            currentIcon.href = freshIcon.href;
        } else if (freshIcon && !currentIcon) {
            document.head.appendChild(freshIcon.cloneNode(true));
        } else if (!freshIcon && currentIcon) {
            currentIcon.remove();
        }
    };

    const swapMain = (freshMain, freshDoc) => {
        const currentMain = document.querySelector('.app-main');
        if (!currentMain) throw new Error('No se encontró el contenedor principal.');
        currentMain.innerHTML = freshMain.innerHTML;
        syncShell(freshDoc);
    };

    const finishScroll = (value) => {
        const y = Number.isFinite(Number(value)) ? Math.max(0, Number(value)) : 0;
        requestAnimationFrame(() => {
            window.scrollTo({top: y, left: 0, behavior: 'auto'});
            requestAnimationFrame(() => window.scrollTo({top: y, left: 0, behavior: 'auto'}));
        });
    };

    const navigate = async (target, options = {}) => {
        const url = new URL(target, location.href);
        const method = String(options.method || 'GET').toUpperCase();

        // Repetir un GET de la misma URL solo desplaza al inicio, pero un POST
        // a la misma URL SIEMPRE debe enviarse (formularios de configuración,
        // uploads, cambios de estado, etc.).
        if ((method === 'GET' || method === 'HEAD') && url.href === location.href && options.historyMode !== 'none') {
            finishScroll(0);
            return;
        }

        saveScroll();
        if (controller) controller.abort();
        controller = new AbortController();
        const localController = controller;
        setBusy(true);

        try {
            const response = await fetch(url.href, {
                method,
                body: method === 'GET' || method === 'HEAD' ? undefined : options.body,
                credentials: 'same-origin',
                cache: 'no-store',
                redirect: 'follow',
                signal: localController.signal,
                headers: {
                    'Accept': HTML_ACCEPT,
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-BP-Navigation': '1',
                    'Cache-Control': 'no-cache'
                }
            });

            const type = response.headers.get('content-type') || '';
            if (!type.includes('text/html')) {
                if (method === 'GET') location.assign(response.url || url.href);
                else location.reload();
                return;
            }

            const html = await response.text();
            if (controller !== localController) return;
            const freshDoc = new DOMParser().parseFromString(html, 'text/html');
            const freshMain = freshDoc.querySelector('.app-main');

            if (!freshMain || !freshDoc.querySelector('.app-shell')) {
                const finalTarget = response.url || url.href;
                if (method === 'GET' || response.redirected) location.assign(finalTarget);
                else location.reload();
                return;
            }

            const finalUrl = (method === 'POST' && !response.redirected)
                ? location.href
                : (response.url || url.href);
            const effectiveHistoryMode = (method === 'POST' && !response.redirected)
                ? 'replace'
                : options.historyMode;
            const scripts = extractScripts(freshMain, finalUrl);
            PAGE_GLOBALS.forEach(name => {
                try { delete window[name]; } catch (_) { window[name] = undefined; }
            });

            if (window.BP_SearchableSelects?.reset) {
                window.BP_SearchableSelects.reset();
            }
            document.querySelectorAll('.modal-backdrop').forEach(node => node.remove());
            document.body.classList.remove('modal-open');
            document.body.style.removeProperty('overflow');
            document.body.style.removeProperty('padding-right');

            const targetScroll = Number.isFinite(Number(options.restoreScroll))
                ? Math.max(0, Number(options.restoreScroll))
                : 0;
            const doSwap = () => {
                swapMain(freshMain, freshDoc);
                window.scrollTo({top:targetScroll,left:0,behavior:'auto'});
            };
            if (document.startViewTransition) {
                const transition = document.startViewTransition(doSwap);
                await transition.updateCallbackDone.catch(() => {});
            } else {
                doSwap();
            }

            if (effectiveHistoryMode === 'push') {
                history.pushState({bpSoftNavigation:true, scrollY:0}, '', finalUrl);
            } else if (effectiveHistoryMode === 'replace') {
                history.replaceState({bpSoftNavigation:true, scrollY:0}, '', finalUrl);
            }

            for (const descriptor of scripts) {
                if (controller !== localController) return;
                try { await runScript(descriptor); } catch (error) { console.error(error); }
            }
            if (controller !== localController) return;

            if (window.BP_SearchableSelects?.enhanceAll) {
                window.BP_SearchableSelects.enhanceAll(document.querySelector('.app-main'));
            }

            document.dispatchEvent(new CustomEvent('bp:navigation:complete', {
                detail: {url: finalUrl}
            }));

            finishScroll(options.restoreScroll ?? 0);

            if (window.matchMedia('(max-width: 991.98px)').matches) {
                document.body.classList.remove('sidebar-open');
                const toggle = document.getElementById('sidebarToggle');
                if (toggle) toggle.setAttribute('aria-expanded', 'false');
            }
        } catch (error) {
            if (error && error.name === 'AbortError') return;
            console.error('Navegación interna:', error);
            if (options.fallback !== false) {
                if (method === 'GET') location.assign(url.href);
                else location.reload();
            }
        } finally {
            if (controller === localController) {
                controller = null;
                setBusy(false);
            }
        }
    };

    document.addEventListener('click', (event) => {
        const link = event.target.closest('a[href]');
        if (shouldUseNativeLink(link, event)) return;

        const url = new URL(link.href, location.href);
        if (url.pathname === location.pathname && url.search === location.search && !url.hash) {
            event.preventDefault();
            finishScroll(0);
            return;
        }

        event.preventDefault();
        navigate(url.href, {historyMode:'push'});
    });

    document.addEventListener('submit', (event) => {
        const form = event.target;
        if (!(form instanceof HTMLFormElement)) return;
        if (form.matches('[data-native-navigation],[data-no-soft-nav]')) return;
        if (form.target && form.target !== '_self') return;

        const method = (form.method || 'get').toLowerCase();
        if (!['get','post'].includes(method)) return;

        const action = new URL(form.action || location.href, location.href);
        if (action.origin !== location.origin || /\/export$/i.test(action.pathname)) return;
        if (/\/logout$/i.test(action.pathname)) return;

        let data;
        try {
            data = new FormData(form, event.submitter || undefined);
        } catch (_) {
            data = new FormData(form);
            if (event.submitter?.name) data.append(event.submitter.name, event.submitter.value || '');
        }

        event.preventDefault();

        if (method === 'get') {
            action.search = '';
            for (const [key, value] of data.entries()) {
                if (value instanceof File) continue;
                action.searchParams.append(key, value);
            }
            navigate(action.href, {historyMode:'push', method:'GET'});
            return;
        }

        navigate(action.href, {
            historyMode:'push',
            method:'POST',
            body:data,
            fallback:true
        });
    });

    window.addEventListener('popstate', (event) => {
        navigate(location.href, {
            historyMode:'none',
            restoreScroll:event.state?.scrollY ?? 0,
            fallback:true
        });
    });

    window.addEventListener('pageshow', () => {
        const save = document.querySelector('[data-documents-form] [data-save]');
        if (save) {
            save.disabled = false;
            save.textContent = 'Guardar borrador';
        }
    });
})();
