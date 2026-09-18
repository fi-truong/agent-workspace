<?php

use App\Http\Controllers\AccountPasswordController;
use App\Http\Controllers\Admin\AdminDashboardController;
use App\Http\Controllers\Admin\FaqController;
use App\Http\Controllers\Admin\PromptController;
use App\Http\Controllers\Admin\ShowcaseController;
use App\Http\Controllers\Admin\TemplateController;
use App\Http\Controllers\Admin\TicketController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\AgentController;
use App\Http\Controllers\AgentTemplateController;
use App\Http\Controllers\AgentWorkspaceController;
use App\Http\Controllers\AiPlusController;
use App\Http\Controllers\AiArtifactController;
use App\Http\Controllers\AiPolicyController;
use App\Http\Controllers\Auth\MicrosoftAuthController;
use App\Http\Controllers\ChatMessageController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\MyUsageController;
use App\Http\Controllers\PromptLibraryController;
use App\Http\Controllers\SharingShowcaseController;
use App\Http\Controllers\SupportController;
use App\Http\Controllers\Teams\TeamInvitationController;
use App\Http\Middleware\EnsureTeamMembership;
use Illuminate\Support\Facades\Route;

Route::post('/ai-plus/agent-workspace/send', [ChatMessageController::class, 'store'])
    ->middleware(['auth', 'throttle:chat'])
    ->name('ai-plus.agent-workspace.send');

Route::post('/ai-plus/agent-workspace/send-stream', [ChatMessageController::class, 'stream'])
    ->middleware(['auth', 'throttle:chat'])
    ->name('ai-plus.agent-workspace.send-stream');

Route::inertia('/', 'welcome')->name('home');

// Routes tạm thời để xem trước UI, chưa yêu cầu đăng nhập/team
// TODO: chuyển vào nhóm auth+team bên dưới khi tích hợp SSO/role-based access thật
Route::get('/ai-plus', [AiPlusController::class, 'index'])->name('ai-plus.index');

// AI+ Module Routes
Route::prefix('ai-plus')->name('ai-plus.')->middleware('auth')->group(function () {
    Route::get('/artifacts/{artifact}/download', [AiArtifactController::class, 'download'])->name('artifacts.download');
    Route::get('/agent-workspace/attachments/{conversation}/{filename}', [ChatMessageController::class, 'attachment'])
        ->where('filename', '[A-Za-z0-9_.-]+')
        ->name('agent-workspace.attachments.show');
    Route::patch('/agent-workspace/conversations/{conversation}', [ChatMessageController::class, 'rename'])
        ->name('conversations.rename');
    Route::delete('/agent-workspace/conversations/{conversation}', [ChatMessageController::class, 'destroy'])
        ->name('conversations.destroy');
    Route::get('/agent-workspace', [AgentWorkspaceController::class, 'index'])->name('agent-workspace.index');
    Route::get('/agent-workspace/agents', [AgentController::class, 'index'])->name('agent-workspace.agents.index');
    Route::post('/agent-workspace/agents', [AgentController::class, 'store'])->middleware('throttle:agent-upload')->name('agent-workspace.agents.store');
    Route::get('/agent-workspace/agents/{agent}', [AgentController::class, 'show'])->name('agent-workspace.agents.show');
    Route::put('/agent-workspace/agents/{agent}', [AgentController::class, 'update'])->middleware('throttle:agent-upload')->name('agent-workspace.agents.update');
    Route::delete('/agent-workspace/agents/{agent}', [AgentController::class, 'destroy'])->name('agent-workspace.agents.destroy');
    Route::get('/prompt-library', [PromptLibraryController::class, 'index'])->name('prompt-library.index');
    Route::get('/agent-templates', [AgentTemplateController::class, 'index'])->name('agent-templates.index');
    Route::post('/agent-templates/{template}/use', [AgentTemplateController::class, 'useTemplate'])->name('agent-templates.use');
    Route::get('/sharing-showcase', [SharingShowcaseController::class, 'index'])->name('sharing-showcase.index');
    Route::post('/sharing-showcase', [SharingShowcaseController::class, 'store'])->name('sharing-showcase.store');
    Route::get('/sharing-showcase/{showcase}', [SharingShowcaseController::class, 'show'])->name('sharing-showcase.show');
    Route::post('/sharing-showcase/{showcase}/use', [SharingShowcaseController::class, 'use'])->name('sharing-showcase.use');
    Route::get('/my-usage', [MyUsageController::class, 'index'])->name('my-usage.index');
    Route::get('/ai-policy', [AiPolicyController::class, 'index'])->name('ai-policy.index');
    Route::get('/support', [SupportController::class, 'index'])->name('support.index');
    Route::post('/support', [SupportController::class, 'store'])->middleware('throttle:support')->name('support.store');
});

// Admin Panel Routes
Route::prefix('admin')->name('admin.')->middleware(['auth', 'admin'])->group(function () {
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

    // Users & Roles
    Route::resource('users', UserController::class)->except('show');
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

        return redirect()->intended(route('ai-plus.index'));
    }

    return back()->withErrors(['email' => 'Sai email hoặc mật khẩu.'])->onlyInput('email');
})->middleware('throttle:login')->name('login.local');

require __DIR__.'/settings.php';
