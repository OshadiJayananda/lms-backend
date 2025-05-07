<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('renew_requests', function (Blueprint $table) {
            if (!Schema::hasColumn('renew_requests', 'processed_at')) {
                $table->timestamp('processed_at')->nullable()->after('status');
            }

            if (!Schema::hasColumn('renew_requests', 'admin_proposed_date')) {
                $table->timestamp('admin_proposed_date')->nullable()->after('requested_date');
            }
        });
    }

    public function down()
    {
        Schema::table('renew_requests', function (Blueprint $table) {
            $table->dropColumn(['processed_at', 'admin_proposed_date']);
        });
    }
};
