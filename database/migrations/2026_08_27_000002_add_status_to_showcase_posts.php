<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('showcase_posts', function (Blueprint $table) {
            $table->enum('status', ['draft', 'published'])->default('draft')->after('badge');
            $table->timestamp('published_at')->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('showcase_posts', function (Blueprint $table) {
            $table->dropColumn(['status', 'published_at']);
        });
    }
};
