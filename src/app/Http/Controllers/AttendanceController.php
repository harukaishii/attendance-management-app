<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\Breaktime;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

class AttendanceController extends Controller
{
    //打刻画面の表示
    public function index()
    {
        $user = Auth::user();
        $attendance = Attendance::where('user_id', $user->id)
            ->whereDate('date', Carbon::today())
            ->first();

        $attendanceStatus = 'initial';
        if ($attendance) {
            if ($attendance->end_time) {
                $attendanceStatus = 'finished';
            } else {
                $breaktime = Breaktime::where('attendance_id', $attendance->id)
                    ->whereNull('end_time')
                    ->first();
                if ($breaktime) {
                    $attendanceStatus = 'onBreak';
                } else {
                    $attendanceStatus = 'working';
                }
            }
        }

        // 日付を日本語形式に変換
        $today = Carbon::today();
        $dayOfWeek = ['日', '月', '火', '水', '木', '金', '土'][$today->dayOfWeek];
        $todayFormatted = $today->format('Y年n月j日') . '(' . $dayOfWeek . ')';

        return view('user.attendance', compact('attendanceStatus', 'todayFormatted'));
    }

    //出勤ボタンの処理
    public function start(){
        $user = Auth::user();

        $attendance = Attendance::where('user_id',$user->id)
            ->whereDate('date',Carbon::today())
            ->first();
        if(is_null($attendance)){
            Attendance::create([
                'user_id' => $user->id,
                'date' => Carbon::today(),
                'start_time' => Carbon::now(),
            ]);
            return redirect()->back()->with('success','出勤打刻が完了しました');
        }
        return redirect()->back()->with('error','すでに出勤打刻済みです');
    }

    //退勤ボタンの処理
    public function end(){
        $user = Auth::user();
        $attendance = Attendance::where('user_id', $user->id)
            ->whereDate('date', Carbon::today())
            ->first();
        //休憩中の場合は退勤できない
        $breaktime = Breaktime::where('attendance_id',$attendance->id)
            ->whereNull('end_time')
            ->first();
        if(is_null($attendance)){
            return redirect()->back()->with('error','出勤打刻がされていません');
        }
        if($breaktime){
            return redirect()->back()->with('error','休憩中です。先に休憩を終了してください');
        }
        if(!is_null($attendance->end_time)){
            return redirect()->back()->with('error','すでに出勤済です');
        }
        $attendance->update(['end_time' => Carbon::now()]);
        return redirect()->back()->with('success','退勤打刻が完了しました');
    }
    //休憩入りボタンの処理
    public function breakStart(){
        $user = Auth::user();
        $attendance = Attendance::where('user_id', $user->id)
            ->whereDate('date', Carbon::today())
            ->first();

        if (is_null($attendance)) {
            return redirect()->back()->with('error', '出勤打刻がされていません');
        }

        $breaktime = Breaktime::where('attendance_id',$attendance->id)
            ->whereNull('end_time')
            ->first();

        if($breaktime){
            return redirect()->back()->with('error','すでに休憩中です');
        }

        Breaktime::create([
            'user_id' => $user->id,
            'attendance_id' => $attendance->id,
            'start_time' => Carbon::now(),
        ]);
        return redirect()->back()->with('success', '休憩開始');
    }
    //休憩戻るボタンの処理
    public function breakEnd(){
        $user = Auth::user();
        $attendance = Attendance::where('user_id', $user->id)
            ->whereDate('date', Carbon::today())
            ->first();
        $breaktime = Breaktime::where('attendance_id', $attendance->id)
            ->whereNull('end_time')
            ->first();
        if (is_null($breaktime)){
            return redirect()->back()->with('error', '休憩開始打刻がされていません');
        }
        $breaktime->update(['end_time' => Carbon::now()]);
        return redirect()->back()->with('success', '休憩終了');

    }

}
