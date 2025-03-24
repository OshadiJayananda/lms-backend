@extends('layouts.email')

@section('content')
    <h1>Book Now Available for Renewal</h1>
    <p>Hello,</p>

    <p>We're pleased to inform you that "{{ $book->name }}" is now available for renewal.</p>

    <p><strong>Copies Available:</strong> {{ $book->no_of_copies }}</p>

    @if ($requestedDate)
        <p>You had previously requested to renew until {{ $requestedDate }}.</p>
    @endif

    <p>To renew this book, please visit your account on our library system.</p>

    <p>This notification will expire in 7 days.</p>

    <p>Thank you for using our library!</p>
@endsection
