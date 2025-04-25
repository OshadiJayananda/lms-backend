<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class RenewalRejectedMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public $renewRequest;

    public function __construct($renewRequest)
    {
        $this->renewRequest = $renewRequest;
    }

    public function build()
    {
        return $this->subject('Your Book Renewal Request Was Not Approved')
            ->view('emails.renewal_rejected');
    }
}
