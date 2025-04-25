<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use App\Models\Book;
use App\Models\Borrow;

class BookIssuedMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public $book;
    public $borrow;

    /**
     * Create a new message instance.
     *
     * @param Book $book
     * @param Borrow $borrow
     */
    public function __construct(Book $book, Borrow $borrow)
    {
        $this->book = $book;
        $this->borrow = $borrow;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        return $this->subject('Book Issued - Return Date Notification')
            ->view('emails.book_issued')
            ->with([
                'book' => $this->book,
                'borrow' => $this->borrow,
            ]);
    }
}
