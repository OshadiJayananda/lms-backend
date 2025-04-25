<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class RenewalApprovedMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public $renewRequest;
    public $newDueDate;

    public function __construct($renewRequest, $newDueDate)
    {
        $this->renewRequest = $renewRequest;
        $this->newDueDate = $newDueDate;
    }

    public function build()
    {
        return $this->subject('Your Book Renewal Has Been Approved')
            ->view('emails.renewal_approved');
    }
}
