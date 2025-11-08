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

class AdminStaffTest extends TestCase
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
            'email' => 'yamada@example.com',
            'email_verified_at' => now(),
            'password' => Hash::make('password'),
            'is_admin' => 0,
        ]);

        // 一般ユーザー2を作成
        $this->generalUser2 = User::create([
            'name' => '佐藤花子',
            'email' => 'sato@example.com',
            'email_verified_at' => now(),
            'password' => Hash::make('password'),
            'is_admin' => 0,
        ]);
    }

    /**
     * 未認証ユーザーはアクセスできない
     */
    public function test_未認証ユーザーはスタッフ一覧にアクセスできない()
    {
        $response = $this->get(route('admin_staff.index'));
        $response->assertRedirect(route('admin.login'));
    }

    /**
     * 一般ユーザーはアクセスできない
     */
    public function test_一般ユーザーはスタッフ一覧にアクセスできない()
    {
        $this->actingAs($this->generalUser1);

        $response = $this->get(route('admin_staff.index'));

        // アクセス拒否またはリダイレクト
        $this->assertTrue(
            $response->status() === 403 ||
                $response->status() === 302
        );
    }

    /**
     * 管理者ユーザーはアクセスできる
     */
    public function test_管理者ユーザーはスタッフ一覧にアクセスできる()
    {
        $this->actingAs($this->adminUser);

        $response = $this->get(route('admin_staff.index'));

        $response->assertStatus(200);
        $response->assertViewIs('admin.staff_list');
    }

    /**
     * 全ユーザーの氏名とメールアドレスが表示される
     */
    public function test_全ユーザーの氏名とメールアドレスが表示される()
    {
        $this->actingAs($this->adminUser);

        $response = $this->get(route('admin_staff.index'));

        $response->assertStatus(200);

        $users = $response->viewData('users');

        // 全ユーザーが含まれている（管理者含む）
        $this->assertGreaterThanOrEqual(3, $users->count());

        // ユーザー1の情報が含まれている
        $user1 = $users->firstWhere('id', $this->generalUser1->id);
        $this->assertNotNull($user1);
        $this->assertEquals('山田太郎', $user1->name);
        $this->assertEquals('yamada@example.com', $user1->email);

        // ユーザー2の情報が含まれている
        $user2 = $users->firstWhere('id', $this->generalUser2->id);
        $this->assertNotNull($user2);
        $this->assertEquals('佐藤花子', $user2->name);
        $this->assertEquals('sato@example.com', $user2->email);

        // ビューに表示される
        $response->assertSee('山田太郎');
        $response->assertSee('yamada@example.com');
        $response->assertSee('佐藤花子');
        $response->assertSee('sato@example.com');
    }

    /**
     * ユーザーが作成日時の降順でソートされる
     */
    public function test_ユーザーが作成日時の降順でソートされる()
    {
        $this->actingAs($this->adminUser);

        $response = $this->get(route('admin_staff.index'));

        $response->assertStatus(200);

        $users = $response->viewData('users');

        // 最新のユーザーが最初に表示される
        $firstUser = $users->first();
        $lastUser = $users->last();

        $this->assertGreaterThanOrEqual(
            $lastUser->created_at->timestamp,
            $firstUser->created_at->timestamp
        );
    }

    /**
     * 管理者も一覧に含まれる
     */
    public function test_管理者も一覧に含まれる()
    {
        $this->actingAs($this->adminUser);

        $response = $this->get(route('admin_staff.index'));

        $response->assertStatus(200);

        $users = $response->viewData('users');

        // 管理者ユーザーも含まれている
        $admin = $users->firstWhere('id', $this->adminUser->id);
        $this->assertNotNull($admin);
        $this->assertEquals(1, $admin->is_admin);
    }
}

class AdminUserAttendanceTest extends TestCase
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
            'name' => '山田太郎',
            'email' => 'yamada@example.com',
            'email_verified_at' => now(),
            'password' => Hash::make('password'),
            'is_admin' => 0,
        ]);
    }

    /**
     * 未認証ユーザーはアクセスできない
     */
    public function test_未認証ユーザーはユーザー別勤怠一覧にアクセスできない()
    {
        $response = $this->get(route('admin_user_attendance.index', ['user' => $this->generalUser->id]));
        $response->assertRedirect(route('admin.login'));
    }

    /**
     * 一般ユーザーはアクセスできない
     */
    public function test_一般ユーザーはユーザー別勤怠一覧にアクセスできない()
    {
        $this->actingAs($this->generalUser);

        $response = $this->get(route('admin_user_attendance.index', ['user' => $this->generalUser->id]));

        // アクセス拒否またはリダイレクト
        $this->assertTrue(
            $response->status() === 403 ||
                $response->status() === 302
        );
    }

    /**
     * 管理者ユーザーはアクセスできる
     */
    public function test_管理者ユーザーはユーザー別勤怠一覧にアクセスできる()
    {
        $this->actingAs($this->adminUser);

        $response = $this->get(route('admin_user_attendance.index', ['user' => $this->generalUser->id]));

        $response->assertStatus(200);
    }

    /**
     * 指定ユーザーの勤怠情報が表示される
     */
    public function test_指定ユーザーの勤怠情報が正確に表示される()
    {
        $this->actingAs($this->adminUser);

        // 対象ユーザーの勤怠データを作成
        $attendance = Attendance::create([
            'user_id' => $this->generalUser->id,
            'date' => Carbon::now()->startOfMonth(),
            'start_time' => Carbon::now()->startOfMonth()->setTime(9, 0, 0),
            'end_time' => Carbon::now()->startOfMonth()->setTime(18, 0, 0),
            'status' => AttendanceStatus::Approved,
        ]);

        // 休憩データを作成
        Breaktime::create([
            'user_id' => $this->generalUser->id,
            'attendance_id' => $attendance->id,
            'start_time' => Carbon::now()->startOfMonth()->setTime(12, 0, 0),
            'end_time' => Carbon::now()->startOfMonth()->setTime(13, 0, 0),
        ]);

        $response = $this->get(route('admin_user_attendance.index', ['user' => $this->generalUser->id]));

        $response->assertStatus(200);

        // カレンダーデータを確認
        $calendar = $response->viewData('calendar');

        // 対象ユーザーの勤怠データが含まれている
        $attendanceData = collect($calendar)->firstWhere('id', $attendance->id);
        $this->assertNotNull($attendanceData);
        $this->assertEquals('09:00', $attendanceData['start']);
        $this->assertEquals('18:00', $attendanceData['end']);
    }

    /**
     * 他のユーザーの勤怠データは含まれない
     */
    public function test_他のユーザーの勤怠データは含まれない()
    {
        $this->actingAs($this->adminUser);

        // 別のユーザーを作成
        $otherUser = User::create([
            'name' => '別のユーザー',
            'email' => 'other_' . uniqid() . '@example.com',
            'email_verified_at' => now(),
            'password' => Hash::make('password'),
            'is_admin' => 0,
        ]);

        // 対象ユーザーの勤怠データ
        $myAttendance = Attendance::create([
            'user_id' => $this->generalUser->id,
            'date' => Carbon::now()->startOfMonth(),
            'start_time' => Carbon::now()->startOfMonth()->setTime(9, 0, 0),
            'end_time' => Carbon::now()->startOfMonth()->setTime(18, 0, 0),
            'status' => AttendanceStatus::Approved,
        ]);

        // 別のユーザーの勤怠データ
        $otherAttendance = Attendance::create([
            'user_id' => $otherUser->id,
            'date' => Carbon::now()->startOfMonth(),
            'start_time' => Carbon::now()->startOfMonth()->setTime(9, 0, 0),
            'end_time' => Carbon::now()->startOfMonth()->setTime(18, 0, 0),
            'status' => AttendanceStatus::Approved,
        ]);

        $response = $this->get(route('admin_user_attendance.index', ['user' => $this->generalUser->id]));

        $response->assertStatus(200);

        $calendar = $response->viewData('calendar');
        $ids = collect($calendar)->pluck('id')->filter();

        // 対象ユーザーのデータのみ含まれる
        $this->assertContains($myAttendance->id, $ids);
        $this->assertNotContains($otherAttendance->id, $ids);
    }

    /**
     * 前月ボタンで前月のデータが表示される
     */
    public function test_前月ボタンで前月の勤怠データが表示される()
    {
        $this->actingAs($this->adminUser);

        $lastMonth = Carbon::now()->subMonth();

        // 前月のデータを作成
        $lastMonthAttendance = Attendance::create([
            'user_id' => $this->generalUser->id,
            'date' => $lastMonth->startOfMonth(),
            'start_time' => $lastMonth->copy()->setTime(9, 0, 0),
            'end_time' => $lastMonth->copy()->setTime(18, 0, 0),
            'status' => AttendanceStatus::Approved,
        ]);

        // 今月のデータを作成
        $thisMonthAttendance = Attendance::create([
            'user_id' => $this->generalUser->id,
            'date' => Carbon::now()->startOfMonth(),
            'start_time' => Carbon::now()->startOfMonth()->setTime(9, 0, 0),
            'end_time' => Carbon::now()->startOfMonth()->setTime(18, 0, 0),
            'status' => AttendanceStatus::Approved,
        ]);

        // 前月を表示
        $response = $this->get(route('admin_user_attendance.index', [
            'user' => $this->generalUser->id,
            'year' => $lastMonth->year,
            'month' => $lastMonth->month
        ]));

        $response->assertStatus(200);

        $calendar = $response->viewData('calendar');
        $ids = collect($calendar)->pluck('id')->filter();

        // 前月のデータが含まれている
        $this->assertContains($lastMonthAttendance->id, $ids);

        // 今月のデータが含まれていない
        $this->assertNotContains($thisMonthAttendance->id, $ids);
    }

    /**
     * 翌月ボタンで翌月のデータが表示される
     */
    public function test_翌月ボタンで翌月の勤怠データが表示される()
    {
        $this->actingAs($this->adminUser);

        $nextMonth = Carbon::now()->addMonth();

        // 今月のデータを作成
        $thisMonthAttendance = Attendance::create([
            'user_id' => $this->generalUser->id,
            'date' => Carbon::now()->startOfMonth(),
            'start_time' => Carbon::now()->startOfMonth()->setTime(9, 0, 0),
            'end_time' => Carbon::now()->startOfMonth()->setTime(18, 0, 0),
            'status' => AttendanceStatus::Approved,
        ]);

        // 翌月のデータを作成
        $nextMonthAttendance = Attendance::create([
            'user_id' => $this->generalUser->id,
            'date' => $nextMonth->startOfMonth(),
            'start_time' => $nextMonth->copy()->setTime(9, 0, 0),
            'end_time' => $nextMonth->copy()->setTime(18, 0, 0),
            'status' => AttendanceStatus::Approved,
        ]);

        // 翌月を表示
        $response = $this->get(route('admin_user_attendance.index', [
            'user' => $this->generalUser->id,
            'year' => $nextMonth->year,
            'month' => $nextMonth->month
        ]));

        $response->assertStatus(200);

        $calendar = $response->viewData('calendar');
        $ids = collect($calendar)->pluck('id')->filter();

        // 翌月のデータが含まれている
        $this->assertContains($nextMonthAttendance->id, $ids);

        // 今月のデータが含まれていない
        $this->assertNotContains($thisMonthAttendance->id, $ids);
    }

    /**
     * 詳細ボタンで勤怠詳細画面に遷移する
     */
    public function test_詳細ボタンで勤怠詳細画面に遷移する()
    {
        $this->actingAs($this->adminUser);

        $attendance = Attendance::create([
            'user_id' => $this->generalUser->id,
            'date' => Carbon::today(),
            'start_time' => Carbon::now()->setTime(9, 0, 0),
            'end_time' => Carbon::now()->setTime(18, 0, 0),
            'status' => AttendanceStatus::Approved,
        ]);

        // 詳細画面にアクセス
        $response = $this->get(route('admin_attendance_detail.show', ['id' => $attendance->id]));

        $response->assertStatus(200);
        $response->assertViewIs('admin.admin_attendance_detail');
        $response->assertViewHas('attendance');
    }

    /**
     * 現在の月が表示される
     */
    public function test_現在の月が表示される()
    {
        $this->actingAs($this->adminUser);

        $response = $this->get(route('admin_user_attendance.index', ['user' => $this->generalUser->id]));

        $response->assertStatus(200);

        $currentMonth = $response->viewData('currentMonth');
        $now = Carbon::now();

        $this->assertEquals($now->year, $currentMonth->year);
        $this->assertEquals($now->month, $currentMonth->month);
    }
}
