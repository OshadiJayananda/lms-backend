<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use App\Models\Book;
use App\Models\Borrow;

class ReturnReminderMail extends Mailable implements ShouldQueue
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
        return $this->subject('Return Reminder - Book Due Soon')
            ->view('emails.return_reminder')
            ->with([
                'book' => $this->book,
                'borrow' => $this->borrow,
            ]);
    }
}
