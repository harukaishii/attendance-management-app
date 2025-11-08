<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use App\Models\User;
use App\Models\Attendance;
use App\Models\Breaktime;
use Carbon\Carbon;
use Illuminate\Support\Facades\Hash;
use App\Enums\AttendanceStatus;

class AdminAttendanceDetailTest extends TestCase
{
    use DatabaseTransactions;

    private $adminUser;
    private $generalUser;

    protected function setUp(): void
    {
        parent::setUp();

        // 管理者ユーザーを作成
        $this->adminUser = User::create([
            'name' => '管理者',
            'email' => 'admin_' . uniqid() . '@example.com',
            'email_verified_at' => now(),
            'password' => Hash::make('password'),
            'is_admin' => 1,
        ]);

        // 一般ユーザーを作成
        $this->generalUser = User::create([
            'name' => '一般ユーザー',
            'email' => 'user_' . uniqid() . '@example.com',
            'email_verified_at' => now(),
            'password' => Hash::make('password'),
            'is_admin' => 0,
        ]);
    }

    /**
     * 未認証ユーザーはアクセスできない
     */
    public function test_未認証ユーザーは管理者勤怠詳細にアクセスできない()
    {
        $attendance = Attendance::create([
            'user_id' => $this->generalUser->id,
            'date' => Carbon::today(),
            'start_time' => Carbon::now()->setTime(9, 0, 0),
            'end_time' => Carbon::now()->setTime(18, 0, 0),
            'status' => AttendanceStatus::Approved,
        ]);

        $response = $this->get(route('admin_attendance_detail.show', ['id' => $attendance->id]));
        $response->assertRedirect(route('admin.login'));
    }

    /**
     * 一般ユーザーはアクセスできない
     */
    public function test_一般ユーザーは管理者勤怠詳細にアクセスできない()
    {
        $this->actingAs($this->generalUser);

        $attendance = Attendance::create([
            'user_id' => $this->generalUser->id,
            'date' => Carbon::today(),
            'start_time' => Carbon::now()->setTime(9, 0, 0),
            'end_time' => Carbon::now()->setTime(18, 0, 0),
            'status' => AttendanceStatus::Approved,
        ]);

        $response = $this->get(route('admin_attendance_detail.show', ['id' => $attendance->id]));

        // アクセス拒否またはリダイレクト
        $this->assertTrue(
            $response->status() === 403 ||
                $response->status() === 302
        );
    }

    /**
     * 管理者ユーザーはアクセスできる
     */
    public function test_管理者ユーザーは勤怠詳細にアクセスできる()
    {
        $this->actingAs($this->adminUser);

        $attendance = Attendance::create([
            'user_id' => $this->generalUser->id,
            'date' => Carbon::today(),
            'start_time' => Carbon::now()->setTime(9, 0, 0),
            'end_time' => Carbon::now()->setTime(18, 0, 0),
            'status' => AttendanceStatus::Approved,
        ]);

        $response = $this->get(route('admin_attendance_detail.show', ['id' => $attendance->id]));

        $response->assertStatus(200);
        $response->assertViewIs('admin.admin_attendance_detail');
    }

    /**
     * 詳細画面に選択した勤怠情報が表示される
     */
    public function test_詳細画面に選択した勤怠情報が表示される()
    {
        $this->actingAs($this->adminUser);

        $targetDate = Carbon::create(2025, 11, 15);
        $startTime = Carbon::create(2025, 11, 15, 9, 30, 0);
        $endTime = Carbon::create(2025, 11, 15, 18, 15, 0);

        $attendance = Attendance::create([
            'user_id' => $this->generalUser->id,
            'date' => $targetDate,
            'start_time' => $startTime,
            'end_time' => $endTime,
            'status' => AttendanceStatus::Approved,
            'note' => 'テスト備考',
        ]);

        $response = $this->get(route('admin_attendance_detail.show', ['id' => $attendance->id]));

        $response->assertStatus(200);

        // ビューに渡されたデータを確認
        $viewAttendance = $response->viewData('attendance');
        $this->assertEquals($targetDate->format('Y-m-d'), Carbon::parse($viewAttendance->date)->format('Y-m-d'));
        $this->assertEquals($startTime->format('H:i:s'), Carbon::parse($viewAttendance->start_time)->format('H:i:s'));
        $this->assertEquals($endTime->format('H:i:s'), Carbon::parse($viewAttendance->end_time)->format('H:i:s'));
        $this->assertEquals('テスト備考', $viewAttendance->note);
    }

    /**
     * ユーザー情報が表示される
     */
    public function test_ユーザー情報が表示される()
    {
        $this->actingAs($this->adminUser);

        $attendance = Attendance::create([
            'user_id' => $this->generalUser->id,
            'date' => Carbon::today(),
            'start_time' => Carbon::now()->setTime(9, 0, 0),
            'end_time' => Carbon::now()->setTime(18, 0, 0),
            'status' => AttendanceStatus::Approved,
        ]);

        $response = $this->get(route('admin_attendance_detail.show', ['id' => $attendance->id]));

        $response->assertStatus(200);

        $user = $response->viewData('user');
        $this->assertEquals($this->generalUser->id, $user->id);
        $this->assertEquals($this->generalUser->name, $user->name);

        // ビューにユーザー名が表示される
        $response->assertSee($this->generalUser->name);
    }

    /**
     * 休憩時間が表示される
     */
    public function test_休憩時間が表示される()
    {
        $this->actingAs($this->adminUser);

        $attendance = Attendance::create([
            'user_id' => $this->generalUser->id,
            'date' => Carbon::today(),
            'start_time' => Carbon::now()->setTime(9, 0, 0),
            'end_time' => Carbon::now()->setTime(18, 0, 0),
            'status' => AttendanceStatus::Approved,
        ]);

        $breakStart = Carbon::now()->setTime(12, 0, 0);
        $breakEnd = Carbon::now()->setTime(13, 0, 0);

        Breaktime::create([
            'user_id' => $this->generalUser->id,
            'attendance_id' => $attendance->id,
            'start_time' => $breakStart,
            'end_time' => $breakEnd,
        ]);

        $response = $this->get(route('admin_attendance_detail.show', ['id' => $attendance->id]));

        $response->assertStatus(200);

        $breaktimes = $response->viewData('breaktimes');
        $this->assertEquals(1, $breaktimes->count());
        $this->assertEquals($breakStart->format('H:i:s'), Carbon::parse($breaktimes->first()->start_time)->format('H:i:s'));
        $this->assertEquals($breakEnd->format('H:i:s'), Carbon::parse($breaktimes->first()->end_time)->format('H:i:s'));
    }

    /**
     * 出勤時間が退勤時間より後の場合エラー
     */
    public function test_出勤時間が退勤時間より後の場合エラーになる()
    {
        $this->actingAs($this->adminUser);

        $attendance = Attendance::create([
            'user_id' => $this->generalUser->id,
            'date' => Carbon::today(),
            'start_time' => Carbon::now()->setTime(9, 0, 0),
            'end_time' => Carbon::now()->setTime(18, 0, 0),
            'status' => AttendanceStatus::Approved,
        ]);

        $response = $this->post(route('admin_attendance_detail.update', ['id' => $attendance->id]), [
            'start_time' => '18:00',
            'end_time' => '09:00',  // 出勤より前
            'note' => 'テスト備考',
        ]);

        $response->assertSessionHasErrors();
    }

    /**
     * 休憩開始時間が退勤時間より後の場合エラー
     */
    public function test_休憩開始時間が退勤時間より後の場合エラーになる()
    {
        $this->actingAs($this->adminUser);

        $attendance = Attendance::create([
            'user_id' => $this->generalUser->id,
            'date' => Carbon::today(),
            'start_time' => Carbon::now()->setTime(9, 0, 0),
            'end_time' => Carbon::now()->setTime(18, 0, 0),
            'status' => AttendanceStatus::Approved,
        ]);

        $response = $this->post(route('admin_attendance_detail.update', ['id' => $attendance->id]), [
            'start_time' => '09:00',
            'end_time' => '18:00',
            'breaks' => [
                ['start' => '19:00', 'end' => '20:00'],  // 退勤より後
            ],
            'note' => 'テスト備考',
        ]);

        $response->assertSessionHasErrors();
    }

    /**
     * 休憩終了時間が退勤時間より後の場合エラー
     */
    public function test_休憩終了時間が退勤時間より後の場合エラーになる()
    {
        $this->actingAs($this->adminUser);

        $attendance = Attendance::create([
            'user_id' => $this->generalUser->id,
            'date' => Carbon::today(),
            'start_time' => Carbon::now()->setTime(9, 0, 0),
            'end_time' => Carbon::now()->setTime(18, 0, 0),
            'status' => AttendanceStatus::Approved,
        ]);

        $response = $this->post(route('admin_attendance_detail.update', ['id' => $attendance->id]), [
            'start_time' => '09:00',
            'end_time' => '18:00',
            'breaks' => [
                ['start' => '12:00', 'end' => '19:00'],  // 終了が退勤より後
            ],
            'note' => 'テスト備考',
        ]);

        $response->assertSessionHasErrors();
    }

    /**
     * 備考欄が未入力の場合エラー
     */
    public function test_備考欄が未入力の場合エラーになる()
    {
        $this->actingAs($this->adminUser);

        $attendance = Attendance::create([
            'user_id' => $this->generalUser->id,
            'date' => Carbon::today(),
            'start_time' => Carbon::now()->setTime(9, 0, 0),
            'end_time' => Carbon::now()->setTime(18, 0, 0),
            'status' => AttendanceStatus::Approved,
        ]);

        $response = $this->post(route('admin_attendance_detail.update', ['id' => $attendance->id]), [
            'start_time' => '09:00',
            'end_time' => '18:00',
            'note' => '',  // 備考なし
        ]);

        $response->assertSessionHasErrors(['note']);
    }

    /**
     * 修正処理が正常に実行される
     */
    public function test_修正処理が正常に実行される()
    {
        $this->actingAs($this->adminUser);

        $attendance = Attendance::create([
            'user_id' => $this->generalUser->id,
            'date' => Carbon::today(),
            'start_time' => Carbon::now()->setTime(9, 0, 0),
            'end_time' => Carbon::now()->setTime(18, 0, 0),
            'status' => AttendanceStatus::Approved,
        ]);

        $response = $this->post(route('admin_attendance_detail.update', ['id' => $attendance->id]), [
            'start_time' => '09:30',
            'end_time' => '18:30',
            'breaks' => [
                ['start' => '12:00', 'end' => '13:00'],
            ],
            'note' => '管理者による修正',
        ]);

        $response->assertRedirect(route('admin_attendance_list.index', ['date' => Carbon::today()->format('Y-m-d')]));
        $response->assertSessionHas('success', '勤怠情報を修正しました');

        // ステータスが承認待ちになっているか確認
        $attendance->refresh();
        $this->assertEquals(AttendanceStatus::Unapproved->value, $attendance->status->value);
    }

    /**
     * 管理者は他のユーザーの勤怠も編集できる
     */
    public function test_管理者は他のユーザーの勤怠も編集できる()
    {
        $this->actingAs($this->adminUser);

        // 別のユーザーを作成
        $anotherUser = User::create([
            'name' => '別のユーザー',
            'email' => 'another_' . uniqid() . '@example.com',
            'email_verified_at' => now(),
            'password' => Hash::make('password'),
            'is_admin' => 0,
        ]);

        $attendance = Attendance::create([
            'user_id' => $anotherUser->id,
            'date' => Carbon::today(),
            'start_time' => Carbon::now()->setTime(9, 0, 0),
            'end_time' => Carbon::now()->setTime(18, 0, 0),
            'status' => AttendanceStatus::Approved,
        ]);

        $response = $this->post(route('admin_attendance_detail.update', ['id' => $attendance->id]), [
            'start_time' => '09:30',
            'end_time' => '18:00',
            'note' => '管理者による修正',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $attendance->refresh();
        $this->assertEquals('09:30:00', Carbon::parse($attendance->start_time)->format('H:i:s'));
    }

    /**
     * 承認待ちの勤怠も編集できる（管理者は制限なし）
     */
    public function test_管理者は承認待ちの勤怠も編集できる()
    {
        $this->actingAs($this->adminUser);

        $attendance = Attendance::create([
            'user_id' => $this->generalUser->id,
            'date' => Carbon::today(),
            'start_time' => Carbon::now()->setTime(9, 0, 0),
            'end_time' => Carbon::now()->setTime(18, 0, 0),
            'status' => AttendanceStatus::Unapproved,  // 承認待ち
        ]);

        $response = $this->post(route('admin_attendance_detail.update', ['id' => $attendance->id]), [
            'start_time' => '09:30',
            'end_time' => '18:00',
            'note' => '管理者による修正',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');
    }

    /**
     * 休憩データが開始時間順にソートされる
     */
    public function test_休憩データが開始時間順にソートされる()
    {
        $this->actingAs($this->adminUser);

        $attendance = Attendance::create([
            'user_id' => $this->generalUser->id,
            'date' => Carbon::today(),
            'start_time' => Carbon::now()->setTime(9, 0, 0),
            'end_time' => Carbon::now()->setTime(18, 0, 0),
            'status' => AttendanceStatus::Approved,
        ]);

        // 逆順で作成
        Breaktime::create([
            'user_id' => $this->generalUser->id,
            'attendance_id' => $attendance->id,
            'start_time' => Carbon::now()->setTime(15, 0, 0),
            'end_time' => Carbon::now()->setTime(15, 15, 0),
        ]);

        Breaktime::create([
            'user_id' => $this->generalUser->id,
            'attendance_id' => $attendance->id,
            'start_time' => Carbon::now()->setTime(12, 0, 0),
            'end_time' => Carbon::now()->setTime(13, 0, 0),
        ]);

        $response = $this->get(route('admin_attendance_detail.show', ['id' => $attendance->id]));

        $response->assertStatus(200);

        $breaktimes = $response->viewData('breaktimes');
        $this->assertEquals(2, $breaktimes->count());

        // 最初が12:00、2番目が15:00であることを確認
        $this->assertEquals('12:00:00', Carbon::parse($breaktimes[0]->start_time)->format('H:i:s'));
        $this->assertEquals('15:00:00', Carbon::parse($breaktimes[1]->start_time)->format('H:i:s'));
    }

    /**
     * 備考が保存される
     */
    public function test_備考が保存される()
    {
        $this->actingAs($this->adminUser);

        $attendance = Attendance::create([
            'user_id' => $this->generalUser->id,
            'date' => Carbon::today(),
            'start_time' => Carbon::now()->setTime(9, 0, 0),
            'end_time' => Carbon::now()->setTime(18, 0, 0),
            'status' => AttendanceStatus::Approved,
        ]);

        $noteText = '管理者による時刻修正';

        $response = $this->post(route('admin_attendance_detail.update', ['id' => $attendance->id]), [
            'start_time' => '09:30',
            'end_time' => '18:00',
            'note' => $noteText,
        ]);

        $response->assertRedirect();

        $attendance->refresh();
        $this->assertEquals($noteText, $attendance->note);
    }

    /**
     * 元の日付の勤怠一覧にリダイレクトされる
     */
    public function test_更新後に元の日付の勤怠一覧にリダイレクトされる()
    {
        $this->actingAs($this->adminUser);

        $targetDate = Carbon::create(2025, 11, 15);

        $attendance = Attendance::create([
            'user_id' => $this->generalUser->id,
            'date' => $targetDate,
            'start_time' => $targetDate->copy()->setTime(9, 0, 0),
            'end_time' => $targetDate->copy()->setTime(18, 0, 0),
            'status' => AttendanceStatus::Approved,
        ]);

        $response = $this->post(route('admin_attendance_detail.update', ['id' => $attendance->id]), [
            'start_time' => '09:30',
            'end_time' => '18:00',
            'note' => '修正',
        ]);

        // 該当日付の勤怠一覧にリダイレクト
        $response->assertRedirect(route('admin_attendance_list.index', ['date' => '2025-11-15']));
    }
}
