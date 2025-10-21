<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Attendance;
use Carbon\Carbon;

class AdminUserAttendanceController extends Controller
{
    /**
     * 特定ユーザーの月次勤怠一覧の表示
     *
     * @param int $user ユーザーID
     * @param int|null $year 年
     * @param int|null $month 月
     * @return \Illuminate\View\View
     */
    public function index($user, $year = null, $month = null)
    {
        // ユーザー情報を取得
        $userModel = User::findOrFail($user);

        // 年月が指定されていない場合は現在の年月を使用
        $year = $year ?? Carbon::now()->year;
        $month = $month ?? Carbon::now()->month;

        // 指定された年月の勤怠データを取得
        $attendances = Attendance::where('user_id', $user)
            ->whereYear('date', $year)
            ->whereMonth('date', $month)
            ->with('breaktimes')
            ->orderBy('date', 'asc')
            ->get()
            ->keyBy(function ($item) {
                return Carbon::parse($item->date)->format('Y-m-d');
            });

        // 月の全日付を生成
        $startDate = Carbon::create($year, $month, 1);
        $endDate = $startDate->copy()->endOfMonth();
        $calendar = [];

        for ($date = $startDate->copy(); $date->lte($endDate); $date->addDay()) {
            $dateKey = $date->format('Y-m-d');
            $attendance = $attendances->get($dateKey);

            // 曜日を日本語に変換
            $dayOfWeek = ['日', '月', '火', '水', '木', '金', '土'][$date->dayOfWeek];
            $formattedDate = $date->format('m/d') . '(' . $dayOfWeek . ')';

            if ($attendance) {
                // 休憩時間の合計を計算（分）
                $totalBreakMinutes = 0;
                foreach ($attendance->breaktimes as $breaktime) {
                    if ($breaktime->start_time && $breaktime->end_time) {
                        $start = Carbon::parse($breaktime->start_time);
                        $end = Carbon::parse($breaktime->end_time);
                        $totalBreakMinutes += $start->diffInMinutes($end);
                    }
                }

                // 勤務時間を計算（分）
                $workTime = 0;
                if ($attendance->start_time && $attendance->end_time) {
                    $start = Carbon::parse($attendance->start_time);
                    $end = Carbon::parse($attendance->end_time);
                    $totalMinutes = $start->diffInMinutes($end);
                    $workTime = $totalMinutes - $totalBreakMinutes;
                }

                $calendar[] = [
                    'date' => $formattedDate,
                    'start' => Carbon::parse($attendance->start_time)->format('H:i'),
                    'end' => $attendance->end_time ? Carbon::parse($attendance->end_time)->format('H:i') : null,
                    'break' => sprintf('%d:%02d', floor($totalBreakMinutes / 60), $totalBreakMinutes % 60),
                    'total' => $workTime > 0 ? sprintf('%d:%02d', floor($workTime / 60), $workTime % 60) : null,
                    'id' => $attendance->id,
                    'isFuture' => false,
                ];
            } else {
                // 勤怠データがない日
                $calendar[] = [
                    'date' => $formattedDate,
                    'start' => null,
                    'end' => null,
                    'break' => null,
                    'total' => null,
                    'id' => null,
                    'isFuture' => false,
                ];
            }
        }

        // 前月・翌月のURL
        $prevMonth = Carbon::create($year, $month, 1)->subMonth();
        $nextMonth = Carbon::create($year, $month, 1)->addMonth();
        $currentMonth = Carbon::create($year, $month, 1);

        $prevMonthUrl = route('admin_user_attendance.index', [
            'user' => $user,
            'year' => $prevMonth->year,
            'month' => $prevMonth->month
        ]);

        $nextMonthUrl = route('admin_user_attendance.index', [
            'user' => $user,
            'year' => $nextMonth->year,
            'month' => $nextMonth->month
        ]);

        return view('admin.user_attendance_list', [
            'user' => $userModel,
            'calendar' => $calendar,
            'currentMonth' => $currentMonth,
            'prevMonthUrl' => $prevMonthUrl,
            'nextMonthUrl' => $nextMonthUrl,
        ]);
    }
}
