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
        Schema::table('borrows', function (Blueprint $table) {
            $table->boolean('fine_paid')->default(false)->after('status');

            // Optional: Add index if you'll be querying this field frequently
            $table->index('fine_paid');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('borrows', function (Blueprint $table) {
            $table->dropColumn('fine_paid');

            // Drop the index if you created one
            $table->dropIndex(['fine_paid']);
        });
    }
};
