<section class="section">
    <div class="container">
        <div class="setting-wrap">
            <div class="section-head">
                <p class="eyebrow">Admin Panel</p>
                <h1>Financial Report</h1>

                <p>Financial report page is ready and can be filled with report content next.</p>
            </div>
                <p>Income is calculated from approved payments in Payment Validation.</p>
            </div>
            @if (session('status'))
                <p class="setting-alert success">{{ session('status') }}</p>
            @endif
            @if ($errors->any())
                <p class="setting-alert error">{{ $errors->first() }}</p>
            @endif

            <div class="admin-user-table-wrap expense-receipt-card">
                <div class="expense-receipt-head">
                    <div>
                        <p class="expense-receipt-kicker">Expense Ledger</p>
                        <h3>Expense Cost</h3>
                    </div>
                    <div class="expense-receipt-total">
                        <span>Total Expense</span>
                        <strong>{{ $totalExpenseLabel ?? 'Rp 0' }}</strong>
                    </div>
                </div>

                <table class="admin-user-table expense-receipt-table">
                    <thead>
                        <tr>
                            <th>Item</th>
                            <th>Amount</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse (($expenseRows ?? []) as $row)
                            @php
                                $editFormId = 'expense_edit_' . (int) ($row['expenseid'] ?? 0);
                            @endphp
                            <tr>
                                <td>
                                    <input
                                        type="text"
                                        name="expense_name"
                                        form="{{ $editFormId }}"
                                        value="{{ $row['expensename'] }}"
                                        placeholder="Expense name"
                                        required
                                    >
                                </td>
                                <td>
                                    <input
                                        type="text"
                                        name="expense_cost"
                                        form="{{ $editFormId }}"
                                        value="{{ (string) ($row['cost_raw'] ?? '') }}"
                                        placeholder="Amount"
                                        required
                                    >
                                </td>
                                <td>
                                    <form method="post" id="{{ $editFormId }}" action="{{ route('admin.financial.expense.update', ['expenseid' => $row['expenseid']]) }}" class="expense-edit-form">
                                        @csrf
                                        <button type="submit" class="btn btn-outline">Update</button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="3">No expense data yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>

                @php
                    $oldExpenseRows = old('expenses');
                    if (!is_array($oldExpenseRows)) {
                        $oldExpenseRows = [];
                    }
                @endphp

                <form method="post" action="{{ route('admin.financial.expense.store') }}" class="setting-form expense-entry-form" data-expense-form>
                    @csrf

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
            </div>

            <form method="get" action="{{ route('admin.financial') }}" class="admin-user-controls">
                <div class="admin-user-level-filter">
                    <label for="financial_type">Report Type</label>
                    <select id="financial_type" name="type">
                        <option value="daily" @selected(($reportType ?? 'daily') === 'daily')>Daily</option>
                        <option value="monthly" @selected(($reportType ?? '') === 'monthly')>Monthly</option>
                        <option value="yearly" @selected(($reportType ?? '') === 'yearly')>Yearly</option>
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
                <div class="admin-service-card-actions">
                    <button type="submit" class="btn">Apply</button>
                </div>
            </form>

            <div class="admin-payment-grid" role="list">
                @foreach (($summaryCards ?? []) as $card)
                    <div class="admin-payment-cell" role="listitem">
                        <span>{{ $card['label'] ?? '-' }}</span>
                        <strong>{{ $card['income_label'] ?? 'Rp 0' }}</strong>
                        <small>{{ (int) ($card['transactions'] ?? 0) }} {{ $card['meta'] ?? 'entry(ies)' }}</small>
                    </div>
                @endforeach
            </div>

            @if (($reportType ?? 'daily') === 'daily')
                <div class="admin-user-table-wrap">
                    <h3>Daily Report - {{ $monthName ?? '-' }} {{ $selectedYear ?? '-' }}</h3>
                    <table class="admin-user-table">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Transactions</th>
                                <th>Income</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse (($dailyRows ?? []) as $row)
                                <tr>
                                    <td>{{ $row['label'] }}</td>
                                    <td>{{ $row['transactions'] }}</td>
                                    <td>{{ $row['income_label'] }}</td>
                                </tr>
                            @empty
                                <tr>
                                <td colspan="3">No approved payment data for selected month.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
                </div>
            @elseif (($reportType ?? '') === 'monthly')
                <div class="admin-user-table-wrap">
                    <h3>Monthly Report - {{ $selectedYear ?? '-' }}</h3>
                    <table class="admin-user-table">
                        <thead>
                            <tr>
                                <th>Month</th>
                                <th>Transactions</th>
                                <th>Income</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse (($monthlyRows ?? []) as $row)
                                <tr>
                                    <td>{{ $row['label'] }}</td>
                                    <td>{{ $row['transactions'] }}</td>
                                    <td>{{ $row['income_label'] }}</td>
                                </tr>
                            @empty
                                <tr>
                                <td colspan="3">No approved payment data for selected year.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
                </div>
            @else
                <div class="admin-user-table-wrap">
                    <h3>Yearly Report</h3>
                    <table class="admin-user-table">
                        <thead>
                            <tr>
                                <th>Year</th>
                                <th>Transactions</th>
                                <th>Income</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse (($yearlyRows ?? []) as $row)
                                <tr>
                                    <td>{{ $row['label'] }}</td>
                                    <td>{{ $row['transactions'] }}</td>
                                    <td>{{ $row['income_label'] }}</td>
                                </tr>
                            @empty
                                <tr>
                                <td colspan="3">No approved payment data yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
                </div>
            @endif
        </div>
    </div>
</section>
</main>
</div>
