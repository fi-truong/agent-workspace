<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminAuditLog;
use App\Models\AppSetting;
use App\Models\SchoolKnowledgeSource;
use App\Services\SchoolKnowledgeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SchoolKnowledgeController extends Controller
{
    public function index(): View
    {
        return view('admin.school-knowledge.index', [
            'enabled' => AppSetting::boolean('school_knowledge_enabled', true),
            'sources' => SchoolKnowledgeSource::query()
                ->with('creator:id,name')
                ->withCount('chunks')
                ->latest('updated_at')
                ->get(),
            'allowedExtensions' => SchoolKnowledgeService::UPLOAD_EXTENSIONS,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $enabled = $request->boolean('enabled');
        $setting = AppSetting::updateOrCreate(
            ['key' => 'school_knowledge_enabled'],
            ['value' => $enabled ? 'true' : 'false'],
        );
        AdminAuditLog::record('school_knowledge.visibility_updated', $setting, ['enabled' => $enabled]);

        return to_route('admin.school-knowledge.index')->with('success', $enabled
            ? 'School Knowledge Base is now available to Chat and Agents.'
            : 'School Knowledge Base is now disabled for Chat and Agents.');
    }

    public function upload(Request $request, SchoolKnowledgeService $schoolKnowledge): RedirectResponse
    {
        $data = $request->validate([
            'documents' => ['required', 'array', 'max:'.SchoolKnowledgeService::MAX_UPLOAD_FILES],
            'documents.*' => ['required', 'file', 'max:5120'],
        ]);
        $files = $data['documents'];
        $totalBytes = array_sum(array_map(fn ($file) => $file->getSize() ?: 0, $files));
        if ($totalBytes > SchoolKnowledgeService::MAX_TOTAL_UPLOAD_BYTES) {
            return back()->withErrors(['documents' => 'The total upload size must not exceed 25 MB.']);
        }

        $added = 0;
        $errors = [];
        foreach ($files as $file) {
            try {
                $source = $schoolKnowledge->storeUpload($file, $request->user());
                AdminAuditLog::record('school_knowledge.uploaded', $source, ['filename' => $source->original_name]);
                $added++;
            } catch (\Throwable $exception) {
                $errors[] = $file->getClientOriginalName().': '.$exception->getMessage();
            }
        }

        if ($errors !== []) {
            return back()->withErrors(['documents' => implode(' ', $errors)]);
        }

        return back()->with('success', $added.' document'.($added === 1 ? ' was' : 's were').' added to the School Knowledge Base.');
    }

    public function addWebsite(Request $request, SchoolKnowledgeService $schoolKnowledge): RedirectResponse
    {
        $data = $request->validate(['url' => ['required', 'url', 'max:2048']]);

        try {
            $source = $schoolKnowledge->syncWebsite($data['url'], $request->user());
            AdminAuditLog::record('school_knowledge.website_synced', $source, ['url' => $source->source_url]);

            return back()->with('success', 'The official LSTS page was indexed successfully.');
        } catch (\Throwable $exception) {
            return back()->withErrors(['url' => $exception->getMessage()]);
        }
    }

    public function importIntroduction(Request $request, SchoolKnowledgeService $schoolKnowledge): RedirectResponse
    {
        $result = $schoolKnowledge->importBasicSchoolInformation($request->user());
        $subject = SchoolKnowledgeSource::query()
            ->whereIn('source_url', [
                ...SchoolKnowledgeService::INTRODUCTION_LSTS_URLS,
                ...SchoolKnowledgeService::VIETNAMESE_BASIC_URLS,
            ])
            ->latest('updated_at')
            ->first();
        if ($subject) {
            AdminAuditLog::record('school_knowledge.basic_school_information_imported', $subject, [
                'indexed' => $result['indexed'],
                'failed_count' => count($result['failed']),
            ]);
        }

        if ($result['indexed'] === 0) {
            return back()->withErrors(['website' => 'None of the basic school information pages could be read. Please try again later.']);
        }

        $message = $result['indexed'].' basic school information source'.($result['indexed'] === 1 ? ' was' : 's were').' indexed.';
        if ($result['failed'] !== []) {
            $message .= ' '.count($result['failed']).' page'.(count($result['failed']) === 1 ? ' was' : 's were').' unavailable and can be retried later.';
        }

        return back()->with('success', $message);
    }

    public function sync(Request $request, SchoolKnowledgeSource $source, SchoolKnowledgeService $schoolKnowledge): RedirectResponse
    {
        try {
            if ($source->type === SchoolKnowledgeSource::TYPE_WEBSITE) {
                $source = $schoolKnowledge->syncWebsite((string) $source->source_url, $request->user());
            } else {
                $source = $schoolKnowledge->reindex($source);
            }
            AdminAuditLog::record('school_knowledge.reindexed', $source);

            return back()->with('success', 'Source re-indexed successfully.');
        } catch (\Throwable $exception) {
            return back()->withErrors(['source' => $exception->getMessage()]);
        }
    }

    public function destroy(SchoolKnowledgeSource $source, SchoolKnowledgeService $schoolKnowledge): RedirectResponse
    {
        $title = $source->title;
        $schoolKnowledge->delete($source);
        AdminAuditLog::record('school_knowledge.deleted', null, ['title' => $title]);

        return back()->with('success', 'Source removed from the School Knowledge Base.');
    }
}
