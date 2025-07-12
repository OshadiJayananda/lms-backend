<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Borrow;
use App\Models\Book;
use App\Models\User;
use App\Mail\ReturnReminderMail;
use Illuminate\Support\Facades\Mail;
use Carbon\Carbon;

class SendReturnReminders extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'reminders:send';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send return reminders to users 2 days before the due date';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        // Find borrows that are due in 2 days
        $borrows = Borrow::where('status', 'Issued')
            ->whereDate('due_date', Carbon::now()->addDays(2)->toDateString())
            ->get();

        foreach ($borrows as $borrow) {
            $user = User::find($borrow->user_id);
            $book = Book::find($borrow->book_id);

            // Send reminder email
            Mail::to($user->email)->send(new ReturnReminderMail($book, $borrow));

            $this->info("Reminder sent to {$user->email} for book '{$book->name}'.");
        }

        $this->info('All reminders sent successfully.');
    }
}
