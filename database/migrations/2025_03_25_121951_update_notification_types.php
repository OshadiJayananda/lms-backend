<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        DB::statement("ALTER TABLE notifications MODIFY COLUMN type ENUM(
        'reservation_pending',
        'reservation_approved',
        'reservation_rejected',
        'reservation_confirmed',
        'book_available',
        'book_ready_for_pickup',
        'admin_alert'
    )");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
