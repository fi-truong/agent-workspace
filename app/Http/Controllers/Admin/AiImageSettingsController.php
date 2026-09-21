<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminAuditLog;
use App\Models\AppSetting;
use App\Services\ImageGenerationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AiImageSettingsController extends Controller
{
    public function index(): View
    {
        return view('admin.ai-image.index', [
            'enabled' => AppSetting::boolean('ai_plus_image_generation_enabled'),
            'models' => ImageGenerationService::modelOptions(),
            'flareEnabled' => AppSetting::boolean('ai_plus_image_flare_enabled', true),
            'sunburstEnabled' => AppSetting::boolean('ai_plus_image_sunburst_enabled', true),
            'defaultModel' => AppSetting::query()->where('key', 'ai_plus_image_default_model')->value('value') ?: ImageGenerationService::MODEL_FLARE,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'default_model' => ['required', 'in:'.implode(',', array_keys(ImageGenerationService::modelOptions()))],
        ]);
        $enabled = $request->boolean('enabled');
        $flareEnabled = $request->boolean('flare_enabled');
        $sunburstEnabled = $request->boolean('sunburst_enabled');
        if (! $flareEnabled && ! $sunburstEnabled) {
            return back()->withErrors(['models' => 'Enable at least one image model.'])->withInput();
        }
        if (($data['default_model'] === ImageGenerationService::MODEL_FLARE && ! $flareEnabled)
            || ($data['default_model'] === ImageGenerationService::MODEL_SUNBURST && ! $sunburstEnabled)) {
            return back()->withErrors(['default_model' => 'The default model must be enabled.'])->withInput();
        }
        $setting = AppSetting::updateOrCreate(
            ['key' => 'ai_plus_image_generation_enabled'],
            ['value' => $enabled ? 'true' : 'false'],
        );
        AdminAuditLog::record('ai_image_generation.visibility_updated', $setting, ['enabled' => $enabled]);
        AppSetting::updateOrCreate(['key' => 'ai_plus_image_flare_enabled'], ['value' => $flareEnabled ? 'true' : 'false']);
        AppSetting::updateOrCreate(['key' => 'ai_plus_image_sunburst_enabled'], ['value' => $sunburstEnabled ? 'true' : 'false']);
        AppSetting::updateOrCreate(['key' => 'ai_plus_image_default_model'], ['value' => $data['default_model']]);

        return to_route('admin.ai-image.index')->with('success', $enabled
            ? 'Image Studio is now available in Agent Workspace.'
            : 'Image Studio is now hidden from Agent Workspace.');
    }
}
