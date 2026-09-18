<?php
namespace App\Services;
use App\Models\AdminAuditLog; use App\Models\Conversation; use App\Models\EmailDraft; use App\Models\User; use Illuminate\Support\Str;
class EmailDraftService { public function create(User $user, ?Conversation $conversation, string $body): EmailDraft { $draft = EmailDraft::create(['user_id'=>$user->id,'conversation_id'=>$conversation?->id,'recipients'=>[],'subject'=>Str::limit(strip_tags($body),120),'body'=>$body,'attachments'=>[]]); AdminAuditLog::record('email_draft.created', $draft, ['subject' => $draft->subject]); return $draft; } }
