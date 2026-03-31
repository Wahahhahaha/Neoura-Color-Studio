<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Financial Report Print</title>
    <style>
        @page {
            size: A4;
            margin: 14mm;
        }
        body {
            font-family: "Segoe UI", Arial, sans-serif;
            color: #111827;
            margin: 0;
            font-size: 12px;
            line-height: 1.45;
        }
        .sheet {
            border: 1px solid #e5e7eb;
        }
        .report-head {
            display: block;
            background: #f8fafc;
            border-bottom: 1px solid #e5e7eb;
            padding: 12px 14px;
        }
        .report-logo {
            display: flex;
            align-items: center;
            justify-content: flex-start;
            min-height: 40px;
            margin-bottom: 8px;
        }
        .report-logo img {
            max-height: 52px;
            max-width: 240px;
            width: auto;
            object-fit: contain;
        }
        .report-title h1 {
            margin: 0;
            font-size: 18px;
            line-height: 1.2;
        }
        .report-title p {
            margin: 3px 0 0;
            color: #4b5563;
            font-size: 12px;
        }
        .report-meta {
            padding: 10px 14px 4px;
        }
        .report-meta table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0 6px;
        }
        .report-meta td:first-child {
            width: 130px;
            color: #4b5563;
        }
        .summary {
            margin: 8px 14px 0;
            border: 1px solid #d1d5db;
            border-collapse: collapse;
            width: calc(100% - 28px);
        }
        .summary th,
        .summary td {
            border: 1px solid #d1d5db;
            padding: 8px 10px;
        }
        .summary th {
            text-align: left;
            background: #eef2ff;
            font-weight: 700;
        }
        .summary td:last-child {
            text-align: right;
        }
        .summary tr.description-row td:last-child {
            text-align: left;
        }
        .summary .highlight td {
            background: #f8fafc;
            font-weight: 700;
        }
        .detail-wrap {
            padding: 8px 14px 0;
        }
        .detail-title {
            margin: 0 0 6px;
            font-size: 13px;
            font-weight: 700;
        }
        .detail {
            width: 100%;
            border-collapse: collapse;
            border: 1px solid #d1d5db;
        }
        .detail th,
        .detail td {
            border: 1px solid #d1d5db;
            padding: 8px 10px;
        }
        .detail th {
            text-align: left;
            background: #f8fafc;
            font-weight: 700;
        }
        .detail td:nth-child(1) {
            text-align: center;
            width: 50px;
        }
        .detail td:nth-child(3),
        .detail th:nth-child(3) {
            text-align: right;
            white-space: nowrap;
        }
        .detail tfoot td {
            font-weight: 700;
            background: #edf7ed;
        }
        .footnote {
            margin: 8px 14px 14px;
            font-size: 11px;
            color: #6b7280;
        }
    </style>
</head>
<body onload="window.print()">
    <div class="sheet">
        <div class="report-head">
            <div class="report-logo">
                @if (!empty($website['logo_url']))
                    <img src="{{ $website['logo_url'] }}" alt="{{ $website['name'] ?? 'Website Logo' }}">
                @endif
            </div>
            <div class="report-title">
                <h1>{{ $website['name'] ?? 'Website' }}</h1>
                <p>Financial Statement</p>
            </div>
        </div>

        <div class="report-meta">
            <table>
                <tr>
                    <td>Report Type</td>
                    <td>: {{ $typeLabel ?? ucfirst((string) ($snapshot['type'] ?? '-')) }}</td>
                </tr>
                <tr>
                    <td>Period</td>
                    <td>: {{ $snapshot['period_label'] ?? '-' }}</td>
                </tr>
                <tr>
                    <td>Generated At</td>
                    <td>: {{ $generatedAt ?? date('d M Y H:i') }}</td>
                </tr>
            </table>
        </div>

        <table class="summary">
            <thead>
                <tr>
                    <th>Executive Summary</th>
                    <th>Amount</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>Total Income</td>
                    <td>{{ $snapshot['income_label'] ?? 'Rp 0' }}</td>
                </tr>
                <tr class="description-row">
                    <td>Income Description</td>
                    <td>{{ $snapshot['income_description'] ?? '-' }}</td>
                </tr>
                <tr>
                    <td>Total Outcome</td>
                    <td>{{ $snapshot['outcome_label'] ?? 'Rp 0' }}</td>
                </tr>
                <tr class="description-row">
                    <td>Outcome Description</td>
                    <td>{{ $snapshot['outcome_description'] ?? '-' }}</td>
                </tr>
                <tr class="highlight">
                    <td>Result</td>
                    <td>{{ $snapshot['net_label'] ?? 'Rp 0' }}</td>
                </tr>
            </tbody>
        </table>

        <div class="detail-wrap">
            <p class="detail-title">Outcome Detail</p>
            <table class="detail">
                <thead>
                    <tr>
                        <th>No</th>
                        <th>Expense Category</th>
                        <th>Amount (Rp)</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse (($snapshot['outcome_detail_rows'] ?? []) as $rowIndex => $row)
                        <tr>
                            <td>{{ $rowIndex + 1 }}</td>
                            <td>{{ $row['label'] ?? '-' }}</td>
                            <td>{{ $row['value_label'] ?? 'Rp 0' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td>1</td>
                            <td colspan="2">No outcome detail available for this period.</td>
                        </tr>
                    @endforelse
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="2">TOTAL OUTCOME</td>
                        <td>{{ $snapshot['outcome_label'] ?? 'Rp 0' }}</td>
                    </tr>
                </tfoot>
            </table>
        </div>
        <p class="footnote">This document is system-generated and all values are presented in Indonesian Rupiah (IDR).</p>
    </div>
</body>
</html>
