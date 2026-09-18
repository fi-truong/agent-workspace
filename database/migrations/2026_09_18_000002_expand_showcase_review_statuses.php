<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE showcase_posts MODIFY status ENUM('draft', 'pending', 'published', 'rejected') NOT NULL DEFAULT 'draft'");

            return;
        }

        Schema::table('showcase_posts', function (Blueprint $table) {
            $table->string('status', 20)->default('draft')->change();
        });
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("UPDATE showcase_posts SET status = 'draft' WHERE status IN ('pending', 'rejected')");
            DB::statement("ALTER TABLE showcase_posts MODIFY status ENUM('draft', 'published') NOT NULL DEFAULT 'draft'");

            return;
        }

        Schema::table('showcase_posts', function (Blueprint $table) {
            $table->string('status', 20)->default('draft')->change();
        });
    }
};
