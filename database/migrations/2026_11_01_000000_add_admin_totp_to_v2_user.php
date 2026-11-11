<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('v2_user', function (Blueprint $table) {
            $table->text('totp_secret')->nullable();
            $table->boolean('totp_enabled')->default(false);
            $table->unsignedBigInteger('totp_last_used_counter')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('v2_user', function (Blueprint $table) {
            $table->dropColumn(['totp_secret', 'totp_enabled', 'totp_last_used_counter']);
        });
    }
};
