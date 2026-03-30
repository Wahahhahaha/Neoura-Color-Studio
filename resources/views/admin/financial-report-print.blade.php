<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Financial Report Print</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            color: #222;
            margin: 24px;
        }
        .head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            margin-bottom: 16px;
        }
        .logo img {
            max-height: 56px;
            width: auto;
        }
        h1 {
            margin: 0;
            font-size: 24px;
        }
        p {
            margin: 2px 0;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 12px;
        }
        th,
        td {
            border: 1px solid #d0d0d0;
            padding: 8px;
            text-align: left;
        }
        th:last-child,
        td:last-child {
            text-align: right;
        }
    </style>
</head>
<body onload="window.print()">
    <div class="head">
        <div class="logo">
            @if (!empty($website['logo_url']))
                <img src="{{ $website['logo_url'] }}" alt="{{ $website['name'] ?? 'Website Logo' }}">
            @endif
        </div>
        <div>
            <h1>{{ $website['name'] ?? 'Website' }}</h1>
            <p>Financial Report</p>
            <p>Type: {{ ucfirst((string) ($snapshot['type'] ?? '-')) }}</p>
            <p>Period: {{ $snapshot['period_label'] ?? '-' }}</p>
        </div>
    </div>

    <table>
        <tbody>
            <tr>
                <td>Income</td>
                <td>{{ $snapshot['income_label'] ?? 'Rp 0' }}</td>
            </tr>
            <tr>
                <td>Outcome</td>
                <td>{{ $snapshot['outcome_label'] ?? 'Rp 0' }}</td>
            </tr>
            <tr>
                <td>Net</td>
                <td>{{ $snapshot['net_label'] ?? 'Rp 0' }}</td>
            </tr>
        </tbody>
    </table>

    <table>
        <thead>
            <tr>
                <th>Outcome Item</th>
                <th>Cost</th>
            </tr>
        </thead>
        <tbody>
            @forelse (($snapshot['outcome_detail_rows'] ?? []) as $row)
                <tr>
                    <td>{{ $row['label'] ?? '-' }}</td>
                    <td>{{ $row['value_label'] ?? 'Rp 0' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="2">No outcome detail.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
