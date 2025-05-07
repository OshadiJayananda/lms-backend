<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class UpdateNotificationsTableForRenewals extends Migration
{
    public function up()
    {
        Schema::table('notifications', function (Blueprint $table) {
            // Increase type column length
            $table->string('type', 50)->change();

            // Add new columns
            $table->unsignedBigInteger('renew_request_id')->nullable()->after('reservation_id');
            $table->json('metadata')->nullable()->after('type');

            // Add foreign key
            $table->foreign('renew_request_id')->references('id')->on('renew_requests')->onDelete('cascade');
        });
    }

    public function down()
    {
        Schema::table('notifications', function (Blueprint $table) {
            $table->dropForeign(['renew_request_id']);
            $table->dropColumn(['renew_request_id', 'metadata']);
            $table->string('type', 20)->change(); // Revert to original length if needed
        });
    }
}
