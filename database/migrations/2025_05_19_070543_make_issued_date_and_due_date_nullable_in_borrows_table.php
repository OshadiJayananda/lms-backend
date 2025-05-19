<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('borrows', function (Blueprint $table) {
            $table->date('issued_date')->nullable()->change();
            $table->date('due_date')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('borrows', function (Blueprint $table) {
            $table->date('issued_date')->nullable(false)->change();
            $table->date('due_date')->nullable(false)->change();
        });
    }
};
