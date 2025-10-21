<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Attendance;
use App\Models\Breaktime;
use Carbon\Carbon;
use Illuminate\Http\Request;

class AdminAttendanceListController extends Controller
{
    public function index(Request $request)
    {
        // ミドルウェア 'is.admin' で既に管理者チェックが行われているため、
        // ここでのチェックは不要

        // 日付パラメータを取得、なければ当日
        $today = $request->query('date') ? $request->query('date') : Carbon::now()->format('Y-m-d');

        // 全ユーザーを取得
        $users = User::all();

        // 当日の勤怠データを構築
        $attendanceData = [];

        foreach ($users as $user) {
            $attendance = Attendance::where('user_id', $user->id)
                ->whereDate('date', $today)
                ->first();

            $startTime = null;
            $endTime = null;
            $breakTime = null;
            $totalTime = null;
            $attendanceId = null;

            if ($attendance) {
                $attendanceId = $attendance->id;
                $startTime = $attendance->start_time ? Carbon::parse($attendance->start_time)->format('H:i') : null;
                $endTime = $attendance->end_time ? Carbon::parse($attendance->end_time)->format('H:i') : null;

                // 休憩時間を計算（複数回取得）
                $breakTimes = Breaktime::where('attendance_id', $attendance->id)->get();
                $totalBreakMinutes = 0;

                foreach ($breakTimes as $break) {
                    $breakStart = Carbon::parse($break->start_time);
                    $breakEnd = Carbon::parse($break->end_time);
                    $totalBreakMinutes += $breakEnd->diffInMinutes($breakStart);
                }

                if ($totalBreakMinutes > 0) {
                    $breakHours = intdiv($totalBreakMinutes, 60);
                    $breakMins = $totalBreakMinutes % 60;
                    $breakTime = sprintf('%d:%02d', $breakHours, $breakMins);
                }

                // 合計勤務時間を計算
                if ($startTime && $endTime) {
                    $start = Carbon::parse($attendance->start_time);
                    $end = Carbon::parse($attendance->end_time);
                    $totalMinutes = $end->diffInMinutes($start);

                    // 休憩時間を差し引く
                    if ($totalBreakMinutes > 0) {
                        $totalMinutes -= $totalBreakMinutes;
                    }

                    $totalHours = intdiv($totalMinutes, 60);
                    $totalMins = $totalMinutes % 60;
                    $totalTime = sprintf('%d:%02d', $totalHours, $totalMins);
                }
            }

            $attendanceData[] = [
                'user_id' => $user->id,
                'name' => $user->name,
                'start' => $startTime,
                'end' => $endTime,
                'break' => $breakTime,
                'total' => $totalTime,
                'id' => $attendanceId,
            ];
        }

        // ユーザー名でソート
        usort($attendanceData, function ($a, $b) {
            return strcmp($a['name'], $b['name']);
        });

        return view('admin.admin_attendance_list', [
            'attendanceData' => $attendanceData,
            'today' => $today,
        ]);
    }
}
