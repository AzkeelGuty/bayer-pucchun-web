document.addEventListener('DOMContentLoaded', () => {
    const documentsPage = document.querySelector('[data-documents-page]');

    if (documentsPage) {
        const searchInput = documentsPage.querySelector(
            '[data-document-search]'
        );

        const statusSelect = documentsPage.querySelector(
            '[data-document-status]'
        );

        const clearButton = documentsPage.querySelector(
            '[data-clear-documents]'
        );

        const rows = [
            ...documentsPage.querySelectorAll('[data-document-row]')
        ];

        const emptyRows = [
            ...documentsPage.querySelectorAll('[data-empty-row]')
        ];

        const pageSize = 10;
        let currentPage = 1;

        let pagination = documentsPage.querySelector(
            '[data-documents-pagination]'
        );

        if (!pagination) {
            pagination = document.createElement('nav');
            pagination.dataset.documentsPagination = '';
            pagination.setAttribute(
                'aria-label',
                'Paginación de documentos'
            );

            const tableCard = documentsPage.querySelector(
                '.documents-table-card'
            );

            if (tableCard) {
                tableCard.after(pagination);
            }
        }

        const renderPagination = (totalPages) => {
            if (!pagination) {
                return;
            }

            if (totalPages <= 1) {
                pagination.innerHTML = '';
                pagination.classList.add('d-none');
                return;
            }

            pagination.classList.remove('d-none');

            let html = `
                <ul class="pagination justify-content-end mt-3">
                    <li class="page-item ${
                        currentPage === 1 ? 'disabled' : ''
                    }">
                        <button
                            type="button"
                            class="page-link"
                            data-page="${currentPage - 1}"
                            ${
                                currentPage === 1
                                    ? 'disabled'
                                    : ''
                            }
                        >
                            Anterior
                        </button>
                    </li>
            `;

            for (let page = 1; page <= totalPages; page++) {
                html += `
                    <li class="page-item ${
                        page === currentPage ? 'active' : ''
                    }">
                        <button
                            type="button"
                            class="page-link"
                            data-page="${page}"
                        >
                            ${page}
                        </button>
                    </li>
                `;
            }

            html += `
                    <li class="page-item ${
                        currentPage === totalPages ? 'disabled' : ''
                    }">
                        <button
                            type="button"
                            class="page-link"
                            data-page="${currentPage + 1}"
                            ${
                                currentPage === totalPages
                                    ? 'disabled'
                                    : ''
                            }
                        >
                            Siguiente
                        </button>
                    </li>
                </ul>
            `;

            pagination.innerHTML = html;
        };

        const showPageRows = (matchingRows) => {
            const totalPages = Math.ceil(
                matchingRows.length / pageSize
            );

            if (totalPages === 0) {
                rows.forEach((row) => {
                    row.classList.add('d-none');
                });

                renderPagination(0);
                return;
            }

            if (currentPage > totalPages) {
                currentPage = totalPages;
            }

            rows.forEach((row) => {
                row.classList.add('d-none');
            });

            const start = (currentPage - 1) * pageSize;
            const end = start + pageSize;

            matchingRows.slice(start, end).forEach((row) => {
                row.classList.remove('d-none');
            });

            renderPagination(totalPages);
        };

        const filterRows = (resetPage = false) => {
            if (resetPage) {
                currentPage = 1;
            }

            const search = (
                searchInput?.value || ''
            ).trim().toLowerCase();

            const status = (
                statusSelect?.value || ''
            ).toUpperCase();

            const matchingRows = rows.filter((row) => {
                const rowSearch = (
                    row.dataset.search || ''
                ).toLowerCase();

                const rowStatus = (
                    row.dataset.status || ''
                ).toUpperCase();

                const matchesSearch = (
                    !search || rowSearch.includes(search)
                );

                const matchesStatus = (
                    !status || rowStatus === status
                );

                return matchesSearch && matchesStatus;
            });

            showPageRows(matchingRows);

            emptyRows.forEach((emptyRow) => {
                const shouldHide = (
                    rows.length > 0 &&
                    matchingRows.length > 0
                );

                emptyRow.classList.toggle('d-none', shouldHide);
            });
        };

        searchInput?.addEventListener('input', () => {
            filterRows(true);
        });

        statusSelect?.addEventListener('change', () => {
            filterRows(true);
        });

        clearButton?.addEventListener('click', () => {
            if (searchInput) {
                searchInput.value = '';
            }

            if (statusSelect) {
                statusSelect.value = '';
            }

            filterRows(true);
        });

        pagination?.addEventListener('click', (event) => {
            const button = event.target.closest('[data-page]');

            if (!button || button.disabled) {
                return;
            }

            const page = Number(button.dataset.page);

            if (!Number.isInteger(page) || page < 1) {
                return;
            }

            currentPage = page;
            filterRows(false);
        });

        filterRows();
    }

    const workflowButtons = document.querySelectorAll(
        '[data-document-action]'
    );

    workflowButtons.forEach((button) => {
        button.addEventListener('click', () => {
            const action = button.dataset.documentAction || '';
            const labels = {
                validar: 'Validar',
                observar: 'Observar',
                devolver: 'Devolver a borrador',
                publicar: 'Publicar',
            };

            const label = labels[action] || 'Esta acción';

            alert(
                `${label} está preparada visualmente. ` +
                'Su ejecución real dependerá de la conexión del backend.'
            );
        });
    });

    const documentForm = document.querySelector(
        '[data-documents-form]'
    );

    if (!documentForm) {
        return;
    }

    const detailsBody = documentForm.querySelector(
        '[data-details-body]'
    );

    const addDetailButton = documentForm.querySelector(
        '[data-add-detail]'
    );

    const detailTemplate = detailsBody?.querySelector(
        '[data-detail-row]'
    );

    if (
        !detailsBody ||
        !addDetailButton ||
        !detailTemplate
    ) {
        return;
    }

    const updateRemoveButtons = () => {
        const detailRows = detailsBody.querySelectorAll(
            '[data-detail-row]'
        );

        const removeButtons = detailsBody.querySelectorAll(
            '[data-remove-detail]'
        );

        removeButtons.forEach((button) => {
            button.disabled = detailRows.length === 1;
        });
    };

    const clearRowValues = (row) => {
        row.querySelectorAll('input').forEach((input) => {
            input.value = '';
        });
    };

    const updateRowNames = (row, index) => {
        row.querySelectorAll('[name]').forEach((field) => {
            field.name = field.name.replace(
                /details\[\d+\]/g,
                `details[${index}]`
            );
        });
    };

    const addDetailRow = () => {
        const detailRows = detailsBody.querySelectorAll(
            '[data-detail-row]'
        );

        const newIndex = detailRows.length;
        const newRow = detailTemplate.cloneNode(true);

        newRow.dataset.detailIndex = newIndex;

        updateRowNames(newRow, newIndex);
        clearRowValues(newRow);

        detailsBody.appendChild(newRow);
        updateRemoveButtons();
    };

    addDetailButton.addEventListener(
        'click',
        addDetailRow
    );

    detailsBody.addEventListener('click', (event) => {
        const removeButton = event.target.closest(
            '[data-remove-detail]'
        );

        if (!removeButton) {
            return;
        }

        const detailRows = detailsBody.querySelectorAll(
            '[data-detail-row]'
        );

        if (detailRows.length > 1) {
            removeButton.closest('[data-detail-row]').remove();
            updateRemoveButtons();
        }
    });

    documentForm.addEventListener('submit', (event) => {
        const quantities = detailsBody.querySelectorAll(
            'input[name$="[cantidad]"]'
        );

        let valid = true;

        quantities.forEach((quantity) => {
            if (
                !quantity.value ||
                Number(quantity.value) <= 0
            ) {
                quantity.classList.add('is-invalid');
                valid = false;
            } else {
                quantity.classList.remove('is-invalid');
            }
        });

        if (!valid) {
            event.preventDefault();

            alert(
                'La cantidad debe ser mayor que cero.'
            );
        }
    });

    updateRemoveButtons();
});