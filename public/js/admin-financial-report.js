(() => {
    const form = document.querySelector('[data-expense-form]');
    const rowsWrap = document.querySelector('[data-expense-input-rows]');
    const addButton = document.querySelector('[data-add-expense-row]');
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
})();
