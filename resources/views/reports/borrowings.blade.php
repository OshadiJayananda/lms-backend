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
        <div class="summary-item"><strong>Returned But Not Given:</strong>
            {{ $borrowings->where('status', 'Returned')->count() }}
        </div><br />
        <div class="summary-item"><strong>Returned And Given:</strong>
            {{ $borrowings->where('status', 'Confirmed')->count() }}
        </div><br />
    </div>

    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Book</th>
                <th>Member</th>
                <th>Issued Date</th>
                <th>Due Date</th>
                <th>Return Date</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($borrowings as $borrowing)
                <tr>
                    <td>{{ $borrowing->id }}</td>
                    <td>{{ $borrowing->book->name }}</td>
                    <td>{{ $borrowing->user->name }}</td>
                    <td>{{ $borrowing->issued_date->format('Y-m-d') }}</td>
                    <td>{{ $borrowing->due_date->format('Y-m-d') }}</td>
                    <td>{{ $borrowing->returned_date ? $borrowing->returned_date->format('Y-m-d') : 'N/A' }}</td>
                    <td class="status-{{ strtolower($borrowing->status) }}">{{ $borrowing->status }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="footer">
        Library Management System - &copy; {{ date('Y') }}
    </div>
</body>

</html>
