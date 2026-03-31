@php
    $activeType = (string) ($reportType ?? 'daily');
    $reportTitle = $activeType === 'monthly'
        ? 'Monthly Report - ' . ($selectedYear ?? '-')
        : ($activeType === 'yearly'
            ? 'Yearly Report'
            : 'Daily Report - ' . ($monthName ?? '-') . ' ' . ($selectedYear ?? '-'));

    $oldExpenseRows = old('expenses');
    if (!is_array($oldExpenseRows)) {
        $oldExpenseRows = [];
    }

    $selectedYearInt = (int) ($selectedYear ?? date('Y'));
    $selectedMonthInt = (int) ($selectedMonth ?? date('n'));
    $prevTimestamp = mktime(0, 0, 0, $selectedMonthInt - 1, 1, $selectedYearInt);
    $nextTimestamp = mktime(0, 0, 0, $selectedMonthInt + 1, 1, $selectedYearInt);
    $prevYear = (int) date('Y', $prevTimestamp);
    $prevMonth = (int) date('n', $prevTimestamp);
    $nextYear = (int) date('Y', $nextTimestamp);
    $nextMonth = (int) date('n', $nextTimestamp);
@endphp

<section class="section financial-page">
    <div class="container">
        <div class="financial-shell">
            <div class="financial-hero">
                <div>
                    <p class="eyebrow">Admin Panel</p>
                    <h1>Financial Report</h1>
                    <p>Income data is generated from approved transactions in Payment Validation.</p>
                </div>
                <div class="financial-hero-pill">
                    <span>Current View</span>
                    <strong>{{ ucfirst($activeType) }}</strong>
                </div>
            </div>

            @if (session('status'))
                <p class="setting-alert success">{{ session('status') }}</p>
            @endif
            @if ($errors->any())
                <p class="setting-alert error">{{ $errors->first() }}</p>
            @endif
            @if (!empty($expenseCostResetThisMonth))
                <p class="setting-alert success">Monthly expense reset applied. Expense names are kept, please input this month cost again.</p>
            @endif
            <p class="setting-alert" data-financial-feedback hidden></p>

            <form method="get" action="{{ route('admin.financial') }}" class="financial-filter-card">
                <div class="financial-filter-grid">
                    <div class="admin-user-level-filter">
                        <label for="financial_type">Report Type</label>
                        <select id="financial_type" name="type">
                            <option value="daily" @selected($activeType === 'daily')>Daily</option>
                            <option value="monthly" @selected($activeType === 'monthly')>Monthly</option>
                            <option value="yearly" @selected($activeType === 'yearly')>Yearly</option>
                        </select>
                    </div>
                    <div class="admin-user-level-filter">
                        <label for="financial_year">Year</label>
                        <select id="financial_year" name="year">
                            @foreach (($yearOptions ?? []) as $year)
                                <option value="{{ $year }}" @selected((int) ($selectedYear ?? 0) === (int) $year)>{{ $year }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="admin-user-level-filter">
                        <label for="financial_month">Month</label>
                        <select id="financial_month" name="month">
                            @for ($month = 1; $month <= 12; $month++)
                                <option value="{{ $month }}" @selected((int) ($selectedMonth ?? 0) === $month)>{{ date('F', mktime(0, 0, 0, $month, 1)) }}</option>
                            @endfor
                        </select>
                    </div>
                    <div class="financial-filter-action">
                        <button type="submit" class="btn">Apply Filter</button>
                    </div>
                </div>
            </form>

            <div data-financial-print-area class="financial-report-area">
                <div class="financial-summary-grid" role="list">
                    @foreach (($summaryCards ?? []) as $card)
                        <article class="financial-summary-card" role="listitem">
                            <p>{{ $card['label'] ?? '-' }}</p>
                            <h3>{{ $card['income_label'] ?? 'Rp 0' }}</h3>
                            <small>{{ (int) ($card['transactions'] ?? 0) }} {{ $card['meta'] ?? 'entry(ies)' }}</small>
                        </article>
                    @endforeach
                </div>

                <article class="financial-card">
                    <div class="financial-card-head">
                        <div>
                            <p class="financial-kicker">Income Breakdown</p>
                            <h2>{{ $reportTitle }}</h2>
                        </div>
                    </div>

                    @if ($activeType === 'daily')
                        <table class="admin-user-table financial-table">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Transactions</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse (($dailyRows ?? []) as $row)
                                    <tr>
                                        <td>{{ $row['label'] }}</td>
                                        <td>{{ $row['transactions'] }}</td>
                                        <td class="financial-action-cell">
                                            <span class="financial-income-actions">
                                                <a href="{{ route('admin.financial.print', ['type' => 'daily', 'period' => ($row['period'] ?? '')]) }}" target="_blank" rel="noopener" class="btn btn-outline">Print</a>
                                                <a href="{{ route('admin.financial.export_pdf', ['type' => 'daily', 'period' => ($row['period'] ?? '')]) }}" class="btn btn-outline">PDF</a>
                                                <a href="{{ route('admin.financial.export_excel', ['type' => 'daily', 'period' => ($row['period'] ?? '')]) }}" class="btn btn-outline">Excel</a>
                                            </span>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="3">No approved payment data for selected month.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    @elseif ($activeType === 'monthly')
                        <table class="admin-user-table financial-table">
                            <thead>
                                <tr>
                                    <th>Month</th>
                                    <th>Transactions</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse (($monthlyRows ?? []) as $row)
                                    <tr>
                                        <td>{{ $row['label'] }}</td>
                                        <td>{{ $row['transactions'] }}</td>
                                        <td class="financial-action-cell">
                                            <span class="financial-income-actions">
                                                <a href="{{ route('admin.financial.print', ['type' => 'monthly', 'period' => ($row['period'] ?? '')]) }}" target="_blank" rel="noopener" class="btn btn-outline">Print</a>
                                                <a href="{{ route('admin.financial.export_pdf', ['type' => 'monthly', 'period' => ($row['period'] ?? '')]) }}" class="btn btn-outline">PDF</a>
                                                <a href="{{ route('admin.financial.export_excel', ['type' => 'monthly', 'period' => ($row['period'] ?? '')]) }}" class="btn btn-outline">Excel</a>
                                            </span>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="3">No approved payment data for selected year.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    @else
                        <table class="admin-user-table financial-table">
                            <thead>
                                <tr>
                                    <th>Year</th>
                                    <th>Transactions</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse (($yearlyRows ?? []) as $row)
                                    <tr>
                                        <td>{{ $row['label'] }}</td>
                                        <td>{{ $row['transactions'] }}</td>
                                        <td class="financial-action-cell">
                                            <span class="financial-income-actions">
                                                <a href="{{ route('admin.financial.print', ['type' => 'yearly', 'period' => ($row['period'] ?? '')]) }}" target="_blank" rel="noopener" class="btn btn-outline">Print</a>
                                                <a href="{{ route('admin.financial.export_pdf', ['type' => 'yearly', 'period' => ($row['period'] ?? '')]) }}" class="btn btn-outline">PDF</a>
                                                <a href="{{ route('admin.financial.export_excel', ['type' => 'yearly', 'period' => ($row['period'] ?? '')]) }}" class="btn btn-outline">Excel</a>
                                            </span>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="3">No approved payment data yet.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    @endif
                </article>
            </div>

            <div class="financial-expense-grid" data-expense-section>
                <article class="financial-card">
                    <div class="financial-card-head">
                        <div>
                            <p class="financial-kicker">Expense Ledger</p>
                            <h2>Expense Cost</h2>
                        </div>
                        <div class="expense-receipt-total">
                            <span>Total Expense</span>
                            <strong data-total-expense data-total-expense-value="{{ (int) ($totalExpenseValue ?? 0) }}">{{ $totalExpenseLabel ?? 'Rp 0' }}</strong>
                        </div>
                    </div>
                    <div class="financial-month-nav">
                        <a
                            href="{{ route('admin.financial', ['type' => $activeType, 'year' => $prevYear, 'month' => $prevMonth]) }}"
                            class="btn btn-outline financial-month-arrow"
                            data-expense-nav
                            aria-label="Previous month"
                            title="Previous month"
                        >&larr;</a>
                        <strong>{{ date('F Y', mktime(0, 0, 0, $selectedMonthInt, 1, $selectedYearInt)) }}</strong>
                        <a
                            href="{{ route('admin.financial', ['type' => $activeType, 'year' => $nextYear, 'month' => $nextMonth]) }}"
                            class="btn btn-outline financial-month-arrow"
                            data-expense-nav
                            aria-label="Next month"
                            title="Next month"
                        >&rarr;</a>
                    </div>

                    <table class="admin-user-table expense-receipt-table">
                        <thead>
                            <tr>
                                <th>Item</th>
                                <th>Amount</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody data-expense-ledger-rows>
                            @forelse (($expenseRows ?? []) as $row)
                                @php
                                    $editFormId = 'expense_edit_' . (int) ($row['expenseid'] ?? 0);
                                @endphp
                                <tr data-expense-ledger-row data-expenseid="{{ (int) ($row['expenseid'] ?? 0) }}">
                                    <td>
                                        <input type="text" name="expense_name" form="{{ $editFormId }}" value="{{ $row['expensename'] }}" placeholder="Expense name" required>
                                    </td>
                                    <td>
                                        <input
                                            type="text"
                                            name="expense_cost"
                                            form="{{ $editFormId }}"
                                            value="{{ (int) ($row['cost_value'] ?? 0) > 0 ? (string) ($row['cost_raw'] ?? '') : '' }}"
                                            placeholder="Amount"
                                            required
                                        >
                                    </td>
                                    <td>
                                        <div class="financial-ledger-actions">
                                            <form method="post" id="{{ $editFormId }}" action="{{ route('admin.financial.expense.update', ['expenseid' => $row['expenseid']]) }}" class="expense-edit-form">
                                                @csrf
                                                <button type="submit" class="btn btn-outline financial-icon-btn" aria-label="Update expense" title="Update expense">&#9998;</button>
                                            </form>
                                            <button
                                                type="button"
                                                class="btn btn-outline financial-icon-btn"
                                                data-delete-expense-row
                                                data-expenseid="{{ (int) ($row['expenseid'] ?? 0) }}"
                                                data-delete-url="{{ route('admin.financial.expense.delete', ['expenseid' => (int) ($row['expenseid'] ?? 0)]) }}"
                                                aria-label="Delete expense"
                                                title="Delete expense"
                                            >&times;</button>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr data-expense-ledger-empty>
                                    <td colspan="3">No expense data yet.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </article>

                <article class="financial-card">
                    <div class="financial-card-head">
                        <div>
                            <p class="financial-kicker">Input Expense</p>
                            <h2>Add New Expense Rows</h2>
                        </div>
                    </div>

                    <form
                        method="post"
                        action="{{ route('admin.financial.expense.store') }}"
                        class="setting-form expense-entry-form"
                        data-expense-form
                        data-delete-url-template="{{ route('admin.financial.expense.delete', ['expenseid' => '__EXPENSE_ID__']) }}"
                        data-update-url-template="{{ route('admin.financial.expense.update', ['expenseid' => '__EXPENSE_ID__']) }}"
                    >
                        @csrf
                        <input type="hidden" name="expense_year" value="{{ (int) ($selectedYear ?? date('Y')) }}">
                        <input type="hidden" name="expense_month" value="{{ (int) ($selectedMonth ?? date('n')) }}">

                        <table class="admin-user-table expense-entry-table">
                            <thead>
                                <tr>
                                    <th>New Item</th>
                                    <th>Amount</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody data-expense-input-rows>
                                @foreach ($oldExpenseRows as $index => $expenseInput)
                                    <tr data-expense-row>
                                        <td>
                                            <input
                                                type="text"
                                                name="expenses[{{ $index }}][name]"
                                                value="{{ (string) ($expenseInput['name'] ?? '') }}"
                                                placeholder="Example: Studio electricity"
                                                required
                                            >
                                        </td>
                                        <td>
                                            <input
                                                type="text"
                                                name="expenses[{{ $index }}][cost]"
                                                value="{{ (string) ($expenseInput['cost'] ?? '') }}"
                                                placeholder="Example: 250000"
                                                required
                                            >
                                        </td>
                                        <td>
                                            <button type="button" class="btn btn-outline" data-remove-expense-row>-</button>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                            <tfoot>
                                <tr>
                                    <td colspan="3">
                                        <button type="button" class="btn expense-add-row-btn" data-add-expense-row>+ Add Row</button>
                                    </td>
                                </tr>
                            </tfoot>
                        </table>

                        <div class="setting-actions">
                            <button type="submit" class="btn">Save Expense</button>
                        </div>
                    </form>
                </article>
            </div>
        </div>
    </div>
</section>
</main>
</div>
