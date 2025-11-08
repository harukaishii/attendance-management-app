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

class AttendanceDetailTest extends TestCase
{
    use DatabaseTransactions;

    private $testUser;

    protected function setUp(): void
    {
        parent::setUp();

        // テスト専用のユーザーを作成
        $this->testUser = User::create([
            'name' => 'テスト太郎',
            'email' => 'test_' . uniqid() . '@example.com',
            'email_verified_at' => now(),
            'password' => Hash::make('password'),
            'is_admin' => 0,
        ]);
    }

    /**
     * 未認証ユーザーはアクセスできない
     */
    public function test_未認証ユーザーは勤怠詳細画面にアクセスできない()
    {
        $attendance = Attendance::create([
            'user_id' => $this->testUser->id,
            'date' => Carbon::today(),
            'start_time' => Carbon::now()->setTime(9, 0, 0),
            'end_time' => Carbon::now()->setTime(18, 0, 0),
            'status' => AttendanceStatus::Approved,
        ]);

        $response = $this->get(route('attendance.detail.show', ['id' => $attendance->id]));
        $response->assertRedirect(route('login'));
    }

    /**
     * 認証済みユーザーは勤怠詳細画面にアクセスできる
     */
    public function test_認証済みユーザーは勤怠詳細画面にアクセスできる()
    {
        $this->actingAs($this->testUser);

        $attendance = Attendance::create([
            'user_id' => $this->testUser->id,
            'date' => Carbon::today(),
            'start_time' => Carbon::now()->setTime(9, 0, 0),
            'end_time' => Carbon::now()->setTime(18, 0, 0),
            'status' => AttendanceStatus::Approved,
        ]);

        $response = $this->get(route('attendance.detail.show', ['id' => $attendance->id]));

        $response->assertStatus(200);
        $response->assertViewIs('user.attendance_detail');
    }

    /**
     * 詳細画面にログインユーザーの名前が表示される
     */
    public function test_詳細画面にログインユーザーの名前が表示される()
    {
        $this->actingAs($this->testUser);

        $attendance = Attendance::create([
            'user_id' => $this->testUser->id,
            'date' => Carbon::today(),
            'start_time' => Carbon::now()->setTime(9, 0, 0),
            'end_time' => Carbon::now()->setTime(18, 0, 0),
            'status' => AttendanceStatus::Approved,
        ]);

        $response = $this->get(route('attendance.detail.show', ['id' => $attendance->id]));

        $response->assertStatus(200);
        $response->assertSee('テスト太郎');
    }

    /**
     * 詳細画面に選択した日付が表示される
     */
    public function test_詳細画面に選択した日付が表示される()
    {
        $this->actingAs($this->testUser);

        $targetDate = Carbon::create(2025, 11, 15);

        $attendance = Attendance::create([
            'user_id' => $this->testUser->id,
            'date' => $targetDate,
            'start_time' => $targetDate->copy()->setTime(9, 0, 0),
            'end_time' => $targetDate->copy()->setTime(18, 0, 0),
            'status' => AttendanceStatus::Approved,
        ]);

        $response = $this->get(route('attendance.detail.show', ['id' => $attendance->id]));

        $response->assertStatus(200);
        $response->assertViewHas('attendance');

        $viewAttendance = $response->viewData('attendance');
        $this->assertEquals($targetDate->format('Y-m-d'), Carbon::parse($viewAttendance->date)->format('Y-m-d'));
    }

    /**
     * 出勤・退勤時間がログインユーザーの打刻と一致している
     */
    public function test_出勤退勤時間が打刻と一致している()
    {
        $this->actingAs($this->testUser);

        $startTime = Carbon::now()->setTime(9, 30, 0);
        $endTime = Carbon::now()->setTime(18, 15, 0);

        $attendance = Attendance::create([
            'user_id' => $this->testUser->id,
            'date' => Carbon::today(),
            'start_time' => $startTime,
            'end_time' => $endTime,
            'status' => AttendanceStatus::Approved,
        ]);

        $response = $this->get(route('attendance.detail.show', ['id' => $attendance->id]));

        $response->assertStatus(200);

        $viewAttendance = $response->viewData('attendance');
        $this->assertEquals($startTime->format('H:i:s'), Carbon::parse($viewAttendance->start_time)->format('H:i:s'));
        $this->assertEquals($endTime->format('H:i:s'), Carbon::parse($viewAttendance->end_time)->format('H:i:s'));
    }

    /**
     * 休憩時間がログインユーザーの打刻と一致している
     */
    public function test_休憩時間が打刻と一致している()
    {
        $this->actingAs($this->testUser);

        $attendance = Attendance::create([
            'user_id' => $this->testUser->id,
            'date' => Carbon::today(),
            'start_time' => Carbon::now()->setTime(9, 0, 0),
            'end_time' => Carbon::now()->setTime(18, 0, 0),
            'status' => AttendanceStatus::Approved,
        ]);

        $breakStart = Carbon::now()->setTime(12, 0, 0);
        $breakEnd = Carbon::now()->setTime(13, 0, 0);

        Breaktime::create([
            'user_id' => $this->testUser->id,
            'attendance_id' => $attendance->id,
            'start_time' => $breakStart,
            'end_time' => $breakEnd,
        ]);

        $response = $this->get(route('attendance.detail.show', ['id' => $attendance->id]));

        $response->assertStatus(200);

        $breaktimes = $response->viewData('breaktimes');
        $this->assertEquals(1, $breaktimes->count());
        $this->assertEquals($breakStart->format('H:i:s'), Carbon::parse($breaktimes->first()->start_time)->format('H:i:s'));
        $this->assertEquals($breakEnd->format('H:i:s'), Carbon::parse($breaktimes->first()->end_time)->format('H:i:s'));
    }

    /**
     * 他人の勤怠詳細は閲覧できない
     */
    public function test_他人の勤怠詳細は閲覧できない()
    {
        $this->actingAs($this->testUser);

        // 他のユーザーを作成
        $otherUser = User::create([
            'name' => '他のユーザー',
            'email' => 'other_' . uniqid() . '@example.com',
            'email_verified_at' => now(),
            'password' => Hash::make('password'),
            'is_admin' => 0,
        ]);

        // 他人の勤怠データを作成
        $otherAttendance = Attendance::create([
            'user_id' => $otherUser->id,
            'date' => Carbon::today(),
            'start_time' => Carbon::now()->setTime(9, 0, 0),
            'end_time' => Carbon::now()->setTime(18, 0, 0),
            'status' => AttendanceStatus::Approved,
        ]);

        // 他人の詳細画面にアクセス
        try {
            $response = $this->get(route('attendance.detail.show', ['id' => $otherAttendance->id]));

            // 例外が投げられなかった場合、403, 404, またはリダイレクトを期待
            $this->assertTrue(
                $response->status() === 403 ||
                    $response->status() === 404 ||
                    $response->status() === 302
            );
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            // ModelNotFoundException が投げられた場合もOK
            $this->assertTrue(true);
        }
    }

    /**
     * 出勤時間が退勤時間より後の場合エラー
     */
    public function test_出勤時間が退勤時間より後の場合エラーになる()
    {
        $this->actingAs($this->testUser);

        $attendance = Attendance::create([
            'user_id' => $this->testUser->id,
            'date' => Carbon::today(),
            'start_time' => Carbon::now()->setTime(9, 0, 0),
            'end_time' => Carbon::now()->setTime(18, 0, 0),
            'status' => AttendanceStatus::Approved,
        ]);

        $response = $this->post(route('attendance.detail.update', ['id' => $attendance->id]), [
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
        $this->actingAs($this->testUser);

        $attendance = Attendance::create([
            'user_id' => $this->testUser->id,
            'date' => Carbon::today(),
            'start_time' => Carbon::now()->setTime(9, 0, 0),
            'end_time' => Carbon::now()->setTime(18, 0, 0),
            'status' => AttendanceStatus::Approved,
        ]);

        $response = $this->post(route('attendance.detail.update', ['id' => $attendance->id]), [
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
        $this->actingAs($this->testUser);

        $attendance = Attendance::create([
            'user_id' => $this->testUser->id,
            'date' => Carbon::today(),
            'start_time' => Carbon::now()->setTime(9, 0, 0),
            'end_time' => Carbon::now()->setTime(18, 0, 0),
            'status' => AttendanceStatus::Approved,
        ]);

        $response = $this->post(route('attendance.detail.update', ['id' => $attendance->id]), [
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
        $this->actingAs($this->testUser);

        $attendance = Attendance::create([
            'user_id' => $this->testUser->id,
            'date' => Carbon::today(),
            'start_time' => Carbon::now()->setTime(9, 0, 0),
            'end_time' => Carbon::now()->setTime(18, 0, 0),
            'status' => AttendanceStatus::Approved,
        ]);

        $response = $this->post(route('attendance.detail.update', ['id' => $attendance->id]), [
            'start_time' => '09:00',
            'end_time' => '18:00',
            'note' => '',  // 備考なし
        ]);

        $response->assertSessionHasErrors(['note']);
    }

    /**
     * 修正申請処理が正常に実行される
     */
    public function test_修正申請処理が正常に実行される()
    {
        $this->actingAs($this->testUser);

        $attendance = Attendance::create([
            'user_id' => $this->testUser->id,
            'date' => Carbon::today(),
            'start_time' => Carbon::now()->setTime(9, 0, 0),
            'end_time' => Carbon::now()->setTime(18, 0, 0),
            'status' => AttendanceStatus::Approved,
        ]);

        $response = $this->post(route('attendance.detail.update', ['id' => $attendance->id]), [
            'start_time' => '09:30',
            'end_time' => '18:30',
            'breaks' => [
                ['start' => '12:00', 'end' => '13:00'],
            ],
            'note' => '遅刻のため修正します',
        ]);

        $response->assertRedirect(route('attendance.list.index'));
        $response->assertSessionHas('success');

        // ステータスが承認待ちになっているか確認
        $attendance->refresh();
        $this->assertEquals(AttendanceStatus::Unapproved->value, $attendance->status->value);
    }

    /**
     * 承認待ちの勤怠は編集できない（isEditable = false）
     */
    public function test_承認待ちの勤怠は編集できない()
    {
        $this->actingAs($this->testUser);

        $attendance = Attendance::create([
            'user_id' => $this->testUser->id,
            'date' => Carbon::today(),
            'start_time' => Carbon::now()->setTime(9, 0, 0),
            'end_time' => Carbon::now()->setTime(18, 0, 0),
            'status' => AttendanceStatus::Unapproved,  // 承認待ち
        ]);

        $response = $this->get(route('attendance.detail.show', ['id' => $attendance->id]));

        $response->assertStatus(200);

        // isEditable が false であることを確認
        $isEditable = $response->viewData('isEditable');
        $this->assertFalse($isEditable);
    }

    /**
     * 承認待ちの勤怠は更新できない
     */
    public function test_承認待ちの勤怠は更新できない()
    {
        $this->actingAs($this->testUser);

        $attendance = Attendance::create([
            'user_id' => $this->testUser->id,
            'date' => Carbon::today(),
            'start_time' => Carbon::now()->setTime(9, 0, 0),
            'end_time' => Carbon::now()->setTime(18, 0, 0),
            'status' => AttendanceStatus::Unapproved,  // 承認待ち
        ]);

        $response = $this->post(route('attendance.detail.update', ['id' => $attendance->id]), [
            'start_time' => '09:30',
            'end_time' => '18:30',
            'note' => '修正します',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('error', '承認待ちのため修正できません。');
    }

    /**
     * 承認済みの勤怠は編集できる（isEditable = true）
     */
    public function test_承認済みの勤怠は編集できる()
    {
        $this->actingAs($this->testUser);

        $attendance = Attendance::create([
            'user_id' => $this->testUser->id,
            'date' => Carbon::today(),
            'start_time' => Carbon::now()->setTime(9, 0, 0),
            'end_time' => Carbon::now()->setTime(18, 0, 0),
            'status' => AttendanceStatus::Approved,  // 承認済み
        ]);

        $response = $this->get(route('attendance.detail.show', ['id' => $attendance->id]));

        $response->assertStatus(200);

        // isEditable が true であることを確認
        $isEditable = $response->viewData('isEditable');
        $this->assertTrue($isEditable);
    }

    /**
     * 休憩データが開始時間順にソートされる
     */
    public function test_休憩データが開始時間順にソートされる()
    {
        $this->actingAs($this->testUser);

        $attendance = Attendance::create([
            'user_id' => $this->testUser->id,
            'date' => Carbon::today(),
            'start_time' => Carbon::now()->setTime(9, 0, 0),
            'end_time' => Carbon::now()->setTime(18, 0, 0),
            'status' => AttendanceStatus::Approved,
        ]);

        // 逆順で作成
        Breaktime::create([
            'user_id' => $this->testUser->id,
            'attendance_id' => $attendance->id,
            'start_time' => Carbon::now()->setTime(15, 0, 0),
            'end_time' => Carbon::now()->setTime(15, 15, 0),
        ]);

        Breaktime::create([
            'user_id' => $this->testUser->id,
            'attendance_id' => $attendance->id,
            'start_time' => Carbon::now()->setTime(12, 0, 0),
            'end_time' => Carbon::now()->setTime(13, 0, 0),
        ]);

        $response = $this->get(route('attendance.detail.show', ['id' => $attendance->id]));

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
        $this->actingAs($this->testUser);

        $attendance = Attendance::create([
            'user_id' => $this->testUser->id,
            'date' => Carbon::today(),
            'start_time' => Carbon::now()->setTime(9, 0, 0),
            'end_time' => Carbon::now()->setTime(18, 0, 0),
            'status' => AttendanceStatus::Approved,
        ]);

        $noteText = '電車遅延のため遅刻しました';

        $response = $this->post(route('attendance.detail.update', ['id' => $attendance->id]), [
            'start_time' => '09:30',
            'end_time' => '18:00',
            'note' => $noteText,
        ]);

        $response->assertRedirect(route('attendance.list.index'));

        $attendance->refresh();
        $this->assertEquals($noteText, $attendance->note);
    }
}
