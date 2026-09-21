<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // The field was introduced after users had already signed in. Database
        // sessions retain the most recent activity for each active session, which
        // is the closest reliable historical value available for a one-time fill.
        DB::table('sessions')
            ->select('user_id', DB::raw('MAX(last_activity) as last_activity'))
            ->whereNotNull('user_id')
            ->groupBy('user_id')
            ->orderBy('user_id')
            ->each(function (object $session): void {
                DB::table('users')
                    ->where('id', $session->user_id)
                    ->whereNull('last_login_at')
                    ->update([
                        'last_login_at' => Carbon::createFromTimestamp((int) $session->last_activity),
                    ]);
            });
    }

    public function down(): void
    {
        // Backfilled values cannot be distinguished safely from real sign-ins.
    }
};
