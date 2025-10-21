<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;
use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\AttendanceListController;
use App\Http\Controllers\AttendanceDetailController;
use App\Http\Controllers\StampCorrectionController;
use App\Http\Controllers\AdminAttendanceListController;
use App\Http\Controllers\AdminAttendanceDetailController;
use App\Http\Controllers\AdminStaffController;
use App\Http\Controllers\AdminUserAttendanceController;
use App\Http\Controllers\AdminApplicationController;


/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

// 一般ユーザー
Route::middleware('auth')->group(function () {

    // 打刻画面の表示
    Route::get('/attendance', [AttendanceController::class, 'index'])->name('attendance.index');

    // 打刻処理
    Route::post('attendance/start', [AttendanceController::class, 'start'])->name('attendance.start');
    Route::post('attendance/end', [AttendanceController::class, 'end'])->name('attendance.end');
    Route::post('break/start', [AttendanceController::class, 'breakStart'])->name('break.start');
    Route::post('break/end', [AttendanceController::class, 'breakEnd'])->name('break.end');

    // 勤怠一覧
    Route::get('/attendance/list', [AttendanceListController::class, 'index'])->name('attendance.list.index');
    Route::get('/attendance/list/{year?}/{month?}', [AttendanceListController::class, 'index'])->name('attendance.list.show');

    // 勤怠詳細表示 (GET)
    Route::get('/attendance/detail/{id}', [AttendanceDetailController::class, 'show'])->name('attendance.detail.show');

    // 勤怠更新処理 (POST)
    Route::post('/attendance/detail/{id}', [AttendanceDetailController::class, 'update'])->name('attendance.detail.update');

    // 申請一覧画面
    Route::get('/stamp_correction_request/list', [StampCorrectionController::class, 'index'])->name('stamp_correction.index');
});


// 管理者ログイン フォーム表示（GET）
Route::get('/admin/login', function () {
    return view('auth.admin_login');
})->middleware('guest')->name('admin.login');

// 管理者ログイン処理（POST）
Route::post('/admin/login', function (Request $request) {
    $validated = $request->validate([
        'email' => ['required', 'email'],
        'password' => ['required', 'string'],
    ]);

    if (auth()->attempt($validated, $request->filled('remember'))) {
        // ログインしたユーザーが管理者か確認
        if (auth()->user()->is_admin !== 1) {
            auth()->logout();
            return back()->withErrors(['email' => '管理者のみログイン可能です']);
        }

        $request->session()->regenerate();
        return redirect()->intended(route('admin_attendance_list.index'));
    }

    return back()->withErrors(['email' => 'メールアドレスまたはパスワードが正しくありません']);
})->middleware('guest')->name('admin.login.post');


// 管理者ページ（認証済み＋管理者のみ）
Route::middleware(['auth', 'is.admin'])->group(function () {
    // 勤怠一覧
    Route::get('/admin/attendances', [AdminAttendanceListController::class, 'index'])->name('admin_attendance_list.index');

    //勤怠詳細表示（GET）
    Route::get('/admin/attendances/{id}',[AdminAttendanceDetailController::class,'show'])->name('admin_attendance_detail.show');

    //勤怠詳細修正（POST）
    Route::post('/attendances/{id}', [AdminAttendanceDetailController::class, 'update'])->name('admin_attendance_detail.update');

    //スタッフ一覧
    Route::get('/admin/users', [AdminStaffController::class, 'index'])->name('admin_staff.index');

    //月次勤怠一覧
    Route::get('/admin/users/{user}/attendances/{year?}/{month?}', [AdminUserAttendanceController::class, 'index'])->name('admin_user_attendance.index');

    //申請一覧
    Route::get('/admin/requests',[AdminApplicationController::class,'index'])->name('admin_request.index');

    //承認画面の表示
    Route::get('/admin/requests/{id}', [AdminApplicationController::class, 'show'])->name('admin_request.show');

    //承認処理
    Route::post('/admin/requests/{id}/approve', [AdminApplicationController::class, 'approve'])->name('admin_request.approve');

    // 管理者用ログアウト
    Route::post('/admin/logout', function (Request $request) {
        auth()->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect()->route('admin.login')->with('status', 'ログアウトしました');
    })->name('admin.logout');
});

// 管理者ログイン後のリダイレクト設定
Route::get('/admin', function () {
    return redirect()->route('admin_attendance_list.index');
});


// ログアウト後のリダイレクトを上書き
Route::post('/logout', function (Request $request) {
    auth()->guard('web')->logout();

    $request->session()->invalidate();
    $request->session()->regenerateToken();

    return redirect()->route('login');
})->name('logout');
