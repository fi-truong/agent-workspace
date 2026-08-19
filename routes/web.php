<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Teams\TeamInvitationController;
use App\Http\Middleware\EnsureTeamMembership;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AiPlusController;
use App\Http\Controllers\AgentWorkspaceController;
use App\Http\Controllers\PromptLibraryController;
use App\Http\Controllers\AgentTemplateController;
use App\Http\Controllers\SharingShowcaseController;
use App\Http\Controllers\MyUsageController;
use App\Http\Controllers\AiPolicyController;
use App\Http\Controllers\SupportController;

Route::inertia('/', 'welcome')->name('home');

// Routes tạm thời để xem trước UI, chưa yêu cầu đăng nhập/team
// TODO: chuyển vào nhóm auth+team bên dưới khi tích hợp SSO/role-based access thật
Route::get('/ai-plus', [AiPlusController::class, 'index'])->name('ai-plus.index');

// AI+ Module Routes
Route::prefix('ai-plus')->name('ai-plus.')->group(function () {
    Route::get('/agent-workspace', [AgentWorkspaceController::class, 'index'])->name('agent-workspace.index');
    Route::get('/prompt-library', [PromptLibraryController::class, 'index'])->name('prompt-library.index');
    Route::get('/agent-templates', [AgentTemplateController::class, 'index'])->name('agent-templates.index');
    Route::get('/sharing-showcase', [SharingShowcaseController::class, 'index'])->name('sharing-showcase.index');
    Route::get('/my-usage', [MyUsageController::class, 'index'])->name('my-usage.index');
    Route::get('/ai-policy', [AiPolicyController::class, 'index'])->name('ai-policy.index');
    Route::get('/support', [SupportController::class, 'index'])->name('support.index');
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
