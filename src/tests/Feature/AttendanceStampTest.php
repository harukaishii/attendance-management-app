<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use App\Models\User;
use App\Models\Attendance;
use App\Models\Breaktime;
use Carbon\Carbon;
use Illuminate\Support\Facades\Hash;

class AttendanceStampTest extends TestCase
{
    use DatabaseTransactions;

    private $testUser;

    protected function setUp(): void
    {
        parent::setUp();

        // テスト専用のユーザーを作成
        $this->testUser = User::create([
            'name' => 'テスト専用ユーザー',
            'email' => 'test_' . uniqid() . '@example.com', // ユニークなメールアドレス
            'email_verified_at' => now(),
            'password' => Hash::make('password'),
            'is_admin' => 0,
        ]);
    }

    /**
     * 未認証ユーザーはアクセスできない
     */
    public function test_未認証ユーザーは打刻画面にアクセスできない()
    {
        $response = $this->get(route('attendance.index'));
        $response->assertRedirect(route('login'));
    }

    /**
     * 認証済みユーザーは打刻画面にアクセスできる
     */
    public function test_認証済みユーザーは打刻画面にアクセスできる()
    {
        $this->actingAs($this->testUser);
        $response = $this->get(route('attendance.index'));

        $response->assertStatus(200);
        $response->assertViewIs('user.attendance');
    }

    /**
     * 日時が正しく表示される
     */
    public function test_現在の日時が正しい形式で表示される()
    {
        $this->actingAs($this->testUser);
        $response = $this->get(route('attendance.index'));

        $response->assertStatus(200);
        $response->assertViewHas('todayFormatted');

        $todayFormatted = $response->viewData('todayFormatted');

        // 日付フォーマットの検証（例: "2025年11月2日(日)"）
        $pattern = '/^[0-9]{4}年[0-9]{1,2}月[0-9]{1,2}日[(][日月火水木金土][)]$/u';
        $this->assertMatchesRegularExpression($pattern, $todayFormatted);
    }

    /**
     * ステータス：勤務外
     */
    public function test_勤務外の場合ステータスがinitialと表示される()
    {
        $this->actingAs($this->testUser);

        // 今日の勤怠データを削除（勤務外状態にする）
        Attendance::where('user_id', $this->testUser->id)
            ->whereDate('date', Carbon::today())
            ->delete();

        $response = $this->get(route('attendance.index'));

        $response->assertStatus(200);
        $response->assertViewHas('attendanceStatus', 'initial');
    }

    /**
     * ステータス：出勤中
     */
    public function test_出勤中の場合ステータスがworkingと表示される()
    {
        $this->actingAs($this->testUser);

        // 出勤済み・退勤なしのデータを作成
        Attendance::where('user_id', $this->testUser->id)
            ->whereDate('date', Carbon::today())
            ->delete();

        Attendance::create([
            'user_id' => $this->testUser->id,
            'date' => Carbon::today(),
            'start_time' => Carbon::now()->subHours(2),
            'end_time' => null,
        ]);

        $response = $this->get(route('attendance.index'));

        $response->assertStatus(200);
        $response->assertViewHas('attendanceStatus', 'working');
    }

    /**
     * ステータス：休憩中
     */
    public function test_休憩中の場合ステータスがonBreakと表示される()
    {
        $this->actingAs($this->testUser);

        // 出勤済みのデータを作成
        Attendance::where('user_id', $this->testUser->id)
            ->whereDate('date', Carbon::today())
            ->delete();

        $attendance = Attendance::create([
            'user_id' => $this->testUser->id,
            'date' => Carbon::today(),
            'start_time' => Carbon::now()->subHours(3),
            'end_time' => null,
        ]);

        // 休憩中のデータを作成
        Breaktime::where('attendance_id', $attendance->id)->delete();
        Breaktime::create([
            'user_id' => $this->testUser->id,
            'attendance_id' => $attendance->id,
            'start_time' => Carbon::now()->subMinutes(30),
            'end_time' => null,
        ]);

        $response = $this->get(route('attendance.index'));

        $response->assertStatus(200);
        $response->assertViewHas('attendanceStatus', 'onBreak');
    }

    /**
     * ステータス：退勤済
     */
    public function test_退勤済の場合ステータスがfinishedと表示される()
    {
        $this->actingAs($this->testUser);

        // 出勤・退勤済みのデータを作成
        Attendance::where('user_id', $this->testUser->id)
            ->whereDate('date', Carbon::today())
            ->delete();

        Attendance::create([
            'user_id' => $this->testUser->id,
            'date' => Carbon::today(),
            'start_time' => Carbon::now()->subHours(8),
            'end_time' => Carbon::now()->subHours(1),
        ]);

        $response = $this->get(route('attendance.index'));

        $response->assertStatus(200);
        $response->assertViewHas('attendanceStatus', 'finished');
    }

    /**
     * 出勤機能：正常に出勤できる
     */
    public function test_勤務外状態から出勤できる()
    {
        $this->actingAs($this->testUser);

        // 今日の勤怠データを削除
        Attendance::where('user_id', $this->testUser->id)
            ->whereDate('date', Carbon::today())
            ->delete();

        $response = $this->post(route('attendance.start'));

        $response->assertRedirect();
        $response->assertSessionHas('success', '出勤打刻が完了しました');

        // データベースに出勤記録があることを確認
        $this->assertDatabaseHas('attendances', [
            'user_id' => $this->testUser->id,
            'date' => Carbon::today()->toDateString(),
        ]);
    }

    /**
     * 出勤機能：同日に2回出勤できない
     */
    public function test_同日に2回目の出勤はできない()
    {
        $this->actingAs($this->testUser);

        // 既に出勤済みのデータを作成
        Attendance::where('user_id', $this->testUser->id)
            ->whereDate('date', Carbon::today())
            ->delete();

        Attendance::create([
            'user_id' => $this->testUser->id,
            'date' => Carbon::today(),
            'start_time' => Carbon::now()->subHours(2),
        ]);

        $response = $this->post(route('attendance.start'));

        $response->assertRedirect();
        $response->assertSessionHas('error', 'すでに出勤打刻済みです');
    }

    /**
     * 休憩機能：出勤中から休憩できる
     */
    public function test_出勤中状態から休憩できる()
    {
        $this->actingAs($this->testUser);

        // 出勤済みのデータを作成
        Attendance::where('user_id', $this->testUser->id)
            ->whereDate('date', Carbon::today())
            ->delete();

        $attendance = Attendance::create([
            'user_id' => $this->testUser->id,
            'date' => Carbon::today(),
            'start_time' => Carbon::now()->subHours(2),
            'end_time' => null,
        ]);

        Breaktime::where('attendance_id', $attendance->id)->delete();

        $response = $this->post(route('break.start'));

        $response->assertRedirect();
        $response->assertSessionHas('success', '休憩開始');

        // データベースに休憩記録があることを確認
        $this->assertDatabaseHas('breaktimes', [
            'user_id' => $this->testUser->id,
            'attendance_id' => $attendance->id,
        ]);
    }

    /**
     * 休憩機能：出勤していない場合は休憩できない
     */
    public function test_出勤していない場合は休憩できない()
    {
        $this->actingAs($this->testUser);

        // 今日の勤怠データを削除
        Attendance::where('user_id', $this->testUser->id)
            ->whereDate('date', Carbon::today())
            ->delete();

        $response = $this->post(route('break.start'));

        $response->assertRedirect();
        $response->assertSessionHas('error', '出勤打刻がされていません');
    }

    /**
     * 休憩機能：既に休憩中の場合は再度休憩できない
     */
    public function test_既に休憩中の場合は再度休憩できない()
    {
        $this->actingAs($this->testUser);

        // 出勤済み・休憩中のデータを作成
        Attendance::where('user_id', $this->testUser->id)
            ->whereDate('date', Carbon::today())
            ->delete();

        $attendance = Attendance::create([
            'user_id' => $this->testUser->id,
            'date' => Carbon::today(),
            'start_time' => Carbon::now()->subHours(2),
            'end_time' => null,
        ]);

        Breaktime::where('attendance_id', $attendance->id)->delete();
        Breaktime::create([
            'user_id' => $this->testUser->id,
            'attendance_id' => $attendance->id,
            'start_time' => Carbon::now()->subMinutes(30),
            'end_time' => null,
        ]);

        $response = $this->post(route('break.start'));

        $response->assertRedirect();
        $response->assertSessionHas('error', 'すでに休憩中です');
    }

    /**
     * 休憩戻機能：休憩中から休憩戻できる
     */
    public function test_休憩中状態から休憩戻できる()
    {
        $this->actingAs($this->testUser);

        // 休憩中のデータを作成
        Attendance::where('user_id', $this->testUser->id)
            ->whereDate('date', Carbon::today())
            ->delete();

        $attendance = Attendance::create([
            'user_id' => $this->testUser->id,
            'date' => Carbon::today(),
            'start_time' => Carbon::now()->subHours(3),
            'end_time' => null,
        ]);

        Breaktime::where('attendance_id', $attendance->id)->delete();
        $breaktime = Breaktime::create([
            'user_id' => $this->testUser->id,
            'attendance_id' => $attendance->id,
            'start_time' => Carbon::now()->subMinutes(30),
            'end_time' => null,
        ]);

        $response = $this->post(route('break.end'));

        $response->assertRedirect();
        $response->assertSessionHas('success', '休憩終了');

        // end_timeが記録されていることを確認
        $this->assertNotNull($breaktime->fresh()->end_time);
    }

    /**
     * 休憩戻機能：休憩していない場合は休憩戻できない
     */
    public function test_休憩していない場合は休憩戻できない()
    {
        $this->actingAs($this->testUser);

        // 出勤中のデータを作成（休憩なし）
        Attendance::where('user_id', $this->testUser->id)
            ->whereDate('date', Carbon::today())
            ->delete();

        $attendance = Attendance::create([
            'user_id' => $this->testUser->id,
            'date' => Carbon::today(),
            'start_time' => Carbon::now()->subHours(2),
            'end_time' => null,
        ]);

        Breaktime::where('attendance_id', $attendance->id)->delete();

        $response = $this->post(route('break.end'));

        $response->assertRedirect();
        $response->assertSessionHas('error', '休憩開始打刻がされていません');
    }

    /**
     * 退勤機能：出勤中から退勤できる
     */
    public function test_出勤中状態から退勤できる()
    {
        $this->actingAs($this->testUser);

        // 出勤済みのデータを作成
        Attendance::where('user_id', $this->testUser->id)
            ->whereDate('date', Carbon::today())
            ->delete();

        $attendance = Attendance::create([
            'user_id' => $this->testUser->id,
            'date' => Carbon::today(),
            'start_time' => Carbon::now()->subHours(8),
            'end_time' => null,
        ]);

        $response = $this->post(route('attendance.end'));

        $response->assertRedirect();
        $response->assertSessionHas('success', '退勤打刻が完了しました');

        // end_timeが記録されていることを確認
        $this->assertNotNull($attendance->fresh()->end_time);
    }

    /**
     * 退勤機能：出勤していない場合は退勤できない
     */
    public function test_出勤していない場合は退勤できない()
    {
        $this->actingAs($this->testUser);

        // 今日の勤怠データを削除
        Attendance::where('user_id', $this->testUser->id)
            ->whereDate('date', Carbon::today())
            ->delete();

        $response = $this->post(route('attendance.end'));

        $response->assertRedirect();
        $response->assertSessionHas('error', '出勤打刻がされていません');
    }

    /**
     * 退勤機能：休憩中は退勤できない
     */
    public function test_休憩中は退勤できない()
    {
        $this->actingAs($this->testUser);

        // 休憩中のデータを作成
        Attendance::where('user_id', $this->testUser->id)
            ->whereDate('date', Carbon::today())
            ->delete();

        $attendance = Attendance::create([
            'user_id' => $this->testUser->id,
            'date' => Carbon::today(),
            'start_time' => Carbon::now()->subHours(4),
            'end_time' => null,
        ]);

        Breaktime::where('attendance_id', $attendance->id)->delete();
        Breaktime::create([
            'user_id' => $this->testUser->id,
            'attendance_id' => $attendance->id,
            'start_time' => Carbon::now()->subMinutes(10),
            'end_time' => null,
        ]);

        $response = $this->post(route('attendance.end'));

        $response->assertRedirect();
        $response->assertSessionHas('error', '休憩中です。先に休憩を終了してください');
    }

    /**
     * 休憩機能：複数回休憩できる
     */
    public function test_休憩は複数回できる()
    {
        $this->actingAs($this->testUser);

        // 出勤済みのデータを作成
        Attendance::where('user_id', $this->testUser->id)
            ->whereDate('date', Carbon::today())
            ->delete();

        $attendance = Attendance::create([
            'user_id' => $this->testUser->id,
            'date' => Carbon::today(),
            'start_time' => Carbon::now()->subHours(5),
            'end_time' => null,
        ]);

        Breaktime::where('attendance_id', $attendance->id)->delete();

        // 1回目の休憩（終了済み）
        Breaktime::create([
            'user_id' => $this->testUser->id,
            'attendance_id' => $attendance->id,
            'start_time' => Carbon::now()->subHours(2),
            'end_time' => Carbon::now()->subHours(1)->subMinutes(30),
        ]);

        // 2回目の休憩開始
        $response = $this->post(route('break.start'));

        $response->assertRedirect();
        $response->assertSessionHas('success', '休憩開始');

        // 休憩記録が2件あることを確認
        $this->assertEquals(2, Breaktime::where('attendance_id', $attendance->id)->count());
    }
}
