document.addEventListener('DOMContentLoaded', () => {
    const documentsPage = document.querySelector('[data-documents-page]');

    if (documentsPage) {
        const searchInput = documentsPage.querySelector('[data-document-search]');
        const statusSelect = documentsPage.querySelector('[data-document-status]');
        const clearButton = documentsPage.querySelector('[data-clear-documents]');
        const rows = [...documentsPage.querySelectorAll('[data-document-row]')];
        const emptyRows = [...documentsPage.querySelectorAll('[data-empty-row]')];

        const filterRows = () => {
            const search = (searchInput?.value || '').trim().toLowerCase();
            const status = (statusSelect?.value || '').toUpperCase();
            let visibleRows = 0;

            rows.forEach((row) => {
                const rowSearch = (row.dataset.search || '').toLowerCase();
                const rowStatus = (row.dataset.status || '').toUpperCase();

                const matchesSearch = !search || rowSearch.includes(search);
                const matchesStatus = !status || rowStatus === status;
                const visible = matchesSearch && matchesStatus;

                row.classList.toggle('d-none', !visible);

                if (visible) {
                    visibleRows++;
                }
            });

            emptyRows.forEach((emptyRow) => {
                emptyRow.classList.toggle(
                    'd-none',
                    rows.length > 0 && visibleRows > 0
                );
            });
        };

        searchInput?.addEventListener('input', filterRows);
        statusSelect?.addEventListener('change', filterRows);

        clearButton?.addEventListener('click', () => {
            if (searchInput) {
                searchInput.value = '';
            }

            if (statusSelect) {
                statusSelect.value = '';
            }

            filterRows();
        });

        filterRows();
    }

    const documentForm = document.querySelector('[data-documents-form]');

    if (!documentForm) {
        return;
    }

    const detailsBody = documentForm.querySelector('[data-details-body]');
    const addDetailButton = documentForm.querySelector('[data-add-detail]');
    const detailTemplate = detailsBody?.querySelector('[data-detail-row]');

    if (!detailsBody || !addDetailButton || !detailTemplate) {
        return;
    }

    const updateRemoveButtons = () => {
        const rows = detailsBody.querySelectorAll('[data-detail-row]');
        const removeButtons = detailsBody.querySelectorAll('[data-remove-detail]');

        removeButtons.forEach((button) => {
            button.disabled = rows.length === 1;
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
        const rows = detailsBody.querySelectorAll('[data-detail-row]');
        const newIndex = rows.length;
        const newRow = detailTemplate.cloneNode(true);

        newRow.dataset.detailIndex = newIndex;
        updateRowNames(newRow, newIndex);
        clearRowValues(newRow);

        detailsBody.appendChild(newRow);
        updateRemoveButtons();
    };

    addDetailButton.addEventListener('click', addDetailRow);

    detailsBody.addEventListener('click', (event) => {
        const removeButton = event.target.closest('[data-remove-detail]');

        if (!removeButton) {
            return;
        }

        const rows = detailsBody.querySelectorAll('[data-detail-row]');

        if (rows.length > 1) {
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
            if (!quantity.value || Number(quantity.value) <= 0) {
                quantity.classList.add('is-invalid');
                valid = false;
            } else {
                quantity.classList.remove('is-invalid');
            }
        });

        if (!valid) {
            event.preventDefault();
            alert('La cantidad debe ser mayor que cero.');
        }
    });

    updateRemoveButtons();
});