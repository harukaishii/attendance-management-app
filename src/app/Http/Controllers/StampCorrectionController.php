<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Enums\AttendanceStatus;

class StampCorrectionController extends Controller
{
    public function index(Request $request){
        $userId = Auth::id();

        $status = $request->query('status', 'pending');

        $statusesToFilter = [];
        if ($status === 'approved') {
            $statusesToFilter = [AttendanceStatus::Approved->value];
        } else {
            $statusesToFilter = [AttendanceStatus::Unapproved->value];
        }

        // 3. データを取得
        $applications = Attendance::with('user')
            ->where('user_id', $userId)
            ->whereIn('status', $statusesToFilter)
            ->orderBy('updated_at', 'desc')
            ->get();

        return view('user.stamp_correction_request', [
            'applications' => $applications,
            'currentStatus' => $status,
        ]);
    }
}
