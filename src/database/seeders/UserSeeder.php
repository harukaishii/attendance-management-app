<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Carbon\Carbon;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $now = Carbon::now();

        // 管理者ユーザー（ログイン可能）
        DB::table('users')->insert([
            'name' => '管理者',
            'email' => 'admin@example.com',
            'email_verified_at' => $now,
            'password' => Hash::make('admin123'),
            'is_admin' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        // 一般ユーザー（ログイン可能）
        DB::table('users')->insert([
            'name' => '田中太郎',
            'email' => 'user@example.com',
            'email_verified_at' => $now,
            'password' => Hash::make('user1234'),
            'is_admin' => false,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        // ランダムな一般ユーザー9人（ログイン不可）
        $names = [
            '佐藤花子',
            '鈴木一郎',
            '高橋美咲',
            '渡辺健太',
            '伊藤さくら',
            '山本大輔',
            '中村優子',
            '小林翔太',
            '加藤美穂'
        ];

        foreach ($names as $name) {
            DB::table('users')->insert([
                'name' => $name,
                'email' => Str::slug($name, '') . rand(100, 999) . '@example.com',
                'email_verified_at' => $now,
                'password' => Hash::make(Str::random(32)),
                'is_admin' => false,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }
}
