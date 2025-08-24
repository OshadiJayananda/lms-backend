<!DOCTYPE html>
<html>

<head>
    <title>Book Request Approved</title>
</head>

<body>
    <h1>Book Request Approved</h1>
    <p>Dear {{ $borrow->user->name }},</p>
    <p>Your request for the book <strong>{{ $book->name }}</strong> has been approved.</p>
    <p>Please visit the library to pick up your book. The pickup availability time is between 9 AM to 5 PM.</p>
    <p>Book Details:</p>
    <ul>
        <li>Title: {{ $book->name }}</li>
        <li>Author: {{ $book->author->name }}</li>
        <li>ISBN: {{ $book->isbn }}</li>
    </ul>
    <p>Thank you!</p>
</body>

</html>
