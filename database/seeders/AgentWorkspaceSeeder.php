<?php

namespace Database\Seeders;

use App\Models\Agent;
use App\Models\Conversation;
use App\Models\User;
use App\Models\Workflow;
use Illuminate\Database\Seeder;

class AgentWorkspaceSeeder extends Seeder
{
    public function run(): void
    {
        // Dữ liệu demo cho Agent Workspace, gắn với tài khoản Fi Truong.
        // Khi có SSO thật, dữ liệu này sẽ do chính người dùng tạo ra qua UI,
        // không cần seed nữa — đây chỉ để trang có nội dung khi demo/xem trước.
        $user = User::where('email', 'fi.truong@lsts.edu.vn')->first();
        if (! $user) {
            return;
        }

        $conversations = [
            'Email draft for parent meeting',
            'Lesson plan - Grade 7 Math',
            'Summary of student progress',
        ];
        foreach ($conversations as $title) {
            Conversation::create(['user_id' => $user->id, 'title' => $title]);
        }

        $agents = [
            'Math Quiz Generator',
            'Email Assistant (Bilingual)',
        ];
        foreach ($agents as $title) {
            Agent::create(['user_id' => $user->id, 'title' => $title, 'is_shared' => false]);
        }

        $workflows = [
            'Weekly Report Generator',
            'Student Progress Summary',
        ];
        foreach ($workflows as $title) {
            Workflow::create(['user_id' => $user->id, 'title' => $title, 'is_shared' => false]);
        }
    }
}
