<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use App\Models\User;
use App\Models\Attendance;
use App\Models\Breaktime;
use Carbon\Carbon;
use Illuminate\Support\Facades\Hash;

class AttendanceListTest extends TestCase
{
    use DatabaseTransactions;

    private $testUser;

    protected function setUp(): void
    {
        parent::setUp();

        // テスト専用のユーザーを作成
        $this->testUser = User::create([
            'name' => 'テスト専用ユーザー',
            'email' => 'test_' . uniqid() . '@example.com',
            'email_verified_at' => now(),
            'password' => Hash::make('password'),
            'is_admin' => 0,
        ]);
    }

    /**
     * 未認証ユーザーはアクセスできない
     */
    public function test_未認証ユーザーは勤怠一覧画面にアクセスできない()
    {
        $response = $this->get(route('attendance.list.index'));
        $response->assertRedirect(route('login'));
    }

    /**
     * 認証済みユーザーは勤怠一覧画面にアクセスできる
     */
    public function test_認証済みユーザーは勤怠一覧画面にアクセスできる()
    {
        $this->actingAs($this->testUser);
        $response = $this->get(route('attendance.list.index'));

        $response->assertStatus(200);
        $response->assertViewIs('user.attendance_list');
    }

    /**
     * 現在の月が表示される
     */
    public function test_勤怠一覧画面に現在の月が表示される()
    {
        $this->actingAs($this->testUser);
        $response = $this->get(route('attendance.list.index'));

        $response->assertStatus(200);

        // ビューに currentMonth が渡されていることを確認
        $response->assertViewHas('currentMonth');

        $currentMonth = $response->viewData('currentMonth');
        $now = Carbon::now();

        // 年月が一致することを確認
        $this->assertEquals($now->year, $currentMonth->year);
        $this->assertEquals($now->month, $currentMonth->month);
    }

    /**
     * 自分の勤怠情報のみが表示される
     */
    public function test_自分の勤怠情報のみが表示される()
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

        // 自分の勤怠データを作成
        $myAttendance = Attendance::create([
            'user_id' => $this->testUser->id,
            'date' => Carbon::today(),
            'start_time' => Carbon::now()->setTime(9, 0, 0),
            'end_time' => Carbon::now()->setTime(18, 0, 0),
        ]);

        // 他のユーザーの勤怠データを作成
        $otherAttendance = Attendance::create([
            'user_id' => $otherUser->id,
            'date' => Carbon::today(),
            'start_time' => Carbon::now()->setTime(9, 0, 0),
            'end_time' => Carbon::now()->setTime(18, 0, 0),
        ]);

        $response = $this->get(route('attendance.list.index'));

        $response->assertStatus(200);

        // ビューに渡された calendar データを確認
        $calendar = $response->viewData('calendar');

        // 自分のデータが含まれていることを確認
        $myData = collect($calendar)->firstWhere('id', $myAttendance->id);
        $this->assertNotNull($myData);

        // 他人のデータが含まれていないことを確認
        $otherData = collect($calendar)->firstWhere('id', $otherAttendance->id);
        $this->assertNull($otherData);
    }

    /**
     * 複数の勤怠データが表示される
     */
    public function test_複数の勤怠データが全て表示される()
    {
        $this->actingAs($this->testUser);

        // 今月の勤怠データを3件作成
        $attendance1 = Attendance::create([
            'user_id' => $this->testUser->id,
            'date' => Carbon::now()->startOfMonth(),
            'start_time' => Carbon::now()->startOfMonth()->setTime(9, 0, 0),
            'end_time' => Carbon::now()->startOfMonth()->setTime(18, 0, 0),
        ]);

        $attendance2 = Attendance::create([
            'user_id' => $this->testUser->id,
            'date' => Carbon::now()->startOfMonth()->addDays(1),
            'start_time' => Carbon::now()->startOfMonth()->addDays(1)->setTime(9, 0, 0),
            'end_time' => Carbon::now()->startOfMonth()->addDays(1)->setTime(18, 0, 0),
        ]);

        $attendance3 = Attendance::create([
            'user_id' => $this->testUser->id,
            'date' => Carbon::now()->startOfMonth()->addDays(2),
            'start_time' => Carbon::now()->startOfMonth()->addDays(2)->setTime(9, 0, 0),
            'end_time' => Carbon::now()->startOfMonth()->addDays(2)->setTime(18, 0, 0),
        ]);

        $response = $this->get(route('attendance.list.index'));

        $response->assertStatus(200);

        $calendar = $response->viewData('calendar');

        // 3件のデータが含まれていることを確認
        $ids = collect($calendar)->pluck('id')->filter()->values();
        $this->assertContains($attendance1->id, $ids);
        $this->assertContains($attendance2->id, $ids);
        $this->assertContains($attendance3->id, $ids);
    }

    /**
     * 前月ボタンで前月のデータが表示される
     */
    public function test_前月ボタンで前月の勤怠データが表示される()
    {
        $this->actingAs($this->testUser);

        $lastMonth = Carbon::now()->subMonth();

        // 前月のデータを作成
        $lastMonthAttendance = Attendance::create([
            'user_id' => $this->testUser->id,
            'date' => $lastMonth->startOfMonth(),
            'start_time' => $lastMonth->copy()->setTime(9, 0, 0),
            'end_time' => $lastMonth->copy()->setTime(18, 0, 0),
        ]);

        // 今月のデータを作成
        $thisMonthAttendance = Attendance::create([
            'user_id' => $this->testUser->id,
            'date' => Carbon::now()->startOfMonth(),
            'start_time' => Carbon::now()->startOfMonth()->setTime(9, 0, 0),
            'end_time' => Carbon::now()->startOfMonth()->setTime(18, 0, 0),
        ]);

        // 前月を表示
        $response = $this->get(route('attendance.list.show', [
            'year' => $lastMonth->year,
            'month' => $lastMonth->month
        ]));

        $response->assertStatus(200);

        $calendar = $response->viewData('calendar');
        $ids = collect($calendar)->pluck('id')->filter()->values();

        // 前月のデータが含まれていることを確認
        $this->assertContains($lastMonthAttendance->id, $ids);

        // 今月のデータが含まれていないことを確認
        $this->assertNotContains($thisMonthAttendance->id, $ids);
    }

    /**
     * 翌月ボタンで翌月のデータが表示される
     */
    public function test_翌月ボタンで翌月の勤怠データが表示される()
    {
        $this->actingAs($this->testUser);

        $nextMonth = Carbon::now()->addMonth();

        // 今月のデータを作成
        $thisMonthAttendance = Attendance::create([
            'user_id' => $this->testUser->id,
            'date' => Carbon::now()->startOfMonth(),
            'start_time' => Carbon::now()->startOfMonth()->setTime(9, 0, 0),
            'end_time' => Carbon::now()->startOfMonth()->setTime(18, 0, 0),
        ]);

        // 翌月のデータを作成
        $nextMonthAttendance = Attendance::create([
            'user_id' => $this->testUser->id,
            'date' => $nextMonth->startOfMonth(),
            'start_time' => $nextMonth->copy()->setTime(9, 0, 0),
            'end_time' => $nextMonth->copy()->setTime(18, 0, 0),
        ]);

        // 翌月を表示
        $response = $this->get(route('attendance.list.show', [
            'year' => $nextMonth->year,
            'month' => $nextMonth->month
        ]));

        $response->assertStatus(200);

        $calendar = $response->viewData('calendar');
        $ids = collect($calendar)->pluck('id')->filter()->values();

        // 翌月のデータが含まれていることを確認
        $this->assertContains($nextMonthAttendance->id, $ids);

        // 今月のデータが含まれていないことを確認
        $this->assertNotContains($thisMonthAttendance->id, $ids);
    }

    /**
     * 年をまたぐ前月の表示
     */
    public function test_年をまたいで前月のデータが表示される()
    {
        $this->actingAs($this->testUser);

        // 1月のデータを表示している状態で前月（前年12月）を確認
        $lastDecember = Carbon::create(Carbon::now()->year - 1, 12, 1);

        $decemberAttendance = Attendance::create([
            'user_id' => $this->testUser->id,
            'date' => $lastDecember,
            'start_time' => $lastDecember->copy()->setTime(9, 0, 0),
            'end_time' => $lastDecember->copy()->setTime(18, 0, 0),
        ]);

        $response = $this->get(route('attendance.list.show', [
            'year' => $lastDecember->year,
            'month' => 12
        ]));

        $response->assertStatus(200);

        // currentMonth の年月を確認
        $currentMonth = $response->viewData('currentMonth');
        $this->assertEquals($lastDecember->year, $currentMonth->year);
        $this->assertEquals(12, $currentMonth->month);

        $calendar = $response->viewData('calendar');
        $ids = collect($calendar)->pluck('id')->filter()->values();
        $this->assertContains($decemberAttendance->id, $ids);
    }

    /**
     * 詳細ボタンで勤怠詳細画面に遷移する
     */
    public function test_詳細ボタンで勤怠詳細画面に遷移する()
    {
        $this->actingAs($this->testUser);

        $attendance = Attendance::create([
            'user_id' => $this->testUser->id,
            'date' => Carbon::today(),
            'start_time' => Carbon::now()->setTime(9, 0, 0),
            'end_time' => Carbon::now()->setTime(18, 0, 0),
        ]);

        // 詳細画面にアクセス
        $response = $this->get(route('attendance.detail.show', ['id' => $attendance->id]));

        $response->assertStatus(200);
        $response->assertViewIs('user.attendance_detail');
        $response->assertViewHas('attendance');
    }

    /**
     * 他人の勤怠詳細は閲覧できない（権限チェック）
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
        ]);

        // 他人の詳細画面にアクセス
        $response = $this->get(route('attendance.detail.show', ['id' => $otherAttendance->id]));

        // アクセス拒否またはリダイレクトされることを確認
        // 実装によって assertStatus(403) または assertRedirect() など
        $this->assertTrue(
            $response->status() === 403 ||
                $response->status() === 302 ||
                $response->status() === 404
        );
    }

    /**
     * 勤怠データがない月でもページが表示される
     */
    public function test_勤怠データがない月でもページが表示される()
    {
        $this->actingAs($this->testUser);

        // データがない月を指定（2年後）
        $futureDate = Carbon::now()->addYears(2);

        $response = $this->get(route('attendance.list.show', [
            'year' => $futureDate->year,
            'month' => $futureDate->month
        ]));

        $response->assertStatus(200);

        // calendar は月の全日付分作成されるが、id は null
        $calendar = $response->viewData('calendar');
        $this->assertGreaterThan(0, count($calendar));

        // すべての id が null であることを確認
        $ids = collect($calendar)->pluck('id')->filter();
        $this->assertEquals(0, $ids->count());
    }

    /**
     * 休憩時間も含めて表示される
     */
    public function test_勤怠データに休憩時間が含まれる()
    {
        $this->actingAs($this->testUser);

        $attendance = Attendance::create([
            'user_id' => $this->testUser->id,
            'date' => Carbon::today(),
            'start_time' => Carbon::now()->setTime(9, 0, 0),
            'end_time' => Carbon::now()->setTime(18, 0, 0),
        ]);

        // 休憩データを作成
        Breaktime::create([
            'user_id' => $this->testUser->id,
            'attendance_id' => $attendance->id,
            'start_time' => Carbon::now()->setTime(12, 0, 0),
            'end_time' => Carbon::now()->setTime(13, 0, 0),
        ]);

        $response = $this->get(route('attendance.list.index'));

        $response->assertStatus(200);

        // calendar から該当日のデータを取得
        $calendar = $response->viewData('calendar');
        $todayData = collect($calendar)->firstWhere('id', $attendance->id);

        $this->assertNotNull($todayData);
        // 休憩時間が計算されていることを確認
        $this->assertNotNull($todayData['break']);
        $this->assertEquals('01:00', $todayData['break']);
    }
}
