<?php
namespace App\Http\Controllers;
use App\Models\AdminAuditLog;
use App\Models\AiArtifact;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class AiArtifactController extends Controller
{
    public function download(Request $request, AiArtifact $artifact)
    {
        abort_unless($artifact->user_id === $request->user()->id, 403);
        abort_unless(Storage::disk('ai-artifacts')->exists($artifact->path), 404);
        return Storage::disk('ai-artifacts')->download($artifact->path, $artifact->name, ['Content-Type' => $artifact->mime_type]);
    }

    public function destroy(Request $request, AiArtifact $artifact)
    {
        abort_unless($artifact->user_id === $request->user()->id, 403);

        Storage::disk('ai-artifacts')->delete($artifact->path);
        AdminAuditLog::record('ai_artifact.deleted', $artifact, ['name' => $artifact->name]);
        $artifact->delete();

        return response()->json(['deleted' => true]);
    }
}
