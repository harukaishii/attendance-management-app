<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Attendance;
use App\Models\Breaktime;
use Carbon\Carbon;
use App\Enums\AttendanceStatus;
use Illuminate\Support\Facades\DB;
use App\Http\Requests\AttendanceUpdateRequest;

class AdminAttendanceDetailController extends Controller
{
    /**
     * 勤怠詳細画面の表示（管理者用）
     *
     * @param int $id 勤怠ID
     * @return \Illuminate\View\View
     */
    public function show($id)
    {
        // 勤怠データを取得（ユーザー情報と休憩データも含む）
        $attendance = Attendance::with([
            'user', // ユーザー情報も取得
            'breaktimes' => function ($query) {
                $query->orderBy('start_time');
            }
        ])->findOrFail($id);

        if (!$attendance) {
            return redirect()
                ->route('admin_attendance_list.index')
                ->with('error', '指定された勤怠データが見つかりません');
        }

        $note = $attendance->note ?? '';

        // Viewに渡すデータ
        return view('admin.admin_attendance_detail', [
            'attendance' => $attendance,
            'breaktimes' => $attendance->breaktimes,
            'note' => $note,
            'user' => $attendance->user, // ユーザー情報を追加
        ]);
    }

    /**
     * 勤怠情報の更新（管理者用）
     *
     * @param AttendanceUpdateRequest $request
     * @param int $id 勤怠ID
     * @return \Illuminate\Http\RedirectResponse
     */
    public function update(AttendanceUpdateRequest $request, $id)
    {
        // 任意のユーザーの勤怠を取得（管理者はuser_id条件不要）
        $attendance = Attendance::findOrFail($id);

        DB::beginTransaction();

        try {
            // 勤怠データ（Attendance）の更新
            $date = Carbon::parse($attendance->date)->format('Y-m-d');

            $attendance->start_time = Carbon::parse($date . ' ' . $request->start_time);
            $attendance->end_time = $request->end_time
                ? Carbon::parse($date . ' ' . $request->end_time)
                : null;
            $attendance->note = $request->note ?? null;

            // ステータスを「承認前（1）」に更新
            $attendance->status = AttendanceStatus::Unapproved;
            $attendance->save();

            // 既存の休憩データを全て削除（リセット）
            $attendance->breaktimes()->delete();

            // 新しい休憩データ（Breaktime）の作成
            if ($request->has('breaks')) {
                foreach ($request->breaks as $index => $breakData) {
                    $breakStart = $breakData['start'] ?? null;
                    $breakEnd = $breakData['end'] ?? null;

                    // 開始時刻と終了時刻が両方入力されている行のみを保存
                    if ($breakStart && $breakEnd) {
                        $fullBreakStart = $date . ' ' . $breakStart;
                        $fullBreakEnd = $date . ' ' . $breakEnd;

                        Breaktime::create([
                            'attendance_id' => $attendance->id,
                            'user_id' => $attendance->user_id, // 該当ユーザーのIDを使用
                            'start_time' => Carbon::parse($fullBreakStart),
                            'end_time' => Carbon::parse($fullBreakEnd),
                        ]);
                    }
                }
            }

            DB::commit();

            // 完了メッセージと共に元の日付の勤怠一覧へリダイレクト
            return redirect()
                ->route('admin_attendance_list.index', ['date' => $date])
                ->with('success', '勤怠情報を修正しました');
        } catch (\Exception $e) {
            DB::rollBack();
            return back()
                ->withInput()
                ->with('error', '勤怠の修正に失敗しました。時間や休憩の入力に誤りがないかご確認ください。' . $e->getMessage());
        }
    }
}
