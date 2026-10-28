{{--
    Progressive AJAX tables. Markup contract:
      <div id="x" data-ajax-table> ... table, pagination ... </div>
      <form method="GET" data-ajax-filter="x"> ... </form>      (optional)
      <a data-ajax href="..."> inside the region (pager, sort links)
      <select data-per-page> inside the region (rendered by the pager)
    Without JS everything still works as normal links/forms.
--}}
<style>
    [data-ajax-table] { position: relative; min-height: 4rem; }
    [data-ajax-table][data-loading] > * { opacity: .45; pointer-events: none; transition: opacity .15s; }
    [data-ajax-table][data-loading]::after {
        content: ''; position: absolute; top: 5rem; left: 50%;
        width: 2rem; height: 2rem; margin-left: -1rem;
        border: 3px solid #c7d2fe; border-top-color: #4f46e5; border-radius: 50%;
        animation: ajax-spin .7s linear infinite;
    }
    @keyframes ajax-spin { to { transform: rotate(360deg); } }
</style>
<script>
    (() => {
        let controller = null;

        async function load(region, url, push = true) {
            controller?.abort();
            controller = new AbortController();
            region.setAttribute('data-loading', '');
            region.setAttribute('aria-busy', 'true');

            try {
                const res = await fetch(url, {
                    headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'text/html' },
                    signal: controller.signal,
                    credentials: 'same-origin',
                });
                if (!res.ok) throw new Error('bad response: ' + res.status);

                const html = await res.text();
                const doc = new DOMParser().parseFromString(html, 'text/html');
                const fresh = doc.getElementById(region.id);
                if (!fresh) throw new Error('region missing');

                region.innerHTML = fresh.innerHTML;
                if (push) history.pushState({}, '', url);
            } catch (e) {
                if (e.name === 'AbortError') return;
                location.href = url; // fall back to a normal navigation
                return;
            } finally {
                region.removeAttribute('data-loading');
                region.removeAttribute('aria-busy');
            }
        }

        window.ajaxTableLoad = load;

        const regionOf = (el) => el.closest('[data-ajax-table]');

        // Back to page 1 everywhere: "page" plus any named pager such as "overdue_page".
        const resetPages = (url) => [...url.searchParams.keys()]
            .filter((k) => k === 'page' || k.endsWith('_page'))
            .forEach((k) => url.searchParams.delete(k));

        window.handlePerPage = function(select) {
            if (!select || !select.value) return;
            const region = regionOf(select);
            const url = new URL(location.href);
            url.searchParams.set('per_page', select.value);
            resetPages(url);

            if (region) {
                load(region, url.toString());
            } else {
                location.href = url.toString();
            }
        };

        document.addEventListener('click', (e) => {
            const a = e.target.closest('a[data-ajax]');
            const region = a && regionOf(a);
            if (!region || e.ctrlKey || e.metaKey || e.shiftKey || e.button !== 0) return;
            e.preventDefault();
            load(region, a.href);
        });

        document.addEventListener('change', (e) => {
            const select = e.target.closest('select[data-per-page]');
            if (!select) return;
            window.handlePerPage(select);
        });

        document.addEventListener('submit', (e) => {
            const form = e.target.closest('form[data-ajax-filter]');
            const region = form && document.getElementById(form.dataset.ajaxFilter);
            if (!region) return;
            e.preventDefault();

            const url = new URL(location.href);
            for (const el of form.elements) {
                if (!el.name) continue;
                url.searchParams.delete(el.name);
            }
            new FormData(form).forEach((value, key) => { if (value !== '') url.searchParams.set(key, value); });
            resetPages(url);
            load(region, url.toString());
        });

        window.addEventListener('popstate', () => location.reload());
    })();
</script>
