<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminAuditLog;
use App\Models\AiSafetyEvent;
use App\Models\AppSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class WorkUseMonitoringController extends Controller
{
    public function index(): View
    {
        $events = AiSafetyEvent::query()->with('user:id,name,email')->latest()->paginate(25);
        $recent = AiSafetyEvent::query()->where('created_at', '>=', now()->subDays(30));

        return view('admin.work-use.index', [
            'enabled' => AppSetting::boolean('ai_plus_work_use_monitoring_enabled', true),
            'events' => $events,
            'metrics' => [
                'total' => (clone $recent)->count(),
                'ambiguous' => (clone $recent)->where('classification', 'ambiguous')->count(),
                'personal' => (clone $recent)->where('classification', 'personal_or_unrelated')->count(),
                'safety_flagged' => (clone $recent)->where('moderation_flagged', true)->count(),
            ],
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $enabled = $request->boolean('enabled');
        $setting = AppSetting::updateOrCreate(
            ['key' => 'ai_plus_work_use_monitoring_enabled'],
            ['value' => $enabled ? 'true' : 'false'],
        );
        AdminAuditLog::record('ai_work_use_monitoring.updated', $setting, ['enabled' => $enabled]);

        return to_route('admin.work-use.index')->with('success', $enabled
            ? 'Work-use monitoring is enabled. Only requests that need review are retained for up to 180 days.'
            : 'Work-use monitoring is disabled.');
    }
}
