(() => {
    const escapeCsv = (value) => {
        const text = String(value ?? '').replace(/\s+/g, ' ').trim();
        return '"' + text.replace(/"/g, '""') + '"';
    };

    const safeFilename = (value) => String(value || 'fieldpulse-table')
        .trim()
        .toLowerCase()
        .replace(/[^a-z0-9]+/g, '-')
        .replace(/^-+|-+$/g, '') || 'fieldpulse-table';

    const enhance = (table, tableIndex) => {
        if (table.dataset.tableTools === 'off' || table.dataset.tableToolsReady === 'true') return;

        const tbody = table.tBodies?.[0];
        if (!tbody) return;

        const rows = Array.from(tbody.rows).filter((row) => {
            if (!row.cells.length) return false;
            if (row.cells.length === 1 && Number(row.cells[0].colSpan || 1) > 1) return false;
            return true;
        });

        if (rows.length < 2) return;

        table.dataset.tableToolsReady = 'true';

        const toolbar = document.createElement('div');
        toolbar.className = 'fp-table-tools';
        toolbar.innerHTML = [
            '<div class="fp-table-tools-main">',
            '  <div class="fp-table-search-wrap"><input type="search" class="fp-table-search" placeholder="Search table..." aria-label="Search table"></div>',
            '  <label class="fp-table-page-size-label">Show <select class="fp-table-page-size" aria-label="Rows per page"><option value="10">10</option><option value="25" selected>25</option><option value="50">50</option><option value="100">100</option><option value="all">All</option></select> records</label>',
            '  <button type="button" class="fp-table-export">Export CSV</button>',
            '</div>',
            '<div class="fp-table-tools-meta">',
            '  <span class="fp-table-count"></span>',
            '  <div class="fp-table-pager"><button type="button" class="fp-table-prev">Previous</button><span class="fp-table-page"></span><button type="button" class="fp-table-next">Next</button></div>',
            '</div>',
        ].join('');

        const pageHasSearch = Boolean(document.querySelector('main input[name="q"], main input[type="search"]:not(.fp-table-search)'));
        const searchWrap = toolbar.querySelector('.fp-table-search-wrap');
        if (pageHasSearch && searchWrap) searchWrap.hidden = true;

        table.parentElement?.insertBefore(toolbar, table);

        const search = toolbar.querySelector('.fp-table-search');
        const pageSize = toolbar.querySelector('.fp-table-page-size');
        const exportButton = toolbar.querySelector('.fp-table-export');
        const count = toolbar.querySelector('.fp-table-count');
        const previous = toolbar.querySelector('.fp-table-prev');
        const next = toolbar.querySelector('.fp-table-next');
        const pageText = toolbar.querySelector('.fp-table-page');

        let query = '';
        let currentPage = 1;
        let perPage = 25;

        try {
            const stored = localStorage.getItem('fieldpulse-table-page-size');
            if (stored && ['10', '25', '50', '100', 'all'].includes(stored)) {
                pageSize.value = stored;
                perPage = stored === 'all' ? Number.POSITIVE_INFINITY : Number(stored);
            }
        } catch (_) {}

        const rowMatches = (row) => !query || row.innerText.toLowerCase().includes(query);

        const filteredRows = () => rows.filter(rowMatches);

        const render = () => {
            const filtered = filteredRows();
            const totalPages = Number.isFinite(perPage)
                ? Math.max(1, Math.ceil(filtered.length / perPage))
                : 1;

            currentPage = Math.min(Math.max(1, currentPage), totalPages);
            const start = Number.isFinite(perPage) ? (currentPage - 1) * perPage : 0;
            const end = Number.isFinite(perPage) ? start + perPage : filtered.length;
            const visible = new Set(filtered.slice(start, end));

            rows.forEach((row) => {
                row.hidden = !visible.has(row);
            });

            const shown = Math.min(filtered.length, Number.isFinite(perPage) ? perPage : filtered.length);
            const from = filtered.length ? start + 1 : 0;
            const to = filtered.length ? Math.min(end, filtered.length) : 0;

            count.textContent = query
                ? 'Showing ' + from + '-' + to + ' of ' + filtered.length + ' matching records'
                : 'Showing ' + from + '-' + to + ' of ' + filtered.length + ' records';

            pageText.textContent = 'Page ' + currentPage + ' of ' + totalPages;
            previous.disabled = currentPage <= 1;
            next.disabled = currentPage >= totalPages;
            toolbar.classList.toggle('fp-table-no-pages', totalPages <= 1);

            if (!shown && rows.length) {
                count.textContent = 'No matching records';
            }
        };

        search?.addEventListener('input', () => {
            query = search.value.trim().toLowerCase();
            currentPage = 1;
            render();
        });

        pageSize?.addEventListener('change', () => {
            perPage = pageSize.value === 'all' ? Number.POSITIVE_INFINITY : Number(pageSize.value);
            currentPage = 1;
            try {
                localStorage.setItem('fieldpulse-table-page-size', pageSize.value);
            } catch (_) {}
            render();
        });

        previous?.addEventListener('click', () => {
            if (currentPage <= 1) return;
            currentPage -= 1;
            render();
        });

        next?.addEventListener('click', () => {
            const filtered = filteredRows();
            const totalPages = Number.isFinite(perPage) ? Math.max(1, Math.ceil(filtered.length / perPage)) : 1;
            if (currentPage >= totalPages) return;
            currentPage += 1;
            render();
        });

        exportButton?.addEventListener('click', () => {
            const headers = Array.from(table.tHead?.rows?.[0]?.cells || []);
            const includedColumns = headers
                .map((cell, index) => ({index, label: cell.innerText.trim()}))
                .filter((column) => column.label.toLowerCase() !== 'actions');

            const exportRows = filteredRows();
            const csv = [
                includedColumns.map((column) => escapeCsv(column.label)).join(','),
                ...exportRows.map((row) => includedColumns.map((column) => {
                    return escapeCsv(row.cells[column.index]?.innerText || '');
                }).join(',')),
            ].join('\r\n');

            const blob = new Blob(['\uFEFF' + csv], {type: 'text/csv;charset=utf-8'});
            const url = URL.createObjectURL(blob);
            const link = document.createElement('a');
            const heading = document.querySelector('main h1')?.innerText || 'fieldpulse-table';
            link.href = url;
            link.download = safeFilename(heading) + '-' + (tableIndex + 1) + '.csv';
            document.body.appendChild(link);
            link.click();
            link.remove();
            URL.revokeObjectURL(url);
        });

        render();
    };

    const boot = () => {
        document.querySelectorAll('main table').forEach(enhance);
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot, {once: true});
    } else {
        boot();
    }
})();
