(() => {
    const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    const form = document.querySelector('[data-expense-form]');
    const rowsWrap = document.querySelector('[data-expense-input-rows]');
    const addButton = document.querySelector('[data-add-expense-row]');
    const feedback = document.querySelector('[data-financial-feedback]');

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

    if (!form || !rowsWrap || !addButton) {
        return;
    }

    const getMaxExistingIndex = () => {
        let maxIndex = -1;
        const inputs = rowsWrap.querySelectorAll('input[name^="expenses["]');
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

    let nextRowIndex = getMaxExistingIndex() + 1;

    const buildRow = (index) => {
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

    addButton.addEventListener('click', () => {
        rowsWrap.appendChild(buildRow(nextRowIndex));
        nextRowIndex += 1;
    });

    rowsWrap.addEventListener('click', (event) => {
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

    document.addEventListener('submit', async (event) => {
        const editForm = event.target instanceof HTMLFormElement
            ? event.target
            : null;
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
                const errorMessage = result?.message || 'Failed to update expense.';
                throw new Error(errorMessage);
            }

            if (costInput && result?.expense?.cost_raw) {
                costInput.value = String(result.expense.cost_raw);
            }
            setFeedback(result?.message || 'Expense row updated.', 'success');
        } catch (error) {
            setFeedback(error.message || 'Failed to update expense.', 'error');
        } finally {
            if (submitButton) {
                submitButton.disabled = false;
            }
        }
    });
})();
