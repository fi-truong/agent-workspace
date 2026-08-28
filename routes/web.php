<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Teams\TeamInvitationController;
use App\Http\Middleware\EnsureTeamMembership;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AiPlusController;
use App\Http\Controllers\AgentWorkspaceController;
use App\Http\Controllers\AgentController;
use App\Http\Controllers\PromptLibraryController;
use App\Http\Controllers\AgentTemplateController;
use App\Http\Controllers\SharingShowcaseController;
use App\Http\Controllers\MyUsageController;
use App\Http\Controllers\AiPolicyController;
use App\Http\Controllers\SupportController;

use App\Http\Controllers\ChatMessageController;

use App\Http\Controllers\Auth\MicrosoftAuthController;
use App\Http\Controllers\Admin\AdminDashboardController;
use App\Http\Controllers\Admin\PromptController;
use App\Http\Controllers\Admin\TemplateController;
use App\Http\Controllers\Admin\ShowcaseController;
use App\Http\Controllers\Admin\FaqController;
use App\Http\Controllers\Admin\TicketController;
use App\Http\Controllers\Admin\UserController;

Route::post('/ai-plus/agent-workspace/send', [ChatMessageController::class, 'store'])
    ->name('ai-plus.agent-workspace.send');

Route::inertia('/', 'welcome')->name('home');

// Routes tạm thời để xem trước UI, chưa yêu cầu đăng nhập/team
// TODO: chuyển vào nhóm auth+team bên dưới khi tích hợp SSO/role-based access thật
Route::get('/ai-plus', [AiPlusController::class, 'index'])->name('ai-plus.index');

// AI+ Module Routes
Route::prefix('ai-plus')->name('ai-plus.')->group(function () {
    Route::get('/agent-workspace', [AgentWorkspaceController::class, 'index'])->name('agent-workspace.index');
    Route::get('/agent-workspace/agents', [AgentController::class, 'index'])->name('agent-workspace.agents.index');
    Route::post('/agent-workspace/agents', [AgentController::class, 'store'])->name('agent-workspace.agents.store');
    Route::get('/agent-workspace/agents/{agent}', [AgentController::class, 'show'])->name('agent-workspace.agents.show');
    Route::put('/agent-workspace/agents/{agent}', [AgentController::class, 'update'])->name('agent-workspace.agents.update');
    Route::delete('/agent-workspace/agents/{agent}', [AgentController::class, 'destroy'])->name('agent-workspace.agents.destroy');
    Route::get('/prompt-library', [PromptLibraryController::class, 'index'])->name('prompt-library.index');
    Route::get('/agent-templates', [AgentTemplateController::class, 'index'])->name('agent-templates.index');
    Route::get('/sharing-showcase', [SharingShowcaseController::class, 'index'])->name('sharing-showcase.index');
    Route::post('/sharing-showcase', [SharingShowcaseController::class, 'store'])->name('sharing-showcase.store');
    Route::get('/sharing-showcase/{showcase}', [SharingShowcaseController::class, 'show'])->name('sharing-showcase.show');
    Route::get('/my-usage', [MyUsageController::class, 'index'])->name('my-usage.index');
    Route::get('/ai-policy', [AiPolicyController::class, 'index'])->name('ai-policy.index');
    Route::get('/support', [SupportController::class, 'index'])->name('support.index');
    Route::post('/support', [SupportController::class, 'store'])->name('support.store');
});

// Admin Panel Routes
Route::prefix('admin')->name('admin.')->middleware(['auth'])->group(function () {
    Route::get('/', [AdminDashboardController::class, 'index'])->name('dashboard');

    // Prompts
    Route::resource('prompts', PromptController::class);

    // Agent Templates
    Route::resource('templates', TemplateController::class);

    // Showcases
    Route::resource('showcases', ShowcaseController::class);

    // FAQs
    Route::resource('faqs', FaqController::class);

    // Support Tickets
    Route::resource('tickets', TicketController::class);
    Route::patch('tickets/{ticket}/assign', [TicketController::class, 'assign'])->name('tickets.assign');
    Route::patch('tickets/{ticket}/status', [TicketController::class, 'updateStatus'])->name('tickets.status');

    // Users & Roles
    Route::resource('users', UserController::class);
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
    return inertia('auth/login');
})->name('login');

// Local email/password login for dev
Route::get('/login-local', fn() => view('auth.login'))->name('login.local.form');
Route::post('/login-local', function() {
    $credentials = request()->validate(['email'=>'required|email','password'=>'required']);
    if (auth()->attempt($credentials, request()->boolean('remember'))) {
        request()->session()->regenerate();
        return redirect()->intended(route('admin.dashboard'));
    }
    return back()->withErrors(['email' => 'Sai email hoặc mật khẩu.'])->onlyInput('email');
})->name('login.local');

require __DIR__.'/settings.php';
