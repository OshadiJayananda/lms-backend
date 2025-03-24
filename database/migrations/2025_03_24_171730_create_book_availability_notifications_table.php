<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('book_availability_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('book_id')->constrained()->onDelete('cascade');
            $table->date('requested_date');
            $table->boolean('notified')->default(false);
            $table->timestamp('notification_sent_at')->nullable();
            $table->timestamps();

            // Composite index for frequent queries
            $table->index(['book_id', 'notified']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('book_availability_notifications');
    }
};
