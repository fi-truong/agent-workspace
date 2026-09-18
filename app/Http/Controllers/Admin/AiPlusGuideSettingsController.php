<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminAuditLog;
use App\Models\AppSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AiPlusGuideSettingsController extends Controller
{
    public function index(): View
    {
        return view('admin.ai-plus-guide.index', [
            'enabled' => AppSetting::boolean('ai_plus_homepage_guide_enabled'),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $request->validate(['enabled' => ['nullable', 'boolean']]);

        $enabled = $request->boolean('enabled');
        $setting = AppSetting::updateOrCreate(
            ['key' => 'ai_plus_homepage_guide_enabled'],
            ['value' => $enabled ? 'true' : 'false'],
        );

        AdminAuditLog::record('ai_plus_guide.visibility_updated', $setting, ['enabled' => $enabled]);

        return redirect()
            ->route('admin.ai-plus-guide.index')
            ->with('success', $enabled
                ? 'AI Plus Guide is now visible on the AI+ homepage.'
                : 'AI Plus Guide is now hidden from the AI+ homepage.');
    }
}
