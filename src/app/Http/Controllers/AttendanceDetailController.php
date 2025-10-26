<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\Attendance;
use App\Models\Breaktime;
use Carbon\Carbon;
use App\Enums\AttendanceStatus;
use Illuminate\Support\Facades\DB;
use App\Http\Requests\AttendanceUpdateRequest;
use Illuminate\Support\Facades\Log;


class AttendanceDetailController extends Controller
{
    //詳細画面の表示
    public function show($id){
        $user = Auth::user();

        //休憩データはあるだけ取ってくる、開始時間順
        $attendance = Attendance::where('user_id',$user->id)
            ->with(['breaktimes' => function ($query) {
                $query->orderBy('start_time');
            }])
            ->findOrFail($id);

        if(!$attendance){
            return redirect()->route('attendance.list.index')->with('error','指定された日の勤怠データが見つかりません');
        }

        //承認まちの時だけ編集不可にする
        $isEditable = (int)$attendance->status->value !== AttendanceStatus::Unapproved->value;

        $note = $attendance->note ?? '';

        // Viewに渡すデータ
        return view('user.attendance_detail', [
            'attendance' => $attendance,
            'breaktimes' => $attendance->breaktimes,
            'note' => $note,
            'isEditable' => $isEditable,
        ]);
    }

    public function update(AttendanceUpdateRequest $request,$id){


        $attendance = Attendance::where('user_id', Auth::id())
        ->findOrFail($id);


        if ($attendance->status->value === AttendanceStatus::Unapproved->value) {
            return back()->with('error', '承認待ちのため修正できません。');
        }

        DB::beginTransaction();

        try {
            // 2. 勤怠データ（Attendance）の更新

            $date = Carbon::parse($attendance->date)->format('Y-m-d');

            $attendance->start_time = Carbon::parse($date . ' ' . $request->start_time);
            $attendance->end_time = $request->end_time ? Carbon::parse($date . ' ' . $request->end_time) : null;
            $attendance->note = $request->note ?? null;


            // ステータスを「承認前（1）」に更新
            $attendance->status = AttendanceStatus::Unapproved;
            $attendance->save();

            // 3. 既存の休憩データを全て削除（リセット）
            $attendance->breaktimes()->delete();


            // 4. 新しい休憩データ（Breaktime）の作成
            if ($request->has('breaks')) {
                foreach ($request->breaks as $index => $breakData) {
                    $breakStart = $breakData['start'] ?? null;
                    $breakEnd = $breakData['end'] ?? null;

                    // 開始時刻と終了時刻が両方入力されている行のみを保存
                    if ($breakStart && $breakEnd) {

                        // ★★★ デバッグログを追加して、ここで止まるか確認 ★★★
                        $fullBreakStart = $date . ' ' . $breakStart;
                        $fullBreakEnd = $date . ' ' . $breakEnd;

                        Breaktime::create([
                            'attendance_id' => $attendance->id,
                            'user_id' => Auth::id(),
                            'start_time' => Carbon::parse($fullBreakStart),
                            'end_time' => Carbon::parse($fullBreakEnd),
                        ]);
                    }
                }
            }

            DB::commit(); // 全ての処理が成功したらコミット

            // 完了メッセージと共に勤怠一覧へリダイレクト
            return redirect()
                ->route('attendance.list.index')
                ->with('success', $date . ' の勤怠を修正申請しました。承認をお待ちください。');

        } catch (\Exception $e) {
            DB::rollBack();
            return back()->withInput()->with('error', '勤怠の修正に失敗しました。時間や休憩の入力に誤りがないかご確認ください。' . $e->getMessage());
        }

    }

}
