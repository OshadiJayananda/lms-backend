@extends('layouts.email')

@section('content')
    <h1>Your Book Renewal Has Been Approved</h1>
    <p>Hello {{ $renewRequest->user->name }},</p>

    <p>Your request to renew "{{ $renewRequest->book->name }}" has been approved.</p>

    <p><strong>New Due Date:</strong> {{ $newDueDate }}</p>

    <p>Thank you for using our library!</p>
@endsection
