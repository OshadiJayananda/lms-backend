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
        <div class="summary-item"><strong>Total Books:</strong> {{ $books->count() }}</div>
        <div class="summary-item"><strong>Available Books:</strong> {{ $books->where('status', 'Available')->count() }}
        </div>
        <div class="summary-item"><strong>Borrowed Books:</strong> {{ $books->where('status', 'Borrowed')->count() }}
        </div>
    </div>

    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Title</th>
                <th>Author</th>
                <th>ISBN</th>
                <th>Category</th>
                <th>Total Borrows</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($books as $book)
                <tr>
                    <td>{{ $book->id }}</td>
                    <td>{{ $book->name }}</td>
                    <td>{{ $book->author }}</td>
                    <td>{{ $book->isbn }}</td>
                    <td>{{ $book->category->name ?? 'N/A' }}</td>
                    <td>{{ $book->borrows_count }}</td>
                    <td>{{ $book->status }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="footer">
        Library Management System - &copy; {{ date('Y') }}
    </div>
</body>

</html>
