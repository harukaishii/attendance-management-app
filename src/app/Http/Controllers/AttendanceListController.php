<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\Attendance;
use Carbon\Carbon;

class AttendanceListController extends Controller
{
    //
    public function index($year = null, $month = null){

        Carbon::setLocale('ja');

        $user = Auth::user();

        //表示年月の指定
        $currentMonth = Carbon::now();
        if($year && $month){
            $currentMonth = Carbon::createFromDate($year,$month,1);
        }

        //月の最初の日と最後の日
        $startDate = $currentMonth -> copy() -> startOfMonth();
        $endDate = $currentMonth -> copy() -> endOfMonth();

        //ログインユーザーの当月分の勤怠データを取得
        //休憩時間も一緒に取得
        $attendances = Attendance::where('user_id', $user->id)
                        ->whereBetween('date',[$startDate,$endDate])
                        ->with('breaktimes')
                        ->get()
                        ->KeyBy(function($item){
                            return Carbon::parse($item->date)->format('Y-m-d');
                        });

        $calendar =[];
        $date = $startDate->copy();

        //月の最初の日から最後の日までループ
        while($date->lte($endDate)){
            $dateString = $date->format('Y-m-d');
            $attendanceData = $attendances->get($dateString);

            $calendar[] = $this->formatAttendanceData($date,$attendanceData);
            $date->addDay();
        }
        //viewに渡すデータ
        return view('user.attendance_list',[
            'calendar' => $calendar,
            'currentMonth' => $currentMonth,


            'prevMonthUrl' => route('attendance.list.show',[
                'year' => $currentMonth->copy()->subMonth()->year,
                'month' => $currentMonth->copy()->subMonth()->month
            ]),

            'nextMonthUrl' => route('attendance.list.show',[
                'year' => $currentMonth->copy()->addMonth()->year,
                'month' => $currentMonth->copy()->addMonth()->month
            ]),

            'currentMonthUrl' => route('attendance.list.index')
        ]);

    }

    private function formatAttendanceData(Carbon $date, $attendance)
    {
        $breakTotal = null;
        $workingTotal = null;
        $startTime = null;
        $endTime = null;
        $attendanceId = null;

        if ($attendance) {
            $attendanceId = $attendance->id;
            $startTime = Carbon::parse($attendance->start_time)->format('H:i');
            $endTime = $attendance->end_time ? Carbon::parse($attendance->end_time)->format('H:i') : null;

            // 休憩時間の合計を計算
            $breakTotalSeconds = $attendance->breaktimes->sum(function ($break) {
                if ($break->end_time) {
                    return Carbon::parse($break->end_time)->diffInSeconds(Carbon::parse($break->start_time));
                }
                return 0;
            });
            $breakTotal = Carbon::parse('@' . $breakTotalSeconds)->format('H:i');

            // 勤務時間の合計を計算
            if ($attendance->end_time) {
                $workSeconds = Carbon::parse($attendance->end_time)->diffInSeconds(Carbon::parse($attendance->start_time));
                $workingTotalSeconds = $workSeconds - $breakTotalSeconds;
                $workingTotal = Carbon::parse('@' . $workingTotalSeconds)->format('H:i');
            }
        }

        // 曜日を日本語に変換
        $dayOfWeek = ['日', '月', '火', '水', '木', '金', '土'][$date->dayOfWeek];

        return [
            'date' => $date->format('m/d') . '(' . $dayOfWeek . ')',
            'raw_date' => $date->format('Y-m-d'),
            'id' => $attendanceId,
            'start' => $startTime,
            'end' => $endTime,
            'break' => $breakTotal,
            'total' => $workingTotal,
            'isFuture' => $date->isFuture(), // 未来日付判定
        ];
    }
}
