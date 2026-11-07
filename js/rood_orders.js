// ============================================================================
// ROOD Orders Management Script - Dropdown Version with Cache-Busting
// ============================================================================

$(document).ready(function () {
    'use strict';

    // ========== Configuration ==========
    const CONFIG = {
        selectedYear: 'All',
        maxRefTypes: 7,
        allowedFileType: 'application/pdf'
    };

    // CSRF token emitted by csrfMeta() in each page's <head>; required by the
    // insert/update/delete backend endpoints.
    const CSRF_TOKEN = $('meta[name="csrf-token"]').attr('content') || '';

    // ========== Year Filter (segmented tabs) ==========
    // Same #yearTabs markup/behavior on the dashboard (index.php) and every
    // category page (inc/issuance_page.php) — one filter pattern, not two.
    function initializeYearFilter() {
        $('#yearTabs').on('click', 'button[data-year]', function () {
            const $tab = $(this);
            if ($tab.hasClass('active')) return;

            $('#yearTabs button[data-year]').removeClass('active');
            $tab.addClass('active');
            CONFIG.selectedYear = $tab.data('year').toString();

            // Reload whichever of these exist on the current page (both
            // functions no-op safely if their target element isn't present).
            reloadAllTables();
            reloadDashboard();
        });
    }

    // ========== DataTable Initialization ==========
    function initializeDataTables() {
        for (let ref = 1; ref <= CONFIG.maxRefTypes; ref++) {
            const tableId = `#tableRef${ref}`;

            if (!$.fn.DataTable.isDataTable(tableId)) {
                $(tableId).DataTable({
                    ajax: {
                        url: `backend/fetch/rood_fetch_orders.php?ref=${ref}&year=${CONFIG.selectedYear}`,
                        cache: false, // Disable caching
                        data: function(d) {
                            // Add timestamp to prevent caching
                            d._timestamp = new Date().getTime();
                        },
                        error: handleAjaxError
                    },
                    columns: [
                        {
                            data: 'file_name',
                            width: '70%',
                            title: 'File Name',
                            render: (data, type) => type === 'display' ? renderFileName(data) : data
                        },
                        {
                            data: 'issuance_date',
                            width: '15%',
                            title: 'Issuance Date',
                            render: function(data, type, row) {
                                // Use the raw date for sorting and the formatted one for display
                                return type === 'display' ? row.issuance_date_display : data;
                            }
                        },
                        {
                            data: null,
                            width: '15%',
                            title: 'Actions',
                            orderable: false,
                            render: (data, type, row) => renderActionButtons(row, ref)
                        }
                    ],
                    columnDefs: [
                        { targets: [1, 2], className: 'text-center' },
                        // Mobile card-view (see .otrs-table media query in
                        // modules/rooms.css) reads this to label each stacked
                        // field, since the <thead> is hidden below 767px.
                        {
                            targets: '_all',
                            createdCell: function (td, cellData, rowData, row, col) {
                                $(td).attr('data-label', this.api().column(col).header().textContent);
                            }
                        }
                    ],
                    order: [[1, 'desc']], // Recent date at the top (column 1 = Issuance Date)
                    orderCellsTop: true,
                    language: {
                        // DataTables sets this once at init, so it can't reference
                        // the currently-selected year — kept generic and paired
                        // with a real link so a wrong-category/wrong-year guess
                        // isn't a dead end.
                        emptyTable: 'No issuances found for this filter. Try a different year above, or <a href="search">search all categories</a>.',
                        loadingRecords: 'Loading...',
                        processing: 'Processing...'
                    },
                    processing: true,
                    drawCallback: function () {
                        attachButtonHandlers(this.api().table().node());
                    }
                });
            }
        }
    }

    // ========== Render Functions ==========
    function escapeHtml(str) {
        return $('<div>').text(str == null ? '' : str).html();
    }

    // Document names are free text (e.g. "ROO No. 001, s. 2024 - Special
    // Audit: ..."), often embedding an issuance number before the first
    // " - ". Splitting it visually lets users scan the number column
    // without reading the full subject line. Falls back to plain text
    // when no such prefix is present. Also normalizes embedded newlines
    // (some entries were typed as multi-line free text) and HTML-escapes
    // the result, since DataTables does not escape plain string data by
    // default.
    function renderFileName(data) {
        if (!data) return '<span class="text-muted fst-italic">Untitled document</span>';

        const normalized = data.replace(/\s*\r?\n\s*/g, ' ').trim();
        const match = normalized.match(/^(.{3,60}?)\s-\s(.+)$/);

        if (match) {
            return `<div class="issuance-cell">
                        <div class="issuance-no">${escapeHtml(match[1])}</div>
                        <div class="issuance-subject">${escapeHtml(match[2])}</div>
                    </div>`;
        }

        return `<div class="issuance-cell"><div class="issuance-subject">${escapeHtml(normalized)}</div></div>`;
    }

    function renderActionButtons(row, ref) {
        const fileName = row.file_path ? row.file_path.split('/').pop() : '';
        
        // Build buttons based on admin status
        const buttons = [];
        
        // Edit button (Admin only)
        if (typeof isAdmin !== 'undefined' && isAdmin) {
            buttons.push(`
                <button class="btn btn-sm btn-primary edit-btn"
                        data-id="${row.id}"
                        data-ref="${ref}"
                        title="Edit"
                        aria-label="Edit this issuance">
                    <i class="bi bi-pencil-square" aria-hidden="true"></i>
                </button>
            `);
        }

        // View button (Everyone) — routed through rood_view_document.php so
        // the browser shows a clean URL/filename instead of the raw
        // uploads/<category>/<year>/<file>.pdf disk path.
        if (row.file_path) {
            buttons.push(`
                <a href="backend/fetch/rood_view_document.php?id=${row.id}"
                   target="_blank"
                   class="btn btn-sm btn-secondary"
                   title="View ${fileName}"
                   aria-label="View ${fileName}">
                    <i class="bi bi-eye" aria-hidden="true"></i>
                </a>
            `);
        }

        // Delete button (Admin only)
        if (typeof isAdmin !== 'undefined' && isAdmin) {
            buttons.push(`
                <button class="btn btn-sm btn-danger delete-btn"
                        data-id="${row.id}"
                        data-ref="${ref}"
                        title="Delete"
                        aria-label="Delete this issuance">
                    <i class="bi bi-trash" aria-hidden="true"></i>
                </button>
            `);
        }
        
        return `<div class="d-flex justify-content-center gap-1">${buttons.join('')}</div>`;
    }

    // ========== Button Event Handlers ==========
    function attachButtonHandlers(tableElement) {
        const $table = $(tableElement);
        
        // Remove existing handlers to prevent duplicates
        $table.off('click', '.edit-btn');
        $table.off('click', '.delete-btn');
        
        // Edit button handler
        $table.on('click', '.edit-btn', handleEditClick);
        
        // Delete button handler
        $table.on('click', '.delete-btn', handleDeleteClick);
    }

    function handleEditClick() {
        const id = $(this).data('id');
        const ref = $(this).data('ref');
        const $btn = $(this);
        
        // Disable button during request
        $btn.prop('disabled', true);
        
        $.ajax({
            url: `backend/fetch/rood_fetch_single_order.php?id=${id}&_t=${new Date().getTime()}`,
            method: 'GET',
            cache: false, // Disable caching
            dataType: 'json',
            success: function (response) {
                $btn.prop('disabled', false);
                
                if (response.success) {
                    populateEditModal(response.data, ref);
                } else {
                    Toast.fire({
                        icon: 'error',
                        title: 'Failed to fetch record',
                        text: response.message
                    });
                }
            },
            error: function (xhr) {
                $btn.prop('disabled', false);
                handleAjaxError(xhr);
            }
        });
    }

    function handleDeleteClick() {
        const id = $(this).data('id');
        const ref = $(this).data('ref');
        const $btn = $(this);
        
        Swal.fire({
            title: 'Are you sure?',
            text: 'This action cannot be undone!',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'Yes, delete it!',
            cancelButtonText: 'Cancel'
        }).then((result) => {
            if (result.isConfirmed) {
                deleteRecord(id, ref, $btn);
            }
        });
    }

    // ========== Toast Configuration ==========
    const Toast = Swal.mixin({
        toast: true,
        position: 'top-end',
        showConfirmButton: false,
        timer: 3000,
        timerProgressBar: true,
        didOpen: (toast) => {
            toast.addEventListener('mouseenter', Swal.stopTimer);
            toast.addEventListener('mouseleave', Swal.resumeTimer);
        }
    });

    // ========== CRUD Operations ==========
    function deleteRecord(id, ref, $btn) {
        // Disable button during request
        $btn.prop('disabled', true);
        
        $.ajax({
            url: 'backend/delete/rood_delete_reference.php',
            method: 'POST',
            data: { id: id, _csrf_token: CSRF_TOKEN },
            cache: false,
            dataType: 'json',
            success: function (response) {
                $btn.prop('disabled', false);
                
                if (response.success) {
                    Toast.fire({
                        icon: 'success',
                        title: 'Record deleted successfully'
                    });
                    
                    // Use ajax.reload() instead of page reload
                    reloadSpecificTable(ref, true); // true = reset pagination
                    reloadDashboard();
                } else {
                    Toast.fire({
                        icon: 'error',
                        title: 'Deletion Failed',
                        text: response.message
                    });
                }
            },
            error: function (xhr) {
                $btn.prop('disabled', false);
                handleAjaxError(xhr, 'Deletion Failed');
            }
        });
    }

    function populateEditModal(data, ref) {
        $('#editId').val(data.id).data('ref', ref);
        $('#editReferenceType').val(data.reference_name);
        $('#editFileName').val(data.file_name);
        $('#editIssuanceDate').val(data.issuance_date);
        
        // Reset edit mode
        $('#editFileName, #editIssuanceDate').prop('readonly', true);
        $('#editUploadFile').prop('disabled', true);
        $('#enableEditBtn').removeClass('d-none');
        $('#saveEditBtn').addClass('d-none');
        
        $('#editReferenceModal').modal('show');
    }

    // ========== Modal Handlers ==========
    function initializeModalHandlers() {
        // Enable edit mode
        $('#enableEditBtn').off('click').on('click', function () {
            $('#editFileName, #editIssuanceDate').prop('readonly', false);
            $('#editUploadFile').prop('disabled', false);
            $(this).addClass('d-none');
            $('#saveEditBtn').removeClass('d-none');
            $('#editFileName').focus();
        });
        
        // Save edit form
        $('#editReferenceForm').off('submit').on('submit', handleEditSubmit);
        
        // Add reference form
        $('#addReferenceForm').off('submit').on('submit', handleAddSubmit);
        
        // File upload handler
        $('#uploadFile, #editUploadFile').off('change').on('change', handleFileChange);
    }

    function handleEditSubmit(e) {
        e.preventDefault();
        
        if (!validateFileUpload($('#editUploadFile')[0])) return;

        const formData = new FormData(this);
        formData.append('_csrf_token', CSRF_TOKEN);
        const $saveBtn = $('#saveEditBtn');
        const ref = $('#editId').data('ref');
        
        // Disable button during request
        $saveBtn.prop('disabled', true);
        
        $.ajax({
            url: 'backend/update/rood_update_reference.php',
            method: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            cache: false, // Disable caching
            dataType: 'json',
            success: function (response) {
                $saveBtn.prop('disabled', false);
                
                if (response.success) {
                    $('#editReferenceModal').modal('hide');
                    $('#editReferenceForm')[0].reset();
                    
                    Toast.fire({
                        icon: 'success',
                        title: 'Record updated successfully'
                    });
                    
                    // Use ajax.reload() to refresh table without page reload
                    reloadSpecificTable(ref, false); // false = keep current page
                    reloadDashboard();
                } else {
                    Toast.fire({
                        icon: 'error',
                        title: 'Update Failed',
                        text: response.message
                    });
                }
            },
            error: function (xhr) {
                $saveBtn.prop('disabled', false);
                handleAjaxError(xhr, 'Update Failed');
            }
        });
    }

    function handleAddSubmit(e) {
        e.preventDefault();
        
        if (!validateFileUpload($('#uploadFile')[0])) return;

        const formData = new FormData(this);
        formData.append('_csrf_token', CSRF_TOKEN);
        const $submitBtn = $(this).find('button[type="submit"]');
        const referenceType = $('#referenceType').val();
        
        // Disable button during request
        $submitBtn.prop('disabled', true);
        
        $.ajax({
            url: 'backend/insert/rood_insert_reference.php',
            method: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            cache: false, // Disable caching
            dataType: 'json',
            success: function (response) {
                $submitBtn.prop('disabled', false);
                
                if (response.success) {
                    $('#addReferenceModal').modal('hide');
                    $('#addReferenceForm')[0].reset();
                    
                    Toast.fire({
                        icon: 'success',
                        title: 'New reference added successfully'
                    });
                    
                    // Use ajax.reload() to refresh table without page reload
                    reloadSpecificTable(referenceType, true); // true = reset to first page
                    reloadDashboard();
                } else {
                    Toast.fire({
                        icon: 'error',
                        title: 'Addition Failed',
                        text: response.message
                    });
                }
            },
            error: function (xhr) {
                $submitBtn.prop('disabled', false);
                handleAjaxError(xhr, 'Addition Failed');
            }
        });
    }

    // ========== Validation Functions ==========
    function validateFileUpload(fileInput) {
        if (!fileInput || !fileInput.files.length) return true;
        
        const file = fileInput.files[0];
        if (file.type !== CONFIG.allowedFileType) {
            Toast.fire({
                icon: 'error',
                title: 'Invalid File Type',
                text: 'Only PDF files are allowed.'
            });
            fileInput.value = '';
            return false;
        }
        
        // Check file size (max 20MB to be safe with server limits)
        const maxSize = 20 * 1024 * 1024; // 20MB
        if (file.size > maxSize) {
            Toast.fire({
                icon: 'error',
                title: 'File Too Large',
                text: 'Maximum file size is 20MB.'
            });
            fileInput.value = '';
            return false;
        }
        
        return true;
    }

    function handleFileChange() {
        const file = this.files[0];
        const $preview = $(this).siblings('.file-preview');
        
        if (file) {
            if (file.type === CONFIG.allowedFileType) {
                if ($preview.length) {
                    $preview.html(`<small class="text-success"><i class="bi bi-check-circle"></i> ${file.name}</small>`);
                }
            } else {
                Toast.fire({
                    icon: 'error',
                    title: 'Invalid File',
                    text: 'Only PDF files are allowed.'
                });
                this.value = '';
                if ($preview.length) {
                    $preview.empty();
                }
            }
        }
    }

    // ========== Utility Functions ==========
    function reloadAllTables() {
        for (let ref = 1; ref <= CONFIG.maxRefTypes; ref++) {
            const tableId = `#tableRef${ref}`;
            if ($.fn.DataTable.isDataTable(tableId)) {
                const dataTable = $(tableId).DataTable();
                // Update URL with cache-busting timestamp
                const newUrl = `backend/fetch/rood_fetch_orders.php?ref=${ref}&year=${CONFIG.selectedYear}&_t=${new Date().getTime()}`;
                dataTable.ajax.url(newUrl).load(null, false);
            }
        }
    }

    function reloadSpecificTable(ref, resetPaging = false) {
        const tableId = `#tableRef${ref}`;
        if ($.fn.DataTable.isDataTable(tableId)) {
            const table = $(tableId).DataTable();
            
            // Add cache-busting parameter to the current URL
            const currentUrl = table.ajax.url();
            const separator = currentUrl.includes('?') ? '&' : '?';
            const newUrl = currentUrl.split('&_t=')[0].split('?_t=')[0] + `${separator}_t=${new Date().getTime()}`;
            
            // Update URL and reload with cache-busting
            table.ajax.url(newUrl).load(null, resetPaging);
        }
    }

    function reloadDashboard() {
        const year = CONFIG.selectedYear;
        const $dashboardElement = $('#dashboardItem');
        
        if (!$dashboardElement.length) return;
        
        // Show loading skeleton
        const skeletonHTML = `
            ${Array(4).fill().map(() => `
                <div class="col-12 col-sm-6 col-lg-4 col-xl-3">
                    <div class="card h-100 border-0 shadow-sm">
                        <div class="card-body p-4">
                            <div class="d-flex justify-content-between align-items-start mb-3">
                                <div class="placeholder-glow w-100">
                                    <div class="placeholder bg-secondary rounded-3" style="width: 56px; height: 56px;"></div>
                                </div>
                                <div class="placeholder-glow">
                                    <span class="placeholder bg-secondary rounded-pill" style="width: 50px; height: 32px;"></span>
                                </div>
                            </div>
                            <div class="placeholder-glow">
                                <span class="placeholder col-12 mb-2"></span>
                                <span class="placeholder col-8 mb-3"></span>
                            </div>
                            <div class="placeholder-glow">
                                <span class="placeholder col-6"></span>
                            </div>
                        </div>
                        <div class="card-footer bg-transparent border-0 pt-0 pb-3 px-4">
                            <div class="placeholder-glow">
                                <span class="placeholder col-12" style="height: 4px;"></span>
                            </div>
                        </div>
                    </div>
                </div>
            `).join('')}
        `;
        
        $dashboardElement.css('opacity', '0.5').html(skeletonHTML);
        
        $.ajax({
            url: `backend/fetch/rood_fetch_reference_counts.php?year=${year}&_t=${new Date().getTime()}`,
            method: 'GET',
            cache: false, // Disable caching
            success: function (html) {
                $dashboardElement.css('opacity', '0');
                
                setTimeout(() => {
                    $dashboardElement.html(html);
                    $dashboardElement.css('opacity', '1');
                    
                    // Add staggered animation to cards
                    const cards = $dashboardElement.find('[class*="col-"]');
                    if (cards.length > 0) {
                        cards.each(function(index) {
                            $(this).css({
                                'opacity': '0',
                                'transform': 'translateY(20px)',
                                'transition': 'opacity 0.4s ease, transform 0.4s ease'
                            });
                            
                            setTimeout(() => {
                                $(this).css({
                                    'opacity': '1',
                                    'transform': 'translateY(0)'
                                });
                            }, index * 100);
                        });
                    }
                }, 300);
            },
            error: function () {
                $dashboardElement.css('opacity', '1').html(`
                    <div class="col-12">
                        <div class="alert alert-warning border-0 shadow-sm d-flex align-items-center" role="alert">
                            <i class="bi bi-exclamation-triangle-fill fs-4 me-3"></i>
                            <div>
                                <h6 class="alert-heading mb-1">Failed to update dashboard</h6>
                                <p class="mb-0">Unable to reload dashboard counts.</p>
                            </div>
                        </div>
                    </div>
                `);
            }
        });
    }

    // ========== Recently Added (dashboard widget) ==========
    function loadRecentIssuances() {
        const $wrap = $('#recentIssuances');
        if (!$wrap.length) return;

        $.ajax({
            url: `backend/fetch/rood_recent.php?limit=8&_t=${new Date().getTime()}`,
            method: 'GET',
            cache: false,
            dataType: 'json',
            success: function (response) {
                const rows = response.data || [];

                if (!rows.length) {
                    $wrap.html(`
                        <div class="text-muted text-center py-3" style="font-size: var(--otrs-text-sm);">
                            <i class="bi bi-inbox me-1"></i>No documents added yet.
                        </div>
                    `);
                    return;
                }

                const itemsHtml = rows.map(row => {
                    const cell = renderFileName(row.file_name);
                    const category = escapeHtml(row.reference_name);
                    const categoryHref = row.category_page && row.category_page !== '#' ? row.category_page : '#';
                    const date = escapeHtml(row.issuance_date_display);
                    const viewHref = row.file_path ? `backend/fetch/rood_view_document.php?id=${row.id}` : null;

                    return `
                        <div class="d-flex align-items-start justify-content-between gap-3 py-2 recent-issuance-row">
                            <div class="flex-grow-1 min-width-0">
                                ${cell}
                                <div class="mt-1">
                                    <a href="${categoryHref}" class="badge text-decoration-none" style="background: var(--otrs-info-bg); color: var(--otrs-info); font-weight:600;">${category}</a>
                                    <span class="text-muted ms-2" style="font-size: var(--otrs-text-xs);"><i class="bi bi-calendar-event me-1"></i>${date}</span>
                                </div>
                            </div>
                            ${viewHref ? `<a href="${viewHref}" target="_blank" class="btn btn-sm btn-secondary flex-shrink-0" title="View" aria-label="View ${escapeHtml(row.file_name || '')}"><i class="bi bi-eye" aria-hidden="true"></i></a>` : ''}
                        </div>
                    `;
                }).join('<hr class="my-1 text-muted opacity-25">');

                $wrap.html(itemsHtml);
            },
            error: function () {
                $wrap.html(`
                    <div class="otrs-alert otrs-alert-danger mb-0">
                        <i class="bi bi-exclamation-triangle-fill"></i>
                        <div>Failed to load recently added issuances.</div>
                    </div>
                `);
            }
        });
    }

    function handleAjaxError(xhr, title = 'Error') {
        let errorMsg = 'An error occurred';
        
        try {
            const response = JSON.parse(xhr.responseText);
            errorMsg = response.message || errorMsg;
        } catch (e) {
            errorMsg = xhr.statusText || errorMsg;
        }
        
        console.error('AJAX Error:', xhr);
        
        Toast.fire({
            icon: 'error',
            title: title,
            text: errorMsg
        });
    }

    // ========== Initialization ==========
    function initialize() {
        initializeYearFilter();
        initializeDataTables();
        initializeModalHandlers();
        loadRecentIssuances();
    }

    // Start the application
    initialize();
});