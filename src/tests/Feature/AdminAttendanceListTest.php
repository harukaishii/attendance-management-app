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

class AdminAttendanceListTest extends TestCase
{
    use DatabaseTransactions;

    private $adminUser;
    private $generalUser1;
    private $generalUser2;

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

        // 一般ユーザー1を作成
        $this->generalUser1 = User::create([
            'name' => '山田太郎',
            'email' => 'user1_' . uniqid() . '@example.com',
            'email_verified_at' => now(),
            'password' => Hash::make('password'),
            'is_admin' => 0,
        ]);

        // 一般ユーザー2を作成
        $this->generalUser2 = User::create([
            'name' => '佐藤花子',
            'email' => 'user2_' . uniqid() . '@example.com',
            'email_verified_at' => now(),
            'password' => Hash::make('password'),
            'is_admin' => 0,
        ]);
    }

    /**
     * 未認証ユーザーはアクセスできない
     */
    public function test_未認証ユーザーは管理者勤怠一覧にアクセスできない()
    {
        $response = $this->get(route('admin_attendance_list.index'));
        $response->assertRedirect(route('admin.login'));
    }

    /**
     * 一般ユーザーはアクセスできない
     */
    public function test_一般ユーザーは管理者勤怠一覧にアクセスできない()
    {
        $this->actingAs($this->generalUser1);

        $response = $this->get(route('admin_attendance_list.index'));

        // アクセス拒否またはリダイレクト
        $this->assertTrue(
            $response->status() === 403 ||
                $response->status() === 302
        );
    }

    /**
     * 管理者ユーザーはアクセスできる
     */
    public function test_管理者ユーザーは勤怠一覧にアクセスできる()
    {
        $this->actingAs($this->adminUser);

        $response = $this->get(route('admin_attendance_list.index'));

        $response->assertStatus(200);
        $response->assertViewIs('admin.admin_attendance_list');
    }

    /**
     * 現在の日付が表示される
     */
    public function test_遷移時に現在の日付が表示される()
    {
        $this->actingAs($this->adminUser);

        $response = $this->get(route('admin_attendance_list.index'));

        $response->assertStatus(200);
        $response->assertViewHas('today');

        $today = $response->viewData('today');
        $this->assertEquals(Carbon::now()->format('Y-m-d'), $today);
    }

    /**
     * 全ユーザーの勤怠情報が表示される
     */
    public function test_全ユーザーの勤怠情報が表示される()
    {
        $this->actingAs($this->adminUser);

        // ユーザー1の勤怠データを作成
        Attendance::create([
            'user_id' => $this->generalUser1->id,
            'date' => Carbon::today(),
            'start_time' => Carbon::now()->setTime(9, 0, 0),
            'end_time' => Carbon::now()->setTime(18, 0, 0),
            'status' => AttendanceStatus::Approved,
        ]);

        // ユーザー2の勤怠データを作成
        Attendance::create([
            'user_id' => $this->generalUser2->id,
            'date' => Carbon::today(),
            'start_time' => Carbon::now()->setTime(9, 30, 0),
            'end_time' => Carbon::now()->setTime(18, 30, 0),
            'status' => AttendanceStatus::Approved,
        ]);

        $response = $this->get(route('admin_attendance_list.index'));

        $response->assertStatus(200);

        $attendanceData = $response->viewData('attendanceData');

        // 全ユーザー分のデータがある（管理者含む）
        $this->assertGreaterThanOrEqual(3, count($attendanceData));

        // ユーザー1のデータが含まれている
        $user1Data = collect($attendanceData)->firstWhere('user_id', $this->generalUser1->id);
        $this->assertNotNull($user1Data);
        $this->assertEquals('山田太郎', $user1Data['name']);

        // ユーザー2のデータが含まれている
        $user2Data = collect($attendanceData)->firstWhere('user_id', $this->generalUser2->id);
        $this->assertNotNull($user2Data);
        $this->assertEquals('佐藤花子', $user2Data['name']);
    }

    /**
     * 勤怠データがない場合もユーザー一覧は表示される
     */
    public function test_勤怠データがないユーザーも一覧に表示される()
    {
        $this->actingAs($this->adminUser);

        // ユーザー1だけ勤怠データを作成
        Attendance::create([
            'user_id' => $this->generalUser1->id,
            'date' => Carbon::today(),
            'start_time' => Carbon::now()->setTime(9, 0, 0),
            'end_time' => Carbon::now()->setTime(18, 0, 0),
            'status' => AttendanceStatus::Approved,
        ]);

        // ユーザー2は勤怠データなし

        $response = $this->get(route('admin_attendance_list.index'));

        $response->assertStatus(200);

        $attendanceData = $response->viewData('attendanceData');

        // ユーザー2もリストに含まれている（データはnull）
        $user2Data = collect($attendanceData)->firstWhere('user_id', $this->generalUser2->id);
        $this->assertNotNull($user2Data);
        $this->assertEquals('佐藤花子', $user2Data['name']);
        $this->assertNull($user2Data['start']);
        $this->assertNull($user2Data['end']);
    }

    /**
     * 出勤・退勤時刻が正確に表示される
     */
    public function test_出勤退勤時刻が正確に表示される()
    {
        $this->actingAs($this->adminUser);

        $startTime = Carbon::now()->setTime(9, 15, 0);
        $endTime = Carbon::now()->setTime(18, 45, 0);

        Attendance::create([
            'user_id' => $this->generalUser1->id,
            'date' => Carbon::today(),
            'start_time' => $startTime,
            'end_time' => $endTime,
            'status' => AttendanceStatus::Approved,
        ]);

        $response = $this->get(route('admin_attendance_list.index'));

        $response->assertStatus(200);

        $attendanceData = $response->viewData('attendanceData');
        $user1Data = collect($attendanceData)->firstWhere('user_id', $this->generalUser1->id);

        $this->assertEquals('09:15', $user1Data['start']);
        $this->assertEquals('18:45', $user1Data['end']);
    }

    /**
     * 休憩時間が正確に計算される
     */
    public function test_休憩時間が正確に計算される()
    {
        $this->actingAs($this->adminUser);

        $attendance = Attendance::create([
            'user_id' => $this->generalUser1->id,
            'date' => Carbon::today(),
            'start_time' => Carbon::now()->setTime(9, 0, 0),
            'end_time' => Carbon::now()->setTime(18, 0, 0),
            'status' => AttendanceStatus::Approved,
        ]);

        // 休憩1: 12:00-13:00 (1時間)
        Breaktime::create([
            'user_id' => $this->generalUser1->id,
            'attendance_id' => $attendance->id,
            'start_time' => Carbon::now()->setTime(12, 0, 0),
            'end_time' => Carbon::now()->setTime(13, 0, 0),
        ]);

        // 休憩2: 15:00-15:15 (15分)
        Breaktime::create([
            'user_id' => $this->generalUser1->id,
            'attendance_id' => $attendance->id,
            'start_time' => Carbon::now()->setTime(15, 0, 0),
            'end_time' => Carbon::now()->setTime(15, 15, 0),
        ]);

        $response = $this->get(route('admin_attendance_list.index'));

        $response->assertStatus(200);

        $attendanceData = $response->viewData('attendanceData');
        $user1Data = collect($attendanceData)->firstWhere('user_id', $this->generalUser1->id);

        // 合計1時間15分
        $this->assertEquals('1:15', $user1Data['break']);
    }

    /**
     * 合計勤務時間が正確に計算される
     */
    public function test_合計勤務時間が正確に計算される()
    {
        $this->actingAs($this->adminUser);

        $attendance = Attendance::create([
            'user_id' => $this->generalUser1->id,
            'date' => Carbon::today(),
            'start_time' => Carbon::now()->setTime(9, 0, 0),
            'end_time' => Carbon::now()->setTime(18, 0, 0),
            'status' => AttendanceStatus::Approved,
        ]);

        // 休憩: 12:00-13:00 (1時間)
        Breaktime::create([
            'user_id' => $this->generalUser1->id,
            'attendance_id' => $attendance->id,
            'start_time' => Carbon::now()->setTime(12, 0, 0),
            'end_time' => Carbon::now()->setTime(13, 0, 0),
        ]);

        $response = $this->get(route('admin_attendance_list.index'));

        $response->assertStatus(200);

        $attendanceData = $response->viewData('attendanceData');
        $user1Data = collect($attendanceData)->firstWhere('user_id', $this->generalUser1->id);

        // 9時間 - 1時間休憩 = 8時間
        $this->assertEquals('8:00', $user1Data['total']);
    }

    /**
     * 前日ボタンで前日のデータが表示される
     */
    public function test_前日ボタンで前日の勤怠データが表示される()
    {
        $this->actingAs($this->adminUser);

        $yesterday = Carbon::yesterday();

        // 昨日の勤怠データを作成
        Attendance::create([
            'user_id' => $this->generalUser1->id,
            'date' => $yesterday,
            'start_time' => $yesterday->copy()->setTime(9, 0, 0),
            'end_time' => $yesterday->copy()->setTime(18, 0, 0),
            'status' => AttendanceStatus::Approved,
        ]);

        // 今日の勤怠データを作成
        Attendance::create([
            'user_id' => $this->generalUser1->id,
            'date' => Carbon::today(),
            'start_time' => Carbon::now()->setTime(9, 0, 0),
            'end_time' => Carbon::now()->setTime(18, 0, 0),
            'status' => AttendanceStatus::Approved,
        ]);

        // 前日を表示
        $response = $this->get(route('admin_attendance_list.index', ['date' => $yesterday->format('Y-m-d')]));

        $response->assertStatus(200);

        $today = $response->viewData('today');
        $this->assertEquals($yesterday->format('Y-m-d'), $today);
    }

    /**
     * 翌日ボタンで翌日のデータが表示される
     */
    public function test_翌日ボタンで翌日の勤怠データが表示される()
    {
        $this->actingAs($this->adminUser);

        $tomorrow = Carbon::tomorrow();

        // 今日の勤怠データを作成
        Attendance::create([
            'user_id' => $this->generalUser1->id,
            'date' => Carbon::today(),
            'start_time' => Carbon::now()->setTime(9, 0, 0),
            'end_time' => Carbon::now()->setTime(18, 0, 0),
            'status' => AttendanceStatus::Approved,
        ]);

        // 翌日の勤怠データを作成
        Attendance::create([
            'user_id' => $this->generalUser1->id,
            'date' => $tomorrow,
            'start_time' => $tomorrow->copy()->setTime(9, 0, 0),
            'end_time' => $tomorrow->copy()->setTime(18, 0, 0),
            'status' => AttendanceStatus::Approved,
        ]);

        // 翌日を表示
        $response = $this->get(route('admin_attendance_list.index', ['date' => $tomorrow->format('Y-m-d')]));

        $response->assertStatus(200);

        $today = $response->viewData('today');
        $this->assertEquals($tomorrow->format('Y-m-d'), $today);
    }

    /**
     * ユーザーが名前順にソートされる
     */
    public function test_ユーザーが名前順にソートされる()
    {
        $this->actingAs($this->adminUser);

        $response = $this->get(route('admin_attendance_list.index'));

        $response->assertStatus(200);

        $attendanceData = $response->viewData('attendanceData');

        // 名前の配列を取得
        $names = array_column($attendanceData, 'name');

        // ソート済みの配列と比較
        $sortedNames = $names;
        sort($sortedNames);

        $this->assertEquals($sortedNames, $names);
    }

    /**
     * 退勤していない場合は合計時間がnull
     */
    public function test_退勤していない場合は合計時間がnull()
    {
        $this->actingAs($this->adminUser);

        // 退勤していない勤怠データ
        Attendance::create([
            'user_id' => $this->generalUser1->id,
            'date' => Carbon::today(),
            'start_time' => Carbon::now()->setTime(9, 0, 0),
            'end_time' => null,  // 退勤なし
            'status' => AttendanceStatus::Approved,
        ]);

        $response = $this->get(route('admin_attendance_list.index'));

        $response->assertStatus(200);

        $attendanceData = $response->viewData('attendanceData');
        $user1Data = collect($attendanceData)->firstWhere('user_id', $this->generalUser1->id);

        $this->assertEquals('09:00', $user1Data['start']);
        $this->assertNull($user1Data['end']);
        $this->assertNull($user1Data['total']);
    }

    /**
     * 勤怠IDが正しく設定される
     */
    public function test_勤怠IDが正しく設定される()
    {
        $this->actingAs($this->adminUser);

        $attendance = Attendance::create([
            'user_id' => $this->generalUser1->id,
            'date' => Carbon::today(),
            'start_time' => Carbon::now()->setTime(9, 0, 0),
            'end_time' => Carbon::now()->setTime(18, 0, 0),
            'status' => AttendanceStatus::Approved,
        ]);

        $response = $this->get(route('admin_attendance_list.index'));

        $response->assertStatus(200);

        $attendanceData = $response->viewData('attendanceData');
        $user1Data = collect($attendanceData)->firstWhere('user_id', $this->generalUser1->id);

        $this->assertEquals($attendance->id, $user1Data['id']);
    }
}
