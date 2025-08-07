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
        }

        .summary-item {
            display: inline-block;
            margin-right: 20px;
        }

        .status-issued {
            color: #3b82f6;
        }

        .status-returned {
            color: #10b981;
        }

        .status-overdue {
            color: #ef4444;
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
    </div>

    <div class="summary">
        <div class="summary-item"><strong>Total Borrowings:</strong> {{ $borrowings->count() }}</div><br />
        <div class="summary-item"><strong>Issued:</strong> {{ $borrowings->where('status', 'Issued')->count() }}</div>
        <br />
        <div class="summary-item"><strong>Unconfirmed Returns:</strong>
            {{ $borrowings->where('status', 'Returned')->count() }}
        </div><br />
        <div class="summary-item"><strong>Confirmed Returns:</strong>
            {{ $borrowings->where('status', 'Confirmed')->count() }}
        </div><br />
    </div>

    <table>
        <thead>
            <tr>
                <th>No</th>
                <th>Book (Borrow ID)</th>
                <th>Member</th>
                <th>Issued Date</th>
                <th>Due Date</th>
                <th>Return Date</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($borrowings as $borrowing)
                <tr>
                    <td>{{ $loop->iteration }}</td>
                    <td>{{ $borrowing->book->name }} ({{ $borrowing->id }})</td>
                    <td>{{ $borrowing->user->name }}</td>
                    <td>{{ optional($borrowing->issued_date)->format('Y-m-d') ?? 'N/A' }}</td>
                    <td>{{ optional($borrowing->due_date)->format('Y-m-d') ?? 'N/A' }}</td>
                    <td>{{ optional($borrowing->returned_date)->format('Y-m-d') ?? 'N/A' }}</td>
                    <td class="status-{{ strtolower($borrowing->status) }}">{{ $borrowing->status }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="7" style="text-align: center; color: #999;">
                        No borrowings found for the selected date range.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <div class="footer">
        Library Management System - &copy; {{ date('Y') }}
    </div>
</body>

</html>
