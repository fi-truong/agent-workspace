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
        $azureUser = Socialite::driver('microsoft')->user();

        $user = User::where('entra_id', $azureUser->getId())->first();

        if (! $user) {
            $user = User::where('email', $azureUser->getEmail())->first();
        }

       if ($user) {
    $user->update([
        'entra_id' => $azureUser->getId(),
        'name' => $user->name ?: $azureUser->getName(),
        'source_system' => 'Entra ID',
    ]);
} else {
    $user = DB::transaction(function () use ($azureUser) {
        $newUser = User::create([
            'name' => $azureUser->getName(),
            'email' => $azureUser->getEmail(),
            'password' => bcrypt(str()->random(32)),
            'entra_id' => $azureUser->getId(),
            'initials' => $this->makeInitials($azureUser->getName()),
            'source_system' => 'Entra ID',
            'email_verified_at' => now(),
        ]);

        app(CreateTeam::class)->handle($newUser, $newUser->name."'s Team", isPersonal: true);

        return $newUser;
    });
}
        // Đảm bảo mọi user (kể cả user cũ do Seeder tạo trước khi có SSO) đều có ít nhất 1 team
        if ($user->ownedTeams()->count() === 0) {
            $team = app(CreateTeam::class)->handle($user, $user->name."'s Team", isPersonal: true);
            // CreateTeam::handle đã gọi switchTeam() bên trong
        } elseif (! $user->currentTeam) {
            // User có team nhưng chưa set current_team → chuyển sang personal team đầu tiên
            $personalTeam = $user->personalTeam();
            if ($personalTeam) {
                $user->switchTeam($personalTeam);
            }
        }
        Auth::login($user, remember: true);

        // Sau SSO login: luôn về trang AI+ (Blade, không cần team), không dùng intended()
        // vì intended() có thể là '/' (welcome page Inertia) → lỗi missing current_team parameter
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
