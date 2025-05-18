<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('borrowing_policies', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('borrow_limit')->default(5);
            $table->unsignedInteger('borrow_duration_days')->default(14);
            $table->decimal('fine_per_day', 8, 2)->default(50.00);
            $table->timestamps();
        });

        // Add initial record
        DB::table('borrowing_policies')->insert([
            'borrow_limit' => 5,
            'borrow_duration_days' => 14,
            'fine_per_day' => 50.00,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down()
    {
        Schema::dropIfExists('borrowing_policies');
    }
};
