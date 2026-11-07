// ============================================================================
// ROOD Global Search — search issuances by document name across all categories
// ============================================================================

$(document).ready(function () {
    'use strict';

    const $input = $('#searchInput');
    const $results = $('#searchResultsWrap');
    let debounceTimer = null;
    let activeRequest = null;

    function escapeHtml(str) {
        return $('<div>').text(str || '').html();
    }

    // Mirrors js/rood_orders.js's renderFileName split (number prefix vs.
    // subject), plus highlights the matched query term in the subject line.
    function renderFileName(fileName, query) {
        if (!fileName) return '<span class="text-muted fst-italic">Untitled document</span>';

        const normalized = fileName.replace(/\s*\r?\n\s*/g, ' ').trim();
        const match = normalized.match(/^(.{3,60}?)\s-\s(.+)$/);
        const prefix = match ? match[1] : null;
        const subject = match ? match[2] : normalized;

        const escapedSubject = highlight(escapeHtml(subject), query);

        return `<div class="issuance-cell">
                    ${prefix ? `<div class="issuance-no">${highlight(escapeHtml(prefix), query)}</div>` : ''}
                    <div class="issuance-subject">${escapedSubject}</div>
                </div>`;
    }

    // Wraps the (already-escaped) query term in <mark> for scannability.
    // Operates on escaped HTML, so the query itself is escaped+regex-escaped
    // before matching to avoid breaking the markup or introducing injection.
    function highlight(escapedText, query) {
        if (!query) return escapedText;
        const escapedQuery = escapeHtml(query).replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
        if (!escapedQuery) return escapedText;
        return escapedText.replace(new RegExp(escapedQuery, 'gi'), (m) => `<mark>${m}</mark>`);
    }

    function renderPrompt(message, icon = 'bi-info-circle-fill', variant = 'info') {
        $results.html(`
            <div class="otrs-alert otrs-alert-${variant}">
                <i class="bi ${icon}"></i>
                <div>${message}</div>
            </div>
        `);
    }

    function renderResults(rows, query) {
        if (!rows.length) {
            renderPrompt(`No issuances matched &ldquo;${escapeHtml(query)}&rdquo;. Try a shorter or different keyword.`, 'bi-inbox', 'info');
            return;
        }

        const rowsHtml = rows.map(row => {
            const fileNameCell = renderFileName(row.file_name, query);
            const category = escapeHtml(row.reference_name);
            const date = escapeHtml(row.issuance_date_display);
            const rawFileName = row.file_path ? row.file_path.split('/').pop() : '';
            // Routed through rood_view_document.php so the browser shows a
            // clean URL/filename instead of the raw uploads/... disk path.
            const viewLink = row.file_path
                ? `<a href="backend/fetch/rood_view_document.php?id=${row.id}" target="_blank" class="btn btn-sm btn-secondary" title="View ${escapeHtml(rawFileName)}" aria-label="View ${escapeHtml(rawFileName)}"><i class="bi bi-eye" aria-hidden="true"></i> View</a>`
                : '';
            const categoryLink = row.category_page && row.category_page !== '#'
                ? `<a href="${escapeHtml(row.category_page)}">${category}</a>`
                : category;

            return `
                <tr>
                    <td data-label="File Name">${fileNameCell}</td>
                    <td class="text-center" data-label="Category">${categoryLink}</td>
                    <td class="text-center" data-label="Issuance Date">${date}</td>
                    <td class="text-center" data-label="View">${viewLink}</td>
                </tr>
            `;
        }).join('');

        $results.html(`
            <div class="card border-0 shadow-sm">
                <div class="table-responsive">
                    <table class="table table-bordered table-hover table-striped otrs-table mb-0" style="width: 100%">
                        <thead class="table-light">
                            <tr>
                                <th class="py-3 fw-semibold">File Name</th>
                                <th class="text-center py-3 fw-semibold">Category</th>
                                <th class="text-center py-3 fw-semibold">Issuance Date</th>
                                <th class="text-center py-3 fw-semibold">View</th>
                            </tr>
                        </thead>
                        <tbody>${rowsHtml}</tbody>
                    </table>
                </div>
                <div class="card-footer bg-white border-0 text-muted small py-2">
                    ${rows.length} result${rows.length === 1 ? '' : 's'}${rows.length === 100 ? ' (showing first 100)' : ''}
                </div>
            </div>
        `);
    }

    function runSearch(query) {
        if (activeRequest) {
            activeRequest.abort();
        }

        renderPrompt('Searching&hellip;', 'bi-hourglass-split', 'info');

        activeRequest = $.ajax({
            url: `backend/fetch/rood_search.php?q=${encodeURIComponent(query)}`,
            method: 'GET',
            cache: false,
            dataType: 'json',
            success: function (response) {
                renderResults(response.data || [], query);
            },
            error: function (xhr) {
                if (xhr.statusText === 'abort') return;
                renderPrompt('Something went wrong while searching. Please try again.', 'bi-exclamation-triangle-fill', 'danger');
            }
        });
    }

    $input.on('input', function () {
        const query = $(this).val().trim();

        clearTimeout(debounceTimer);

        if (query.length < 2) {
            renderPrompt('Start typing above to search issuances.');
            return;
        }

        debounceTimer = setTimeout(() => runSearch(query), 300);
    });

    // Support arriving here via ?q=... (e.g. the dashboard's search box)
    // by pre-filling and immediately running the search.
    const params = new URLSearchParams(window.location.search);
    const initialQuery = (params.get('q') || '').trim();
    if (initialQuery.length >= 2) {
        $input.val(initialQuery);
        runSearch(initialQuery);
    }
});
