<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('messages', function (Blueprint $table): void {
            $table->longText('content')->change();
        });

        Schema::table('agents', function (Blueprint $table): void {
            $table->longText('description')->nullable()->change();
            $table->longText('system_prompt')->nullable()->change();
            $table->longText('knowledge')->nullable()->change();
        });

        Schema::table('email_drafts', function (Blueprint $table): void {
            $table->longText('subject')->change();
        });
    }

    public function down(): void
    {
        // Keep widened columns: a rollback must not truncate content saved after this migration.
    }
};
