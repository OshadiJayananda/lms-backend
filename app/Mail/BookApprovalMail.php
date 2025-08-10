<?php

namespace App\Mail;

use App\Models\Book;
use App\Models\Borrow;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class BookApprovalMail extends Mailable implements ShouldQueue
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
        return $this->subject('Book Request Approved - ' . $this->book->name)
            ->view('emails.book_approval')
            ->with([
                'book' => $this->book,
                'borrow' => $this->borrow,
            ]);
    }
}
