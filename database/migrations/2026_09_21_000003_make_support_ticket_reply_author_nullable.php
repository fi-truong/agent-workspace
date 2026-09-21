<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('support_ticket_replies', function (Blueprint $table) {
            $table->dropForeign(['author_id']);
            $table->foreignId('author_id')->nullable()->change();
            $table->foreign('author_id')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('support_ticket_replies', function (Blueprint $table) {
            $table->dropForeign(['author_id']);
            $table->foreignId('author_id')->nullable(false)->change();
            $table->foreign('author_id')->references('id')->on('users')->restrictOnDelete();
        });
    }
};
