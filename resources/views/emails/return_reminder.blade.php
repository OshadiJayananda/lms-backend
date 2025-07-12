<!DOCTYPE html>
<html>

<head>
    <title>Return Reminder</title>
</head>

<body>
    <h1>Return Reminder</h1>
    <p>Hello {{ $borrow->user->name }},</p>
    <p>This is a reminder that the book "{{ $book->name }}" is due in 2 days.</p>
    <p>Please return the book by: {{ $borrow->due_date->format('Y-m-d') }}</p>
    <p>Thank you!</p>
</body>

</html>
