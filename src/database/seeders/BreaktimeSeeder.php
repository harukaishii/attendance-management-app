<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class BreaktimeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $now = Carbon::now();
        $breaktimes = [];

        // 管理者以外のユーザーのIDを取得
        $nonAdminUserIds = DB::table('users')->where('is_admin', false)->pluck('id');

        // 管理者以外のユーザーの勤怠データを取得（バッチ処理で効率化）
        DB::table('attendances')
            ->whereIn('user_id', $nonAdminUserIds)
            ->orderBy('id')
            ->chunk(100, function ($attendances) use (&$breaktimes, $now) {
                foreach ($attendances as $attendance) {
                    $date = Carbon::parse($attendance->date);
                    $startTime = Carbon::parse($attendance->start_time);

                    // 休憩回数をランダムに（1〜3回）
                    $breakCount = rand(1, 3);

                    $breakTimes = [];

                    for ($i = 0; $i < $breakCount; $i++) {
                        if ($i === 0) {
                            // 1回目：昼休憩（11:30〜13:30の間に開始）
                            $breakStart = $date->copy()->setTime(rand(11, 13), rand(0, 59), 0);
                            // 休憩時間は30分〜90分
                            $breakEnd = $breakStart->copy()->addMinutes(rand(30, 90));
                        } else {
                            // 2回目以降：午後の休憩（14:00〜17:00の間に開始）
                            $breakStart = $date->copy()->setTime(rand(14, 16), rand(0, 59), 0);
                            // 休憩時間は5分〜30分
                            $breakEnd = $breakStart->copy()->addMinutes(rand(5, 30));
                        }

                        // 重複チェック（簡易版）
                        $overlap = false;
                        foreach ($breakTimes as $existingBreak) {
                            if ($breakStart->lt($existingBreak['end']) && $breakEnd->gt($existingBreak['start'])) {
                                $overlap = true;
                                break;
                            }
                        }

                        if (!$overlap) {
                            $breakTimes[] = [
                                'start' => $breakStart,
                                'end' => $breakEnd,
                            ];

                            $breaktimes[] = [
                                'user_id' => $attendance->user_id,
                                'attendance_id' => $attendance->id,
                                'start_time' => $breakStart,
                                'end_time' => $breakEnd,
                                'created_at' => $startTime,
                                'updated_at' => $now,
                            ];
                        }
                    }
                }
            });

        // バッチでインサート
        foreach (array_chunk($breaktimes, 100) as $chunk) {
            DB::table('breaktimes')->insert($chunk);
        }
    }
}
