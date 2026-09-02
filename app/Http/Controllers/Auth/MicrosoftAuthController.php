<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;

use App\Actions\Teams\CreateTeam;
use Illuminate\Support\Facades\DB;

class MicrosoftAuthController extends Controller
{
    public function redirect()
    {
        return Socialite::driver('microsoft')->redirect();
    }

    public function callback()
    {
        // Nếu cấu hình Microsoft chưa bật, redirect về login với thông báo
        if (! config('services.microsoft.client_id')) {
            return redirect()->route('login.local.form')
                ->with('error', 'Đăng nhập Microsoft chưa được cấu hình. Vui lòng dùng đăng nhập email/mật khẩu.');
        }

        $azureUser = Socialite::driver('microsoft')->user();

        // Gating: chỉ cho phép user đã có trong DB (được admin duyệt thêm) + is_active = true
        $user = User::where('entra_id', $azureUser->getId())->first();

        if (! $user) {
            $user = User::where('email', $azureUser->getEmail())->first();
        }

        // Không có trong DB → chặn, không auto-create
        if (! $user) {
            Auth::logout();
            return redirect()->route('ai-plus.access-pending');
        }

        // Có trong DB nhưng bị khóa (is_active = false)
        if (! $user->is_active) {
            Auth::logout();
            return redirect()->route('ai-plus.access-pending');
        }

        // User hợp lệ: upsert entra_id nếu login SSO lần đầu + cập nhật name
        if (empty($user->entra_id) || $user->entra_id !== $azureUser->getId()) {
            $user->update([
                'entra_id' => $azureUser->getId(),
                'name' => $user->name ?: $azureUser->getName(),
                'source_system' => 'Entra ID',
            ]);
        }

        // Đảm bảo mọi user đều có ít nhất 1 team
        if ($user->ownedTeams()->count() === 0) {
            $team = app(CreateTeam::class)->handle($user, $user->name."'s Team", isPersonal: true);
        } elseif (! $user->currentTeam) {
            $personalTeam = $user->personalTeam();
            if ($personalTeam) {
                $user->switchTeam($personalTeam);
            }
        }

        Auth::login($user, remember: true);

        return redirect()->route('ai-plus.index');
    }

    private function makeInitials(string $name): string
    {
        $parts = explode(' ', trim($name));
        $first = mb_substr($parts[0] ?? '', 0, 1);
        $last = mb_substr(end($parts) ?: '', 0, 1);
        return mb_strtoupper($first.$last);
    }
}
