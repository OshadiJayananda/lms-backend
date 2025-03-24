<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class BookAvailableNotification extends Mailable
{
    use Queueable, SerializesModels;

    public $book;
    public $requestedDate;

    public function __construct($book, $requestedDate)
    {
        $this->book = $book;
        $this->requestedDate = $requestedDate;
    }

    public function build()
    {
        return $this->subject('Book Now Available for Renewal')
            ->view('emails.book_available');
    }
}
