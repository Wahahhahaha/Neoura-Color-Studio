(() => {
    const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    const feedback = document.querySelector('[data-financial-feedback]');

    const refs = {
        form: null,
        filterForm: null,
        rowsWrap: null,
        ledgerRowsWrap: null,
        reportArea: null,
        currentViewNode: null,
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

    const formatCompact = (value) => {
        const number = Number.isFinite(value) ? Math.max(0, Math.floor(value)) : 0;
        return new Intl.NumberFormat('id-ID', {
            notation: 'compact',
            maximumFractionDigits: 1,
        }).format(number);
    };

    const parseSeries = (value) => {
        if (!value || typeof value !== 'string') {
            return [];
        }

        try {
            const parsed = JSON.parse(value);
            if (!Array.isArray(parsed)) {
                return [];
            }
            return parsed;
        } catch (_error) {
            return [];
        }
    };

    const createLegend = (incomeLabel, outcomeLabel, incomePercent = 0, outcomePercent = 0) => `
        <div class="financial-chart-legend">
            <span class="is-income">Income ${escapeHtml(incomeLabel)} (${Math.round(incomePercent)}%)</span>
            <span class="is-outcome">Outcome ${escapeHtml(outcomeLabel)} (${Math.round(outcomePercent)}%)</span>
        </div>
    `;

    const polarPoint = (cx, cy, r, angle) => {
        const radians = ((angle - 90) * Math.PI) / 180;
        return {
            x: cx + (r * Math.cos(radians)),
            y: cy + (r * Math.sin(radians)),
        };
    };

    const pieSlicePath = (cx, cy, r, startAngle, endAngle) => {
        const start = polarPoint(cx, cy, r, endAngle);
        const end = polarPoint(cx, cy, r, startAngle);
        const largeArc = endAngle - startAngle <= 180 ? '0' : '1';
        return `M ${cx} ${cy} L ${start.x} ${start.y} A ${r} ${r} 0 ${largeArc} 0 ${end.x} ${end.y} Z`;
    };

    const renderPieChart = (node, incomeValue, outcomeValue, incomeLabel, outcomeLabel) => {
        if (!node) {
            return;
        }

        const income = Number.isFinite(incomeValue) ? Math.max(0, incomeValue) : 0;
        const outcome = Number.isFinite(outcomeValue) ? Math.max(0, outcomeValue) : 0;
        const total = income + outcome;
        const incomePercent = total > 0 ? (income / total) * 100 : 0;
        const outcomePercent = total > 0 ? (outcome / total) * 100 : 0;
        const radius = 72;
        const circumference = 2 * Math.PI * radius;
        const incomeLength = total > 0 ? (income / total) * circumference : circumference;
        const outcomeLength = Math.max(0, circumference - incomeLength);

        node.innerHTML = `
            <svg class="financial-chart-svg" viewBox="0 0 280 250" role="img" aria-label="Income and outcome pie chart">
                <defs>
                    <linearGradient id="chartIncomeGradient" x1="0%" y1="0%" x2="100%" y2="100%">
                        <stop offset="0%" stop-color="#f2ad7b"></stop>
                        <stop offset="100%" stop-color="#d88f5c"></stop>
                    </linearGradient>
                    <linearGradient id="chartOutcomeGradient" x1="0%" y1="0%" x2="100%" y2="100%">
                        <stop offset="0%" stop-color="#e5d8cf"></stop>
                        <stop offset="100%" stop-color="#c3b1a5"></stop>
                    </linearGradient>
                </defs>
                <circle cx="140" cy="116" r="${radius}" class="financial-chart-donut-track"></circle>
                <circle
                    cx="140"
                    cy="116"
                    r="${radius}"
                    class="financial-chart-donut-slice-income"
                    stroke-dasharray="${incomeLength} ${Math.max(0, circumference - incomeLength)}"
                ></circle>
                <circle
                    cx="140"
                    cy="116"
                    r="${radius}"
                    class="financial-chart-donut-slice-outcome"
                    stroke-dasharray="${outcomeLength} ${Math.max(0, circumference - outcomeLength)}"
                    stroke-dashoffset="${-incomeLength}"
                ></circle>
                <circle cx="140" cy="116" r="48" fill="#ffffff"></circle>
                <text x="140" y="106" text-anchor="middle" class="financial-chart-center-kicker">TOTAL</text>
                <text x="140" y="128" text-anchor="middle" class="financial-chart-center-value">${escapeHtml(formatCompact(total))}</text>
                <text x="140" y="146" text-anchor="middle" class="financial-chart-center-text">${Math.round(incomePercent)}% income</text>
            </svg>
            ${createLegend(incomeLabel, outcomeLabel, incomePercent, outcomePercent)}
        `;
    };

    const buildPolylinePoints = (values, chartWidth, chartHeight, padX, padY, maxValue) => values
        .map((value, index) => {
            const safeValue = Number.isFinite(value) ? Math.max(0, value) : 0;
            const x = values.length === 1
                ? (padX + ((chartWidth - (padX * 2)) / 2))
                : (padX + (index * (chartWidth - (padX * 2)) / (values.length - 1)));
            const y = chartHeight - padY - ((safeValue / maxValue) * (chartHeight - (padY * 2)));
            return `${x},${y}`;
        })
        .join(' ');

    const normalizeChartSeries = (labels, incomeValues, outcomeValues) => {
        const maxLength = Math.max(1, labels.length, incomeValues.length, outcomeValues.length);
        const normalizedLabels = Array.from({ length: maxLength }, (_, index) => {
            const raw = labels[index];
            if (typeof raw === 'string' && raw.trim() !== '') {
                return raw;
            }
            return `P${index + 1}`;
        });

        const normalizedIncome = Array.from({ length: maxLength }, (_, index) => {
            const value = Number(incomeValues[index]);
            return Number.isFinite(value) && value >= 0 ? value : 0;
        });

        const normalizedOutcome = Array.from({ length: maxLength }, (_, index) => {
            const value = Number(outcomeValues[index]);
            return Number.isFinite(value) && value >= 0 ? value : 0;
        });

        return {
            labels: normalizedLabels,
            income: normalizedIncome,
            outcome: normalizedOutcome,
        };
    };

    const sumSeries = (values) => values.reduce((carry, value) => {
        const parsed = Number(value);
        if (!Number.isFinite(parsed) || parsed < 0) {
            return carry;
        }
        return carry + parsed;
    }, 0);

    const shouldShowLabel = (index, total) => {
        if (total <= 6) {
            return true;
        }
        if (index === 0 || index === total - 1) {
            return true;
        }
        const step = Math.ceil(total / 4);
        return index % step === 0;
    };

    const renderLineChart = (node, labels, incomeValues, outcomeValues, incomeLabel, outcomeLabel) => {
        if (!node) {
            return;
        }

        const normalized = normalizeChartSeries(labels, incomeValues, outcomeValues);
        const chartLabels = normalized.labels;
        const income = normalized.income;
        const outcome = normalized.outcome;
        const maxValue = Math.max(1, ...income, ...outcome);
        const width = 500;
        const height = 220;
        const padX = 34;
        const padY = 24;

        const incomePoints = buildPolylinePoints(income, width, height, padX, padY, maxValue);
        const outcomePoints = buildPolylinePoints(outcome, width, height, padX, padY, maxValue);

        const yGrid = [0, 0.25, 0.5, 0.75, 1].map((fraction) => {
            const y = height - padY - (fraction * (height - (padY * 2)));
            return `<line x1="${padX}" y1="${y}" x2="${width - padX}" y2="${y}" class="financial-chart-grid-line"></line>`;
        }).join('');

        const xLabels = chartLabels.map((label, index) => {
            if (!shouldShowLabel(index, chartLabels.length)) {
                return '';
            }
            const x = chartLabels.length === 1
                ? (padX + ((width - (padX * 2)) / 2))
                : (padX + (index * (width - (padX * 2)) / (chartLabels.length - 1)));
            return `<text x="${x}" y="${height - 4}" text-anchor="middle" class="financial-chart-axis-label">${escapeHtml(label)}</text>`;
        }).join('');

        const incomeDots = income.map((value, index) => {
            const x = chartLabels.length === 1
                ? (padX + ((width - (padX * 2)) / 2))
                : (padX + (index * (width - (padX * 2)) / (chartLabels.length - 1)));
            const y = height - padY - ((value / maxValue) * (height - (padY * 2)));
            return `<circle cx="${x}" cy="${y}" r="3.5" class="financial-chart-dot-income"></circle>`;
        }).join('');

        const outcomeDots = outcome.map((value, index) => {
            const x = chartLabels.length === 1
                ? (padX + ((width - (padX * 2)) / 2))
                : (padX + (index * (width - (padX * 2)) / (chartLabels.length - 1)));
            const y = height - padY - ((value / maxValue) * (height - (padY * 2)));
            return `<circle cx="${x}" cy="${y}" r="3.5" class="financial-chart-dot-outcome"></circle>`;
        }).join('');

        const singlePointGuide = chartLabels.length === 1
            ? `<line x1="${padX}" y1="${height - padY}" x2="${width - padX}" y2="${height - padY}" class="financial-chart-single-guide"></line>`
            : '';

        node.innerHTML = `
            <svg class="financial-chart-svg" viewBox="0 0 ${width} ${height}" role="img" aria-label="Income and outcome line chart">
                ${yGrid}
                <line x1="${padX}" y1="${height - padY}" x2="${width - padX}" y2="${height - padY}" class="financial-chart-axis-line"></line>
                <line x1="${padX}" y1="${padY}" x2="${padX}" y2="${height - padY}" class="financial-chart-axis-line"></line>
                ${singlePointGuide}
                <polyline points="${outcomePoints}" class="financial-chart-line-outcome"></polyline>
                <polyline points="${incomePoints}" class="financial-chart-line-income"></polyline>
                ${outcomeDots}
                ${incomeDots}
                ${xLabels}
            </svg>
            ${createLegend(incomeLabel, outcomeLabel)}
        `;
    };

    const renderBarChart = (node, labels, incomeValues, outcomeValues, incomeLabel, outcomeLabel) => {
        if (!node) {
            return;
        }

        const normalized = normalizeChartSeries(labels, incomeValues, outcomeValues);
        const chartLabels = normalized.labels;
        const income = normalized.income;
        const outcome = normalized.outcome;
        const maxValue = Math.max(1, ...income, ...outcome);
        const width = 500;
        const height = 220;
        const padX = 20;
        const padY = 24;
        const chartWidth = width - (padX * 2);
        const slotWidth = chartWidth / chartLabels.length;
        const barWidth = Math.max(8, Math.min(18, (slotWidth - 8) / 2));

        const yGrid = [0, 0.25, 0.5, 0.75, 1].map((fraction) => {
            const y = height - padY - (fraction * (height - (padY * 2)));
            return `<line x1="${padX}" y1="${y}" x2="${width - padX}" y2="${y}" class="financial-chart-grid-line"></line>`;
        }).join('');

        const bars = chartLabels.map((label, index) => {
            const incomeValue = Number.isFinite(income[index]) ? Math.max(0, income[index]) : 0;
            const outcomeValue = Number.isFinite(outcome[index]) ? Math.max(0, outcome[index]) : 0;
            const baseX = padX + (index * slotWidth) + ((slotWidth - ((barWidth * 2) + 4)) / 2);
            const incomeHeight = (incomeValue / maxValue) * (height - (padY * 2));
            const outcomeHeight = (outcomeValue / maxValue) * (height - (padY * 2));
            const incomeY = height - padY - incomeHeight;
            const outcomeY = height - padY - outcomeHeight;
            const showText = shouldShowLabel(index, chartLabels.length);
            const labelText = showText
                ? `<text x="${padX + (index * slotWidth) + (slotWidth / 2)}" y="${height - 4}" text-anchor="middle" class="financial-chart-axis-label">${escapeHtml(label)}</text>`
                : '';

            return `
                <rect x="${baseX}" y="${outcomeY}" width="${barWidth}" height="${outcomeHeight}" class="financial-chart-bar-outcome"></rect>
                <rect x="${baseX + barWidth + 4}" y="${incomeY}" width="${barWidth}" height="${incomeHeight}" class="financial-chart-bar-income"></rect>
                ${labelText}
            `;
        }).join('');

        node.innerHTML = `
            <svg class="financial-chart-svg" viewBox="0 0 ${width} ${height}" role="img" aria-label="Income and outcome bar chart">
                ${yGrid}
                <line x1="${padX}" y1="${height - padY}" x2="${width - padX}" y2="${height - padY}" class="financial-chart-axis-line"></line>
                ${bars}
            </svg>
            ${createLegend(incomeLabel, outcomeLabel)}
        `;
    };

    const initFinancialCharts = () => {
        const chartWrap = document.querySelector('[data-financial-charts]');
        if (!chartWrap) {
            return;
        }

        const activePeriodRaw = (chartWrap.getAttribute('data-active-period') || 'daily').toLowerCase();
        const activePeriod = ['daily', 'monthly', 'yearly'].includes(activePeriodRaw) ? activePeriodRaw : 'daily';
        const labels = parseSeries(
            chartWrap.getAttribute(`data-chart-${activePeriod}-labels`)
            || chartWrap.getAttribute('data-chart-labels')
            || '[]',
        ).map((item) => String(item ?? ''));
        const incomeSeries = parseSeries(
            chartWrap.getAttribute(`data-chart-${activePeriod}-income-series`)
            || chartWrap.getAttribute('data-chart-income-series')
            || '[]',
        ).map((item) => Number(item) || 0);
        const outcomeSeries = parseSeries(
            chartWrap.getAttribute(`data-chart-${activePeriod}-outcome-series`)
            || chartWrap.getAttribute('data-chart-outcome-series')
            || '[]',
        ).map((item) => Number(item) || 0);
        const incomeValue = sumSeries(incomeSeries);
        const outcomeValue = sumSeries(outcomeSeries);
        const incomeLabel = formatRupiah(incomeValue);
        const outcomeLabel = formatRupiah(outcomeValue);
        const stageNode = chartWrap.querySelector('[data-chart-stage]');
        const activeTypeRaw = (chartWrap.getAttribute('data-active-chart') || 'pie').toLowerCase();
        const activeType = ['pie', 'line', 'bar'].includes(activeTypeRaw) ? activeTypeRaw : 'pie';

        if (stageNode) {
            if (activeType === 'line') {
                renderLineChart(stageNode, labels, incomeSeries, outcomeSeries, incomeLabel, outcomeLabel);
            } else if (activeType === 'bar') {
                renderBarChart(stageNode, labels, incomeSeries, outcomeSeries, incomeLabel, outcomeLabel);
            } else {
                renderPieChart(stageNode, incomeValue, outcomeValue, incomeLabel, outcomeLabel);
            }
        }

        const titleNode = chartWrap.querySelector('[data-chart-title]');
        if (titleNode) {
            const typeLabel = activeType === 'line' ? 'Line' : (activeType === 'bar' ? 'Bar' : 'Pie');
            const periodLabel = activePeriod === 'monthly' ? 'Monthly' : (activePeriod === 'yearly' ? 'Yearly' : 'Daily');
            titleNode.textContent = `Income vs Outcome - ${periodLabel} - ${typeLabel}`;
        }

        const switchButtons = chartWrap.querySelectorAll('[data-chart-switch]');
        switchButtons.forEach((button) => {
            const type = (button.getAttribute('data-chart-switch') || '').toLowerCase();
            const isActive = type === activeType;
            button.classList.toggle('is-active', isActive);
            button.setAttribute('aria-pressed', isActive ? 'true' : 'false');
        });

        const periodButtons = chartWrap.querySelectorAll('[data-period-switch]');
        periodButtons.forEach((button) => {
            const period = (button.getAttribute('data-period-switch') || '').toLowerCase();
            const isActive = period === activePeriod;
            button.classList.toggle('is-active', isActive);
            button.setAttribute('aria-pressed', isActive ? 'true' : 'false');
        });
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
        refs.filterForm = document.querySelector('[data-financial-filter-form]');
        refs.rowsWrap = document.querySelector('[data-expense-input-rows]');
        refs.ledgerRowsWrap = document.querySelector('[data-expense-ledger-rows]');
        refs.reportArea = document.querySelector('[data-financial-print-area]');
        refs.currentViewNode = document.querySelector('[data-financial-current-view]');
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

    const replaceReportArea = (nextHtml) => {
        const parser = new DOMParser();
        const doc = parser.parseFromString(nextHtml, 'text/html');
        const nextArea = doc.querySelector('[data-financial-print-area]');
        const currentArea = document.querySelector('[data-financial-print-area]');
        if (!nextArea || !currentArea) {
            throw new Error('Failed to load financial report area.');
        }

        currentArea.replaceWith(nextArea);
    };

    const replaceCurrentView = (nextHtml) => {
        const parser = new DOMParser();
        const doc = parser.parseFromString(nextHtml, 'text/html');
        const nextNode = doc.querySelector('[data-financial-current-view]');
        const currentNode = document.querySelector('[data-financial-current-view]');
        if (!nextNode || !currentNode) {
            return;
        }

        currentNode.textContent = nextNode.textContent || '';
    };

    const replaceFilterForm = (nextHtml) => {
        const parser = new DOMParser();
        const doc = parser.parseFromString(nextHtml, 'text/html');
        const nextForm = doc.querySelector('[data-financial-filter-form]');
        const currentForm = document.querySelector('[data-financial-filter-form]');
        if (!nextForm || !currentForm) {
            throw new Error('Failed to load financial filter.');
        }

        currentForm.replaceWith(nextForm);
    };

    const fetchAndReplaceFinancialSections = async (url) => {
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
            throw new Error('Failed to load financial report.');
        }

        replaceReportArea(html);
        replaceExpenseSection(html);
        replaceCurrentView(html);
        replaceFilterForm(html);
        refreshRefs();
        initFinancialCharts();

        const nextUrl = new URL(url, window.location.origin);
        window.history.replaceState({}, '', nextUrl.toString());
    };

    refreshRefs();
    initFinancialCharts();

    if (!refs.form || !refs.rowsWrap || !refs.addButton) {
        return;
    }

    document.addEventListener('submit', async (event) => {
        const filterForm = event.target instanceof HTMLFormElement ? event.target : null;
        if (!filterForm || !filterForm.matches('[data-financial-filter-form]')) {
            return;
        }

        event.preventDefault();
        clearFeedback();

        const submitBtn = filterForm.querySelector('button[type="submit"]');
        if (submitBtn) {
            submitBtn.disabled = true;
        }

        try {
            const url = new URL(filterForm.action, window.location.origin);
            const params = new URLSearchParams(new FormData(filterForm));
            url.search = params.toString();
            await fetchAndReplaceFinancialSections(url.toString());
        } catch (error) {
            setFeedback(error?.message || 'Failed to load financial report.', 'error');
        } finally {
            if (submitBtn) {
                submitBtn.disabled = false;
            }
        }
    });

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
        const periodButton = event.target instanceof Element ? event.target.closest('[data-period-switch]') : null;
        if (periodButton) {
            const chartWrap = periodButton.closest('[data-financial-charts]');
            if (!chartWrap) {
                return;
            }

            const targetPeriod = (periodButton.getAttribute('data-period-switch') || 'daily').toLowerCase();
            chartWrap.setAttribute('data-active-period', targetPeriod);
            initFinancialCharts();
            return;
        }

        const switchButton = event.target instanceof Element ? event.target.closest('[data-chart-switch]') : null;
        if (switchButton) {
            const chartWrap = switchButton.closest('[data-financial-charts]');
            if (!chartWrap) {
                return;
            }

            const targetType = (switchButton.getAttribute('data-chart-switch') || 'pie').toLowerCase();
            chartWrap.setAttribute('data-active-chart', targetType);
            initFinancialCharts();
            return;
        }

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
