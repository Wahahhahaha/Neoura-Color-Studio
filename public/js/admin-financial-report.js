(() => {
    const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    const feedback = document.querySelector('[data-financial-feedback]');

    const refs = {
        form: null,
        rowsWrap: null,
        ledgerRowsWrap: null,
        addButton: null,
        totalExpenseNode: null,
        deleteUrlTemplate: '',
        updateUrlTemplate: '',
        nextRowIndex: 0,
    };

    const setFeedback = (message, type = 'success') => {
        if (!feedback) {
            return;
        }
        feedback.hidden = false;
        feedback.classList.remove('success', 'error');
        feedback.classList.add(type === 'error' ? 'error' : 'success');
        feedback.textContent = message;
    };

    const clearFeedback = () => {
        if (!feedback) {
            return;
        }
        feedback.hidden = true;
        feedback.classList.remove('success', 'error');
        feedback.textContent = '';
    };

    const escapeHtml = (value) => String(value ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');

    const formatRupiah = (value) => {
        const number = Number.isFinite(value) ? Math.max(0, Math.floor(value)) : 0;
        return `Rp ${new Intl.NumberFormat('id-ID').format(number)}`;
    };

    const updateTotalExpense = (value, label) => {
        if (!refs.totalExpenseNode) {
            return;
        }
        const safeValue = Number.isFinite(value) ? Math.max(0, Math.floor(value)) : 0;
        refs.totalExpenseNode.setAttribute('data-total-expense-value', String(safeValue));
        refs.totalExpenseNode.textContent = label && String(label).trim() !== '' ? String(label) : formatRupiah(safeValue);
    };

    const removeLedgerEmptyRow = () => {
        if (!refs.ledgerRowsWrap) {
            return;
        }
        const emptyRow = refs.ledgerRowsWrap.querySelector('[data-expense-ledger-empty]');
        if (emptyRow) {
            emptyRow.remove();
        }
    };

    const ensureLedgerEmptyRow = () => {
        if (!refs.ledgerRowsWrap || refs.ledgerRowsWrap.querySelector('[data-expense-ledger-row]')) {
            return;
        }

        removeLedgerEmptyRow();
        const emptyRow = document.createElement('tr');
        emptyRow.setAttribute('data-expense-ledger-empty', '');
        emptyRow.innerHTML = '<td colspan="3">No expense data yet.</td>';
        refs.ledgerRowsWrap.appendChild(emptyRow);
    };

    const getMaxExistingIndex = () => {
        if (!refs.rowsWrap) {
            return -1;
        }

        let maxIndex = -1;
        const inputs = refs.rowsWrap.querySelectorAll('input[name^="expenses["]');
        inputs.forEach((input) => {
            const name = input.getAttribute('name') || '';
            const match = name.match(/^expenses\[(\d+)\]/);
            if (!match) {
                return;
            }

            const idx = Number(match[1]);
            if (Number.isFinite(idx) && idx > maxIndex) {
                maxIndex = idx;
            }
        });

        return maxIndex;
    };

    const buildInputRow = (index) => {
        const row = document.createElement('tr');
        row.setAttribute('data-expense-row', '');
        row.innerHTML = `
            <td>
                <input
                    type="text"
                    name="expenses[${index}][name]"
                    placeholder="Example: Studio electricity"
                    required
                >
            </td>
            <td>
                <input
                    type="text"
                    name="expenses[${index}][cost]"
                    placeholder="Example: 250000"
                    required
                >
            </td>
            <td>
                <button type="button" class="btn btn-outline" data-remove-expense-row>-</button>
            </td>
        `;
        return row;
    };

    const buildLedgerRow = (rowData) => {
        if (!refs.ledgerRowsWrap) {
            return;
        }
        const expenseId = Number(rowData?.expenseid || 0);
        if (!Number.isFinite(expenseId) || expenseId <= 0) {
            return;
        }

        removeLedgerEmptyRow();

        const editFormId = `expense_edit_${expenseId}`;
        const deleteUrl = refs.deleteUrlTemplate.includes('__EXPENSE_ID__')
            ? refs.deleteUrlTemplate.replace('__EXPENSE_ID__', String(expenseId))
            : '';
        const updateUrl = refs.updateUrlTemplate.includes('__EXPENSE_ID__')
            ? refs.updateUrlTemplate.replace('__EXPENSE_ID__', String(expenseId))
            : `/financial-report/expense/${expenseId}/update`;

        const row = document.createElement('tr');
        row.setAttribute('data-expense-ledger-row', '');
        row.setAttribute('data-expenseid', String(expenseId));
        row.innerHTML = `
            <td>
                <input type="text" name="expense_name" form="${editFormId}" value="${escapeHtml(rowData.expensename || '')}" placeholder="Expense name" required>
            </td>
            <td>
                <input type="text" name="expense_cost" form="${editFormId}" value="${escapeHtml(rowData.cost_raw || '')}" placeholder="Amount" required>
            </td>
            <td>
                <div class="financial-ledger-actions">
                    <form method="post" id="${editFormId}" action="${escapeHtml(updateUrl)}" class="expense-edit-form">
                        <input type="hidden" name="_token" value="${escapeHtml(csrf)}">
                        <button type="submit" class="btn btn-outline financial-icon-btn" aria-label="Update expense" title="Update expense">&#9998;</button>
                    </form>
                    <button type="button" class="btn btn-outline financial-icon-btn" data-delete-expense-row data-expenseid="${expenseId}" data-delete-url="${escapeHtml(deleteUrl)}" aria-label="Delete expense" title="Delete expense">&times;</button>
                </div>
            </td>
        `;

        refs.ledgerRowsWrap.prepend(row);
    };

    const refreshRefs = () => {
        refs.form = document.querySelector('[data-expense-form]');
        refs.rowsWrap = document.querySelector('[data-expense-input-rows]');
        refs.ledgerRowsWrap = document.querySelector('[data-expense-ledger-rows]');
        refs.addButton = document.querySelector('[data-add-expense-row]');
        refs.totalExpenseNode = document.querySelector('[data-total-expense]');
        refs.deleteUrlTemplate = refs.form?.getAttribute('data-delete-url-template') || '';
        refs.updateUrlTemplate = refs.form?.getAttribute('data-update-url-template') || '';
        refs.nextRowIndex = getMaxExistingIndex() + 1;
    };

    const replaceExpenseSection = (nextHtml) => {
        const parser = new DOMParser();
        const doc = parser.parseFromString(nextHtml, 'text/html');
        const nextSection = doc.querySelector('[data-expense-section]');
        const currentSection = document.querySelector('[data-expense-section]');
        if (!nextSection || !currentSection) {
            throw new Error('Failed to load expense section.');
        }
        currentSection.replaceWith(nextSection);
        refreshRefs();
    };

    refreshRefs();

    if (!refs.form || !refs.rowsWrap || !refs.addButton) {
        return;
    }

    document.addEventListener('click', async (event) => {
        const monthNavButton = event.target instanceof Element ? event.target.closest('[data-expense-nav]') : null;
        if (!monthNavButton) {
            return;
        }

        event.preventDefault();
        clearFeedback();

        const url = monthNavButton.getAttribute('href') || '';
        if (!url) {
            return;
        }

        monthNavButton.setAttribute('aria-disabled', 'true');
        try {
            const response = await fetch(url, {
                method: 'GET',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'text/html',
                },
                credentials: 'same-origin',
            });

            const html = await response.text();
            if (!response.ok) {
                throw new Error('Failed to load expense month.');
            }

            replaceExpenseSection(html);
        } catch (error) {
            setFeedback(error?.message || 'Failed to load expense month.', 'error');
        } finally {
            monthNavButton.removeAttribute('aria-disabled');
        }
    });

    document.addEventListener('click', (event) => {
        const addRowButton = event.target instanceof Element ? event.target.closest('[data-add-expense-row]') : null;
        if (addRowButton) {
            if (!refs.rowsWrap) {
                return;
            }

            refs.rowsWrap.appendChild(buildInputRow(refs.nextRowIndex));
            refs.nextRowIndex += 1;
            return;
        }

        const removeButton = event.target instanceof Element ? event.target.closest('[data-remove-expense-row]') : null;
        if (!removeButton) {
            return;
        }

        const row = removeButton.closest('[data-expense-row]');
        if (!row) {
            return;
        }

        row.remove();
    });

    document.addEventListener('click', async (event) => {
        const deleteButton = event.target instanceof Element ? event.target.closest('[data-delete-expense-row]') : null;
        if (!deleteButton) {
            return;
        }

        event.preventDefault();
        clearFeedback();

        const row = deleteButton.closest('[data-expense-ledger-row]');
        const deleteUrl = deleteButton.getAttribute('data-delete-url') || '';
        if (!row || !deleteUrl) {
            return;
        }

        deleteButton.disabled = true;

        try {
            const payload = new FormData();
            payload.set('_token', csrf);

            const response = await fetch(deleteUrl, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': csrf,
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json',
                },
                credentials: 'same-origin',
                body: payload,
            });

            const result = await response.json().catch(() => ({}));
            if (!response.ok || result?.status !== 'ok') {
                throw new Error(result?.message || 'Failed to delete expense row.');
            }

            row.remove();
            ensureLedgerEmptyRow();
            updateTotalExpense(Number(result?.total_expense_value || 0), result?.total_expense_label || '');
            setFeedback(result?.message || 'Expense row deleted.', 'success');
        } catch (error) {
            setFeedback(error?.message || 'Failed to delete expense row.', 'error');
        } finally {
            deleteButton.disabled = false;
        }
    });

    document.addEventListener('submit', async (event) => {
        const formNode = event.target instanceof HTMLFormElement ? event.target : null;
        if (!formNode || !formNode.matches('[data-expense-form]')) {
            return;
        }

        event.preventDefault();
        clearFeedback();

        const submitButton = formNode.querySelector('button[type="submit"]');
        if (submitButton) {
            submitButton.disabled = true;
        }

        try {
            const response = await fetch(formNode.action, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': csrf,
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json',
                },
                credentials: 'same-origin',
                body: new FormData(formNode),
            });

            const result = await response.json().catch(() => ({}));
            if (!response.ok || result?.status !== 'ok') {
                throw new Error(result?.message || 'Failed to add expense row.');
            }

            const insertedRows = Array.isArray(result?.rows) ? result.rows : [];
            insertedRows.forEach((rowData) => buildLedgerRow(rowData));
            updateTotalExpense(Number(result?.total_expense_value || 0), result?.total_expense_label || '');
            setFeedback(result?.message || 'Expense row(s) added.', 'success');

            if (refs.rowsWrap) {
                refs.rowsWrap.innerHTML = '';
                refs.nextRowIndex = 0;
                refs.rowsWrap.appendChild(buildInputRow(refs.nextRowIndex));
                refs.nextRowIndex += 1;
            }
        } catch (error) {
            setFeedback(error?.message || 'Failed to add expense row.', 'error');
        } finally {
            if (submitButton) {
                submitButton.disabled = false;
            }
        }
    });

    document.addEventListener('submit', async (event) => {
        const editForm = event.target instanceof HTMLFormElement ? event.target : null;
        if (!editForm || !editForm.classList.contains('expense-edit-form')) {
            return;
        }

        event.preventDefault();
        clearFeedback();

        const submitButton = editForm.querySelector('button[type="submit"]');
        const formId = editForm.getAttribute('id') || '';
        const nameInput = formId ? document.querySelector(`input[form="${formId}"][name="expense_name"]`) : null;
        const costInput = formId ? document.querySelector(`input[form="${formId}"][name="expense_cost"]`) : null;

        const payload = new FormData(editForm);
        payload.set('expense_name', (nameInput?.value || '').trim());
        payload.set('expense_cost', (costInput?.value || '').trim());

        if (!payload.get('expense_name') || !payload.get('expense_cost')) {
            setFeedback('Expense name and amount are required.', 'error');
            return;
        }

        if (submitButton) {
            submitButton.disabled = true;
        }

        try {
            const response = await fetch(editForm.action, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': csrf,
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json',
                },
                credentials: 'same-origin',
                body: payload,
            });

            const result = await response.json().catch(() => ({}));
            if (!response.ok || result?.status !== 'ok') {
                throw new Error(result?.message || 'Failed to update expense.');
            }

            if (costInput && result?.expense?.cost_raw) {
                costInput.value = String(result.expense.cost_raw);
            }
            setFeedback(result?.message || 'Expense row updated.', 'success');
        } catch (error) {
            setFeedback(error?.message || 'Failed to update expense.', 'error');
        } finally {
            if (submitButton) {
                submitButton.disabled = false;
            }
        }
    });
})();
