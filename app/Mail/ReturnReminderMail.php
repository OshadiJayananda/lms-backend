<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use App\Models\Book;
use App\Models\Borrow;

class ReturnReminderMail extends Mailable
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
        return $this->subject('Return Reminder - Book Due Soon')
            ->view('emails.return_reminder')
            ->with([
                'book' => $this->book,
                'borrow' => $this->borrow,
            ]);
    }
}
