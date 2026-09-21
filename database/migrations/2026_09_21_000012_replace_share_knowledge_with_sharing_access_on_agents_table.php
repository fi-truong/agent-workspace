<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('agents', function (Blueprint $table) {
            $table->string('sharing_access', 20)->default('use_only')->after('is_shared');
        });

        DB::table('agents')->where('share_knowledge', true)->update(['sharing_access' => 'copy']);

        Schema::table('agents', function (Blueprint $table) {
            $table->dropColumn('share_knowledge');
        });
    }

    public function down(): void
    {
        Schema::table('agents', function (Blueprint $table) {
            $table->boolean('share_knowledge')->default(false)->after('is_shared');
        });

        DB::table('agents')->where('sharing_access', 'copy')->update(['share_knowledge' => true]);

        Schema::table('agents', function (Blueprint $table) {
            $table->dropColumn('sharing_access');
        });
    }
};
