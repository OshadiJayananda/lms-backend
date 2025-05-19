<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('borrows', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id'); // Foreign key for the user who borrowed the book
            $table->unsignedBigInteger('book_id'); // Foreign key for the borrowed book
            $table->date('issued_date'); // Date when the book was issued
            $table->date('due_date'); // Due date for returning the book
            $table->string('status')->default('Pending'); // Status of the borrow request (e.g., 'Pending', 'Approved', 'Issued', 'Returned', 'Expired', 'Rejected')
            $table->timestamps(); // Created at and updated at timestamps

            // Foreign key constraints
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('book_id')->references('id')->on('books')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('borrows');
    }
};
