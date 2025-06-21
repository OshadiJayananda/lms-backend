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
            padding: 3px;
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
        <div class="summary-item"><strong>Total Members:</strong> {{ $members->count() }}</div><br />
        <div class="summary-item"><strong>Active Members:</strong> {{ $members->where('status', 'Active')->count() }}
        </div><br />
        <div class="summary-item"><strong>Blocked Members:</strong> {{ $members->where('status', 'Blocked')->count() }}
        </div><br />
        <div class="summary-item"><strong>Avg. Books Borrowed:</strong>
            {{ number_format($members->avg('borrowed_books_count'), 2) }}</div><br />
        <div class="summary-item"><strong>Avg. Books Returned:</strong>
            {{ number_format($members->avg('returned_books_count'), 2) }}</div><br />
    </div>

    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Name</th>
                <th>Email</th>
                <th>Phone</th>
                <th>Total Borrowed</th>
                <th>Total Returned</th>
                <th>Status</th>
                <th>Member Since</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($members as $member)
                <tr>
                    <td>{{ $member->id }}</td>
                    <td>{{ $member->name }}</td>
                    <td>{{ $member->email }}</td>
                    <td>{{ $member->contact ?? 'N/A' }}</td>
                    <td>{{ $member->borrowed_books_count }}</td>
                    <td>{{ $member->returned_books_count }}</td>
                    <td>{{ $member->status }}</td>
                    <td>{{ $member->created_at->format('Y-m-d') }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="footer">
        Library Management System - &copy; {{ date('Y') }}
    </div>
</body>

</html>
