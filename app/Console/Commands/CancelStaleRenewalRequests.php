<?php

namespace App\Console\Commands;

use App\Models\RenewRequest;
use App\Models\Notification;
use Illuminate\Console\Command;
use Carbon\Carbon;

class CancelStaleRenewalRequests extends Command
{
    protected $signature = 'renewals:cancel-stale';
    protected $description = 'Cancel renewal requests that have been pending user confirmation for more than 2 weeks';

    public function handle()
    {
        $thresholdDate = Carbon::now()->subWeeks(2);

        $staleRequests = RenewRequest::where('status', RenewRequest::STATUS_PENDING_USER_CONFIRMATION)
            ->where('updated_at', '<=', $thresholdDate)
            ->get();

        foreach ($staleRequests as $request) {
            // Update the request status
            $request->update([
                'status' => RenewRequest::STATUS_REJECTED,
                'admin_notes' => 'Automatically canceled due to no response within 2 weeks'
            ]);

            // Notify user
            Notification::create([
                'user_id' => $request->user_id,
                'book_id' => $request->book_id,
                'renew_request_id' => $request->id,
                'title' => 'Renewal Request Expired',
                'message' => "Your renewal request for '{$request->book->name}' has been automatically canceled because you didn't respond within 2 weeks.",
                'type' => Notification::TYPE_RENEWAL_EXPIRED,
                'is_read' => false,
                'metadata' => [
                    'request_id' => $request->id,
                    'book_id' => $request->book_id,
                    'expired_at' => now()
                ]
            ]);

            // Notify admin
            Notification::create([
                'user_id' => 1, // Admin ID
                'book_id' => $request->book_id,
                'renew_request_id' => $request->id,
                'title' => 'Renewal Request Expired',
                'message' => "Renewal request for '{$request->book->name}' by {$request->user->name} has been automatically canceled due to no response within 2 weeks.",
                'type' => Notification::TYPE_RENEWAL_EXPIRED,
                'is_read' => false,
                'metadata' => [
                    'request_id' => $request->id,
                    'user_id' => $request->user_id,
                    'expired_at' => now()
                ]
            ]);
        }

        $this->info("Canceled {$staleRequests->count()} stale renewal requests.");
    }
}
