@extends('layouts.email')

@section('content')
    <h1>Book Renewal Request Not Approved</h1>
    <p>Hello {{ $renewRequest->user->name }},</p>

    <p>We regret to inform you that your request to renew "{{ $renewRequest->book->name }}" has not been approved.</p>

    <p><strong>Reason:</strong> The book is in high demand and we need to make it available for other users.</p>

    <p>Please return the book by the original due date: {{ $renewRequest->current_due_date }}</p>

    <p>If you have any questions, please contact the library.</p>

    <p>Thank you for your understanding.</p>
@endsection
