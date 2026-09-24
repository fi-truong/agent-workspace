<?php

use App\Http\Controllers\AccountPasswordController;
use App\Http\Controllers\Admin\AdminDashboardController;
use App\Http\Controllers\Admin\AiImageSettingsController;
use App\Http\Controllers\Admin\AiPlusGuideSettingsController;
use App\Http\Controllers\Admin\FaqController;
use App\Http\Controllers\Admin\PromptController;
use App\Http\Controllers\Admin\SchoolKnowledgeController;
use App\Http\Controllers\Admin\ShowcaseController;
use App\Http\Controllers\Admin\TemplateController;
use App\Http\Controllers\Admin\TicketController;
use App\Http\Controllers\Admin\UsageController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\WorkUseMonitoringController;
use App\Http\Controllers\AgentController;
use App\Http\Controllers\AgentTemplateController;
use App\Http\Controllers\AgentWorkspaceController;
use App\Http\Controllers\AiArtifactController;
use App\Http\Controllers\AiPlusController;
use App\Http\Controllers\AiPlusGuideController;
use App\Http\Controllers\AiPolicyController;
use App\Http\Controllers\AiPolicyAcceptanceController;
use App\Http\Controllers\Auth\MicrosoftAuthController;
use App\Http\Controllers\ChatMessageController;
use App\Http\Controllers\ImageWorkspaceController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\MyUsageController;
use App\Http\Controllers\PromptLibraryController;
use App\Http\Controllers\SharingShowcaseController;
use App\Http\Controllers\SupportController;
use App\Http\Controllers\Teams\TeamInvitationController;
use App\Http\Middleware\EnsureTeamMembership;
use Illuminate\Support\Facades\Route;

Route::post('/ai-plus/agent-workspace/send', [ChatMessageController::class, 'store'])
    ->middleware(['auth', 'ai.policy', 'throttle:chat'])
    ->name('ai-plus.agent-workspace.send');

Route::post('/ai-plus/agent-workspace/send-stream', [ChatMessageController::class, 'stream'])
    ->middleware(['auth', 'ai.policy', 'throttle:chat'])
    ->name('ai-plus.agent-workspace.send-stream');
Route::post('/ai-plus/agent-workspace/generate-image', [ChatMessageController::class, 'generateImage'])
    ->middleware(['auth', 'ai.policy', 'throttle:image-generation'])
    ->name('ai-plus.agent-workspace.generate-image');

Route::inertia('/', 'welcome')->name('home');

// AI+ is an internal staff tool: require sign-in before showing its homepage or modules.
Route::get('/ai-plus', [AiPlusController::class, 'index'])
    ->middleware(['auth', 'ai.policy'])
    ->name('ai-plus.index');

Route::middleware('auth')->group(function () {
    Route::get('/ai-plus/policy-acceptance', [AiPolicyAcceptanceController::class, 'show'])
        ->name('ai-plus.policy-acceptance.show');
    Route::post('/ai-plus/policy-acceptance', [AiPolicyAcceptanceController::class, 'accept'])
        ->name('ai-plus.policy-acceptance.accept');
});

Route::post('/ai-plus/guide/reply', [AiPlusGuideController::class, 'reply'])
    ->middleware(['auth', 'ai.policy', 'throttle:chat'])
    ->name('ai-plus.guide.reply');

// AI+ Module Routes
Route::prefix('ai-plus')->name('ai-plus.')->middleware(['auth', 'ai.policy'])->group(function () {
    Route::get('/artifacts/{artifact}/download', [AiArtifactController::class, 'download'])->name('artifacts.download');
    Route::delete('/artifacts/{artifact}', [AiArtifactController::class, 'destroy'])->name('artifacts.destroy');
    Route::get('/agent-workspace/attachments/{conversation}/{filename}', [ChatMessageController::class, 'attachment'])
        ->where('filename', '[A-Za-z0-9_.-]+')
        ->name('agent-workspace.attachments.show');
    Route::patch('/agent-workspace/conversations/{conversation}', [ChatMessageController::class, 'rename'])
        ->name('conversations.rename');
    Route::post('/agent-workspace/conversations/{conversation}/export', [ChatMessageController::class, 'exportConversation'])
        ->middleware('throttle:chat')
        ->name('conversations.export');
    Route::delete('/agent-workspace/conversations/{conversation}', [ChatMessageController::class, 'destroy'])
        ->name('conversations.destroy');
    Route::get('/agent-workspace', [AgentWorkspaceController::class, 'index'])->name('agent-workspace.index');
    Route::get('/agent-workspace/images', [ImageWorkspaceController::class, 'index'])->name('agent-workspace.images.index');
    Route::get('/agent-workspace/images/{message}/download', [ChatMessageController::class, 'downloadImage'])->name('agent-workspace.images.download');
    Route::delete('/agent-workspace/images/{message}', [ChatMessageController::class, 'destroyImage'])->name('agent-workspace.images.destroy');
    Route::get('/agent-workspace/agents', [AgentController::class, 'index'])->name('agent-workspace.agents.index');
    Route::post('/agent-workspace/agents', [AgentController::class, 'store'])->middleware('throttle:agent-upload')->name('agent-workspace.agents.store');
    Route::get('/agent-workspace/agents/{agent}', [AgentController::class, 'show'])->name('agent-workspace.agents.show');
    Route::put('/agent-workspace/agents/{agent}', [AgentController::class, 'update'])->middleware('throttle:agent-upload')->name('agent-workspace.agents.update');
    Route::delete('/agent-workspace/agents/{agent}', [AgentController::class, 'destroy'])->name('agent-workspace.agents.destroy');
    Route::get('/prompt-library', [PromptLibraryController::class, 'index'])->name('prompt-library.index');
    Route::get('/agent-templates', [AgentTemplateController::class, 'index'])->name('agent-templates.index');
    Route::post('/agent-templates/{template}/use', [AgentTemplateController::class, 'useTemplate'])->name('agent-templates.use');
    Route::get('/sharing-showcase', [SharingShowcaseController::class, 'index'])->name('sharing-showcase.index');
    Route::get('/sharing-showcase/{showcase}', [SharingShowcaseController::class, 'show'])->name('sharing-showcase.show');
    Route::post('/sharing-showcase/{showcase}/comments', [SharingShowcaseController::class, 'storeComment'])
        ->middleware('throttle:30,1')
        ->name('sharing-showcase.comments.store');
    Route::delete('/sharing-showcase/{showcase}/comments/{comment}', [SharingShowcaseController::class, 'destroyComment'])
        ->name('sharing-showcase.comments.destroy');
    Route::post('/sharing-showcase/{showcase}/use', [SharingShowcaseController::class, 'use'])->name('sharing-showcase.use');
    Route::get('/my-usage', [MyUsageController::class, 'index'])->name('my-usage.index');
    Route::get('/ai-policy', [AiPolicyController::class, 'index'])->name('ai-policy.index');
    Route::get('/support', [SupportController::class, 'index'])->name('support.index');
    Route::post('/support', [SupportController::class, 'store'])->middleware('throttle:support')->name('support.store');
    Route::get('/support/requests', [SupportController::class, 'myRequests'])->name('support.requests.index');
    Route::get('/support/requests/{ticket}', [SupportController::class, 'showRequest'])->name('support.requests.show');
    Route::post('/support/requests/{ticket}/replies', [SupportController::class, 'storeFollowUp'])
        ->middleware('throttle:support')
        ->name('support.requests.replies.store');
});

// Admin Panel Routes
Route::prefix('admin')->name('admin.')->middleware(['auth', 'ai.policy', 'admin'])->group(function () {
    Route::get('/', [AdminDashboardController::class, 'index'])->name('dashboard');

    // Prompts
    Route::resource('prompts', PromptController::class)->except('show');

    // Agent Templates
    Route::resource('templates', TemplateController::class)->except('show');

    // Showcases
    Route::resource('showcases', ShowcaseController::class)->except('show');

    // FAQs
    Route::resource('faqs', FaqController::class)->except('show');

    // Support Tickets
    Route::resource('tickets', TicketController::class)->only(['index', 'show', 'destroy']);
    Route::patch('tickets/{ticket}/assign', [TicketController::class, 'assign'])->name('tickets.assign');
    Route::patch('tickets/{ticket}/status', [TicketController::class, 'updateStatus'])->name('tickets.status');
    Route::patch('tickets/{ticket}/notes', [TicketController::class, 'storeNote'])->name('tickets.notes');
    Route::post('tickets/{ticket}/replies', [TicketController::class, 'reply'])->name('tickets.replies.store');

    // Users & Roles
    Route::post('users/token-quota', [UserController::class, 'updateTokenQuota'])->name('users.token-quota.update');
    Route::resource('users', UserController::class)->except('show');
    Route::get('usage', [UsageController::class, 'index'])->name('usage.index');

    // Homepage guide
    Route::get('ai-plus-guide', [AiPlusGuideSettingsController::class, 'index'])->name('ai-plus-guide.index');
    Route::put('ai-plus-guide', [AiPlusGuideSettingsController::class, 'update'])->name('ai-plus-guide.update');
    Route::get('ai-image', [AiImageSettingsController::class, 'index'])->name('ai-image.index');
    Route::put('ai-image', [AiImageSettingsController::class, 'update'])->name('ai-image.update');
    Route::get('work-use', [WorkUseMonitoringController::class, 'index'])->name('work-use.index');
    Route::put('work-use', [WorkUseMonitoringController::class, 'update'])->name('work-use.update');
    Route::get('school-knowledge', [SchoolKnowledgeController::class, 'index'])->name('school-knowledge.index');
    Route::put('school-knowledge', [SchoolKnowledgeController::class, 'update'])->name('school-knowledge.update');
    Route::post('school-knowledge/documents', [SchoolKnowledgeController::class, 'upload'])->name('school-knowledge.upload');
    Route::post('school-knowledge/website', [SchoolKnowledgeController::class, 'addWebsite'])->name('school-knowledge.website');
    Route::post('school-knowledge/import-introduction', [SchoolKnowledgeController::class, 'importIntroduction'])->name('school-knowledge.import-introduction');
    Route::post('school-knowledge/{source}/sync', [SchoolKnowledgeController::class, 'sync'])->name('school-knowledge.sync');
    Route::delete('school-knowledge/{source}', [SchoolKnowledgeController::class, 'destroy'])->name('school-knowledge.destroy');
});

// Legacy route redirect
Route::get('/agent-workspace', function () {
    return redirect()->route('ai-plus.agent-workspace.index');
});

Route::prefix('{current_team}')
    ->middleware(['auth', 'verified', EnsureTeamMembership::class])
    ->group(function () {
        Route::get('dashboard', DashboardController::class)->name('dashboard');
    });

Route::middleware(['auth'])->group(function () {
    Route::post('invitations/{invitation}/accept', [TeamInvitationController::class, 'accept'])->name('invitations.accept');
    Route::delete('invitations/{invitation}', [TeamInvitationController::class, 'decline'])->name('invitations.decline');
});

Route::get('/auth/microsoft/redirect', [MicrosoftAuthController::class, 'redirect'])
    ->name('auth.microsoft.redirect');

Route::get('/auth/microsoft/callback', [MicrosoftAuthController::class, 'callback'])
    ->name('auth.microsoft.callback');

Route::get('/login', function () {
    return redirect()->route('login.local.form');
})->name('login');

// Local email/password login for dev
Route::post('/logout', function () {
    Auth::logout();
    request()->session()->invalidate();
    request()->session()->regenerateToken();

    return redirect()->route('login.local.form');
})->name('logout');

Route::get('/ai-plus/access-pending', function () {
    return view('ai-plus.access-pending');
})->name('ai-plus.access-pending');

// Đổi mật khẩu (cần đăng nhập).
Route::middleware('auth')->group(function () {
    Route::get('/account/password', [AccountPasswordController::class, 'show'])
        ->name('account.password');
    Route::post('/account/password', [AccountPasswordController::class, 'update'])
        ->name('account.password.update');
});

Route::get('/login-local', fn () => view('auth.login'))->name('login.local.form');
Route::post('/login-local', function () {
    $credentials = request()->validate(['email' => 'required|email', 'password' => 'required']);
    $credentials['is_active'] = true;
    if (auth()->attempt($credentials, request()->boolean('remember'))) {
        request()->session()->regenerate();
        auth()->user()?->forceFill(['last_login_at' => now()])->save();

        return \App\Http\Controllers\AiPolicyAcceptanceController::hasAcceptedCurrentVersion(auth()->user())
            ? redirect()->intended(route('ai-plus.index'))
            : redirect()->route('ai-plus.policy-acceptance.show');
    }

    return back()->withErrors(['email' => 'Sai email hoặc mật khẩu.'])->onlyInput('email');
})->middleware('throttle:login')->name('login.local');

require __DIR__.'/settings.php';
