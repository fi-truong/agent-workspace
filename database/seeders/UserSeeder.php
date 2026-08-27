<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        // Dữ liệu nhân sự demo — sau này thay bằng đồng bộ thật từ Entra ID (SSO)
        // Password chỉ là placeholder, không dùng để đăng nhập thật cho tới khi tích hợp SSO.
        $staff = [
            ['name' => 'Admin User', 'email' => 'admin@lsts.edu.vn', 'initials' => 'AD', 'role' => 'Admin', 'department' => 'Administration'],
            ['name' => 'Fi Truong', 'email' => 'ciec.coordinator.04@lsts.edu.vn', 'initials' => 'FT', 'role' => 'CIEC Coordinator • Teacher', 'department' => 'CIEC'],
            ['name' => 'Toan Huynh', 'email' => 'toan.huynh@lsts.edu.vn', 'initials' => 'TH', 'role' => 'Teacher', 'department' => 'Math Department'],
            ['name' => 'Ngoc Tran', 'email' => 'ngoc.tran@lsts.edu.vn', 'initials' => 'NT', 'role' => 'Teacher', 'department' => 'General'],
            ['name' => 'Lan Hoang', 'email' => 'lan.hoang@lsts.edu.vn', 'initials' => 'LH', 'role' => 'Teacher', 'department' => 'English Department'],
            ['name' => 'Dung Vu', 'email' => 'dung.vu@lsts.edu.vn', 'initials' => 'DV', 'role' => 'Teacher', 'department' => 'Science Department'],
            ['name' => 'Ha Nguyen', 'email' => 'ha.nguyen@lsts.edu.vn', 'initials' => 'HN', 'role' => 'Staff', 'department' => 'Academic Office'],
            ['name' => 'Mai Tran', 'email' => 'mai.tran@lsts.edu.vn', 'initials' => 'MT', 'role' => 'Staff', 'department' => 'Counseling Office'],
            ['name' => 'Chi Tran', 'email' => 'chi.tran@lsts.edu.vn', 'initials' => 'CT', 'role' => 'Teacher', 'department' => 'Vietnamese Department'],
        ];

        foreach ($staff as $data) {
            User::firstOrCreate(
                ['email' => $data['email']],
                [
                    'name' => $data['name'],
                    'password' => Hash::make('changeme-'.uniqid()),
                    'initials' => $data['initials'],
                    'role' => $data['role'],
                    'department' => $data['department'],
                ]
            );
        }
    }
}
