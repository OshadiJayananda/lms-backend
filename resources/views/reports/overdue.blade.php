<!DOCTYPE html>
<html>

<head>
    <title>{{ $title }}</title>
    <style>
        body {
            font-family: Arial, sans-serif;
        }

        .header {
            text-align: center;
            margin-bottom: 20px;
        }

        .header h1 {
            margin: 0;
        }

        .header p {
            margin: 0;
            color: #666;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }

        th,
        td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
        }

        th {
            background-color: #f2f2f2;
            position: sticky;
            top: 0;
        }

        .footer {
            margin-top: 20px;
            text-align: right;
            font-size: 12px;
            color: #666;
        }

        .logo {
            text-align: center;
            margin-bottom: 10px;
        }

        .summary {
            margin-bottom: 20px;
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
        }

        .summary-item {
            background-color: #f8f9fa;
            padding: 10px 15px;
            border-radius: 5px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .highlight {
            background-color: #fff3cd;
        }

        .critical {
            background-color: #f8d7da;
        }

        .days-overdue {
            font-weight: bold;
        }

        .days-1-7 {
            color: #ffc107;
        }

        .days-8-14 {
            color: #fd7e14;
        }

        .days-15-plus {
            color: #dc3545;
        }

        .fine-paid {
            color: #28a745;
            font-weight: bold;
        }

        .fine-unpaid {
            color: #dc3545;
            font-weight: bold;
        }

        .status-badge {
            padding: 3px 8px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: bold;
            display: inline-block;
        }

        .status-issued {
            background-color: #d1ecf1;
            color: #0c5460;
        }

        .status-overdue {
            background-color: #f8d7da;
            color: #721c24;
        }

        .status-confirmed {
            background-color: #fff3cd;
            color: #856404;
        }

        .status-returned {
            background-color: #d4edda;
            color: #155724;
        }
    </style>
</head>

<body>
    <div class="logo">
        <h2>Library Management System</h2>
    </div>

    <div class="header">
        <h1>{{ $title }}</h1>
        <p>Generated on: {{ $generatedAt }}</p>
        @if ($fromDate || $toDate)
            <p>
                @if ($fromDate && $toDate)
                    Date Range: {{ $fromDate }} to {{ $toDate }}
                @elseif($fromDate)
                    From: {{ $fromDate }}
                @elseif($toDate)
                    Until: {{ $toDate }}
                @endif
            </p>
        @endif
    </div>

    <div class="summary">
        <div class="summary-item"><strong>Total Overdue:</strong> {{ $overdueBooks->count() }}</div>
        <div class="summary-item"><strong>Max Days Overdue:</strong> {{ $overdueBooks->max('days_overdue') ?? 0 }}</div>
        <div class="summary-item"><strong>Avg Days Overdue:</strong>
            {{ number_format($overdueBooks->avg('days_overdue'), 1) }}</div>
        <div class="summary-item"><strong>Total Potential Fines:</strong> Rs.
            {{ number_format($overdueBooks->sum('calculated_fine'), 2) }}</div>
        <div class="summary-item"><strong>Total Paid Fines:</strong> Rs.
            {{ number_format($overdueBooks->sum('paid_fine_amount'), 2) }}</div>
        <div class="summary-item"><strong>Balance Due:</strong> Rs.
            {{ number_format($overdueBooks->sum('calculated_fine') - $overdueBooks->sum('paid_fine_amount'), 2) }}
        </div>
    </div>

    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Book</th>
                <th>Member</th>
                <th>Issued Date</th>
                <th>Due Date</th>
                <th>Status</th>
                <th>Days Overdue</th>
                <th>Potential Fine</th>
                <th>Paid Amount</th>
                <th>Balance</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($overdueBooks as $borrowing)
                @php
                    $daysClass = '';
                    if ($borrowing->days_overdue >= 15) {
                        $daysClass = 'days-15-plus';
                    } elseif ($borrowing->days_overdue >= 8) {
                        $daysClass = 'days-8-14';
                    } else {
                        $daysClass = 'days-1-7';
                    }

                    $balance = $borrowing->calculated_fine - $borrowing->paid_fine_amount;
                @endphp
                <tr
                    class="{{ $borrowing->days_overdue > 14 ? 'critical' : ($borrowing->days_overdue > 7 ? 'highlight' : '') }}">
                    <td>{{ $borrowing->id }}</td>
                    <td>{{ $borrowing->book->name }}</td>
                    <td>{{ $borrowing->user->name }} (ID: {{ $borrowing->user->id }})</td>
                    <td>{{ $borrowing->issued_date->format('Y-m-d') }}</td>
                    <td>{{ $borrowing->due_date->format('Y-m-d') }}</td>
                    <td>
                        <span class="status-badge status-{{ strtolower($borrowing->status) }}">
                            {{ $borrowing->status }}
                        </span>
                    </td>
                    <td class="days-overdue {{ $daysClass }}">{{ $borrowing->days_overdue }}</td>
                    <td class="fine-unpaid">Rs. {{ number_format($borrowing->calculated_fine, 2) }}</td>
                    <td class="fine-paid">Rs. {{ number_format($borrowing->paid_fine_amount, 2) }}</td>
                    <td class="{{ $balance > 0 ? 'fine-unpaid' : 'fine-paid' }}">
                        Rs. {{ number_format($balance, 2) }}
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="footer">
        Library Management System - &copy; {{ date('Y') }}
    </div>
</body>

</html>
