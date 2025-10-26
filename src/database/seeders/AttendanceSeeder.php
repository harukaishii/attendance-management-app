<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class AttendanceSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $now = Carbon::now();
        $adminId = 1; // 管理者のID

        // 管理者以外のユーザーを取得
        $users = DB::table('users')->where('is_admin', false)->get();

        $attendances = [];

        // note の選択肢
        $notes = [
            '打刻漏れのため',
            '残業の要請があったため',
            '休憩時間に間違いがあるため',
            '通常勤務',
            '客先対応のため',
            '会議が長引いたため',
            '交通遅延のため',
            '早朝出勤のため',
        ];

        foreach ($users as $user) {
            // 3ヶ月前の1日から今日まで
            $startDate = Carbon::now()->subMonths(3)->startOfMonth();
            $endDate = Carbon::today();

            for ($date = $startDate->copy(); $date->lte($endDate); $date->addDay()) {

                // ランダムに休みを入れる（20%の確率で休み）
                if (rand(1, 100) <= 20) {
                    continue;
                }

                // 出勤時刻
                $startTime = $date->copy()->setTime(rand(7, 11), rand(0, 59), 0);
                // 退勤時刻
                $endTime = $date->copy()->setTime(rand(16, 22), rand(0, 59), 0);

                // ステータスをランダムに設定
                $statusRand = rand(1, 10);
                if ($statusRand <= 8) {
                    // 申請中（80%）
                    $status = 1;
                    $approverId = $adminId;
                    $approvedAt = $endTime->copy()->addHours(rand(1, 3));
                } elseif ($statusRand <= 9) {
                    // 入力済み（10%）
                    $status = 0;
                    $approverId = null;
                    $approvedAt = null;
                } else {
                    // 承認済み（10%）
                    $status = 2;
                    $approverId = $adminId;
                    $approvedAt = $endTime->copy()->addHours(rand(1, 3));
                }

                // note は必ず入れる
                $note = $notes[array_rand($notes)];

                $attendances[] = [
                    'user_id' => $user->id,
                    'date' => $date->format('Y-m-d'),
                    'start_time' => $startTime,
                    'end_time' => $endTime,
                    'status' => $status,
                    'note' => $note,
                    'approver_id' => $approverId,
                    'approved_at' => $approvedAt,
                    'created_at' => $startTime,
                    'updated_at' => $now,
                ];
            }
        }

        // バッチでインサート（大量データ対応）
        foreach (array_chunk($attendances, 100) as $chunk) {
            DB::table('attendances')->insert($chunk);
        }
    }
}
