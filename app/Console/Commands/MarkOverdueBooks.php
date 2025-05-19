<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Http\Controllers\BorrowController;
use Illuminate\Support\Facades\Log;

class MarkOverdueBooks extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'books:mark-overdue';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Mark issued books as overdue when past due date';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        try {
            $controller = app(BorrowController::class);
            $result = $controller->markAsOverdue();

            $this->info('Successfully processed overdue books.');
            $this->line('Books marked as overdue: ' . $result['count']);

            if ($result['count'] > 0) {
                Log::info('Marked ' . $result['count'] . ' books as overdue');
            }

            return 0; // Success exit code

        } catch (\Exception $e) {
            $this->error('Error processing overdue books: ' . $e->getMessage());
            Log::error('Overdue books check failed: ' . $e->getMessage());
            return 1; // Error exit code
        }
    }
}
