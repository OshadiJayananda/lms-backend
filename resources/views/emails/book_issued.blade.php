<!DOCTYPE html>
<html>

<head>
    <title>Book Issued</title>
</head>

<body>
    <h1>Book Issued</h1>
    <p>Hello {{ $borrow->user->name }},</p>
    <p>The book "{{ $book->name }}" has been issued to you.</p>
    <p>Please return the book by: {{ $borrow->due_date->format('Y-m-d') }}</p>
    <p>Thank you!</p>
</body>

</html>
