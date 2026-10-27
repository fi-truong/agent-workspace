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
        Schema::table('conversations', function (Blueprint $table) {
            $table->string('chat_model', 80)->nullable();
        });
        Schema::table('usage_logs', function (Blueprint $table) {
            $table->unsignedBigInteger('quota_units')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('usage_logs', function (Blueprint $table) {
            $table->dropColumn('quota_units');
        });
        Schema::table('conversations', function (Blueprint $table) {
            $table->dropColumn('chat_model');
        });
    }
};
