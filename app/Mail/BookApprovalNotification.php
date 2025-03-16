<?php

namespace App\Mail;

use App\Models\Book;
use App\Models\Borrow;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class BookApprovalNotification extends Mailable
{
    use Queueable, SerializesModels;

    public $book;
    public $borrow;

    public function __construct(Book $book, Borrow $borrow)
    {
        $this->book = $book;
        $this->borrow = $borrow;
    }

    public function build()
    {
        return $this->subject('Book Request Approved')
            ->view('emails.book_approval')
            ->with([
                'book' => $this->book,
                'borrow' => $this->borrow,
            ]);
    }
}
