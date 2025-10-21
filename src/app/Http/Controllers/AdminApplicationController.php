<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Attendance;
use App\Enums\AttendanceStatus;

class AdminApplicationController extends Controller
{
    /**
     * 申請一覧の表示
     *
     * @param Request $request
     * @return \Illuminate\View\View
     */
    public function index(Request $request)
    {
        $status = $request->get('status', 'pending');

        // ステータスに応じてデータを取得
        if ($status === 'pending') {
            // 承認待ち（承認前）
            $applications = Attendance::where('status', AttendanceStatus::Unapproved)
                ->with('user')
                ->orderBy('updated_at', 'desc')
                ->get();
        } else {
            // 承認済み
            $applications = Attendance::where('status', AttendanceStatus::Approved)
                ->with('user')
                ->orderBy('updated_at', 'desc')
                ->get();
        }

        return view('admin.application_list', [
            'applications' => $applications,
            'status' => $status,
        ]);
    }

    /**
     * 承認画面の表示
     *
     * @param int $idshow
     * @return \Illuminate\View\View
     */
    public function show($id)
    {
        $attendance = Attendance::with(['user', 'breaktimes' => function ($query) {
            $query->orderBy('start_time');
        }])->findOrFail($id);

        return view('admin.application_detail', [
            'attendance' => $attendance,
            'breaktimes' => $attendance->breaktimes,
            'user' => $attendance->user,
        ]);
    }

    /**
     * 承認処理
     *
     * @param Request $request
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function approve(Request $request, $id)
    {
        $attendance = Attendance::findOrFail($id);

        $attendance->status = AttendanceStatus::Approved;
        $attendance->save();

        return response()->json([
            'success' => true,
            'message' => '承認しました',
        ]);
    }

}
