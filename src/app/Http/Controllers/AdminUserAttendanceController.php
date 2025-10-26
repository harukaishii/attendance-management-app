<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Attendance;
use App\Models\Breaktime;
use Carbon\Carbon;
use Carbon\CarbonPeriod;

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

    public function downloadCsv($user_id, Request $request)
    {
        $user = User::findOrFail($user_id);

        // 年と月を取得（クエリパラメータから）
        $year = $request->query('year', now()->year);
        $month = $request->query('month', now()->month);

        // 指定された年月の1日を作成
        $currentMonth = Carbon::create($year, $month, 1);

        // 勤怠データ取得
        $startOfMonth = $currentMonth->copy()->startOfMonth();
        $endOfMonth = $currentMonth->copy()->endOfMonth();

        $attendances = Attendance::where('user_id', $user_id)
            ->whereYear('date', $year)
            ->whereMonth('date', $month)
            ->with('breaktimes')
            ->orderBy('date')
            ->get()
            ->keyBy(function ($item) {
                return Carbon::parse($item->date)->format('Y-m-d');
            });

        // CSVデータ作成
        $csvData = [];

        // ヘッダー行
        $csvData[] = ['日付', '出勤', '退勤', '休憩', '合計'];

        // データ行
        $period = CarbonPeriod::create($startOfMonth, $endOfMonth);
        foreach ($period as $date) {
            $dateStr = $date->format('Y-m-d');
            $attendance = $attendances->get($dateStr);

            if ($attendance && $attendance->start_time) {
                // 休憩時間の合計を計算
                $totalBreak = 0;
                foreach ($attendance->breaktimes as $breaktime) {
                    if ($breaktime->start_time && $breaktime->end_time) {
                        $totalBreak += Carbon::parse($breaktime->end_time)->diffInMinutes(Carbon::parse($breaktime->start_time));
                    }
                }

                // 勤務時間を計算
                $totalWork = 0;
                if ($attendance->start_time && $attendance->end_time) {
                    $totalWork = Carbon::parse($attendance->end_time)->diffInMinutes(Carbon::parse($attendance->start_time)) - $totalBreak;
                }

                // 曜日を追加
                $dayOfWeek = ['日', '月', '火', '水', '木', '金', '土'][$date->dayOfWeek];
                $formattedDate = $date->format('m/d') . '(' . $dayOfWeek . ')';

                $csvData[] = [
                    $formattedDate,
                    $attendance->start_time ? Carbon::parse($attendance->start_time)->format('H:i') : '-',
                    $attendance->end_time ? Carbon::parse($attendance->end_time)->format('H:i') : '-',
                    $totalBreak > 0 ? sprintf('%d:%02d', floor($totalBreak / 60), $totalBreak % 60) : '-',
                    $totalWork > 0 ? sprintf('%d:%02d', floor($totalWork / 60), $totalWork % 60) : '-',
                ];
            } else {
                // 曜日を追加
                $dayOfWeek = ['日', '月', '火', '水', '木', '金', '土'][$date->dayOfWeek];
                $formattedDate = $date->format('m/d') . '(' . $dayOfWeek . ')';

                $csvData[] = [
                    $formattedDate,
                    '-',
                    '-',
                    '-',
                    '-',
                ];
            }
        }

        // CSVファイル生成
        $fileName = sprintf('%s_%s_勤怠.csv', $user->name, $currentMonth->format('Y年m月'));

        $callback = function () use ($csvData) {
            $file = fopen('php://output', 'w');
            // BOM追加（Excel対応）
            fprintf($file, chr(0xEF) . chr(0xBB) . chr(0xBF));

            foreach ($csvData as $row) {
                fputcsv($file, $row);
            }
            fclose($file);
        };

        return response()->stream($callback, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $fileName . '"',
        ]);
    }
}
