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

class AdminApplicationTest extends TestCase
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
            'email' => 'yamada_' . uniqid() . '@example.com',
            'email_verified_at' => now(),
            'password' => Hash::make('password'),
            'is_admin' => 0,
        ]);

        // 一般ユーザー2を作成
        $this->generalUser2 = User::create([
            'name' => '佐藤花子',
            'email' => 'sato_' . uniqid() . '@example.com',
            'email_verified_at' => now(),
            'password' => Hash::make('password'),
            'is_admin' => 0,
        ]);
    }

    /**
     * 未認証ユーザーはアクセスできない
     */
    public function test_未認証ユーザーは申請一覧にアクセスできない()
    {
        $response = $this->get(route('admin_request.index'));
        $response->assertRedirect(route('admin.login'));
    }

    /**
     * 一般ユーザーはアクセスできない
     */
    public function test_一般ユーザーは申請一覧にアクセスできない()
    {
        $this->actingAs($this->generalUser1);

        $response = $this->get(route('admin_request.index'));

        // アクセス拒否またはリダイレクト
        $this->assertTrue(
            $response->status() === 403 ||
                $response->status() === 302
        );
    }

    /**
     * 管理者ユーザーはアクセスできる
     */
    public function test_管理者ユーザーは申請一覧にアクセスできる()
    {
        $this->actingAs($this->adminUser);

        $response = $this->get(route('admin_request.index'));

        $response->assertStatus(200);
        $response->assertViewIs('admin.application_list');
    }

    /**
     * 承認待ちの申請が全て表示される
     */
    public function test_承認待ちの申請が全て表示される()
    {
        $this->actingAs($this->adminUser);

        // ユーザー1の承認待ち申請を作成
        $pendingAttendance1 = Attendance::create([
            'user_id' => $this->generalUser1->id,
            'date' => Carbon::today(),
            'start_time' => Carbon::now()->setTime(9, 0, 0),
            'end_time' => Carbon::now()->setTime(18, 0, 0),
            'status' => AttendanceStatus::Unapproved,
            'note' => '遅刻のため修正',
        ]);

        // ユーザー2の承認待ち申請を作成
        $pendingAttendance2 = Attendance::create([
            'user_id' => $this->generalUser2->id,
            'date' => Carbon::today(),
            'start_time' => Carbon::now()->setTime(9, 30, 0),
            'end_time' => Carbon::now()->setTime(18, 0, 0),
            'status' => AttendanceStatus::Unapproved,
            'note' => '早退のため修正',
        ]);

        // 承認済みの申請を作成（表示されないはず）
        $approvedAttendance = Attendance::create([
            'user_id' => $this->generalUser1->id,
            'date' => Carbon::yesterday(),
            'start_time' => Carbon::yesterday()->setTime(9, 0, 0),
            'end_time' => Carbon::yesterday()->setTime(18, 0, 0),
            'status' => AttendanceStatus::Approved,
        ]);

        $response = $this->get(route('admin_request.index', ['status' => 'pending']));

        $response->assertStatus(200);

        $applications = $response->viewData('applications');

        // 承認待ちが2件表示される
        $this->assertEquals(2, $applications->count());

        // 承認待ちの申請が含まれている
        $this->assertTrue($applications->contains('id', $pendingAttendance1->id));
        $this->assertTrue($applications->contains('id', $pendingAttendance2->id));

        // 承認済みの申請は含まれない
        $this->assertFalse($applications->contains('id', $approvedAttendance->id));
    }

    /**
     * 承認済みの申請が全て表示される
     */
    public function test_承認済みの申請が全て表示される()
    {
        $this->actingAs($this->adminUser);

        // 承認済み申請を作成
        $approvedAttendance1 = Attendance::create([
            'user_id' => $this->generalUser1->id,
            'date' => Carbon::today(),
            'start_time' => Carbon::now()->setTime(9, 0, 0),
            'end_time' => Carbon::now()->setTime(18, 0, 0),
            'status' => AttendanceStatus::Approved,
        ]);

        $approvedAttendance2 = Attendance::create([
            'user_id' => $this->generalUser2->id,
            'date' => Carbon::yesterday(),
            'start_time' => Carbon::yesterday()->setTime(9, 0, 0),
            'end_time' => Carbon::yesterday()->setTime(18, 0, 0),
            'status' => AttendanceStatus::Approved,
        ]);

        // 承認待ち申請を作成（表示されないはず）
        $pendingAttendance = Attendance::create([
            'user_id' => $this->generalUser1->id,
            'date' => Carbon::today()->addDay(),
            'start_time' => Carbon::today()->addDay()->setTime(9, 0, 0),
            'end_time' => Carbon::today()->addDay()->setTime(18, 0, 0),
            'status' => AttendanceStatus::Unapproved,
        ]);

        $response = $this->get(route('admin_request.index', ['status' => 'approved']));

        $response->assertStatus(200);

        $applications = $response->viewData('applications');

        // 承認済みが2件以上表示される（setUp内の他のデータも含む可能性）
        $this->assertGreaterThanOrEqual(2, $applications->count());

        // 承認済みの申請が含まれている
        $this->assertTrue($applications->contains('id', $approvedAttendance1->id));
        $this->assertTrue($applications->contains('id', $approvedAttendance2->id));

        // 承認待ちの申請は含まれない
        $this->assertFalse($applications->contains('id', $pendingAttendance->id));
    }

    /**
     * デフォルトで承認待ちが表示される
     */
    public function test_デフォルトで承認待ちが表示される()
    {
        $this->actingAs($this->adminUser);

        // 承認待ち申請を作成
        $pendingAttendance = Attendance::create([
            'user_id' => $this->generalUser1->id,
            'date' => Carbon::today(),
            'start_time' => Carbon::now()->setTime(9, 0, 0),
            'end_time' => Carbon::now()->setTime(18, 0, 0),
            'status' => AttendanceStatus::Unapproved,
        ]);

        // ステータスパラメータなしでアクセス
        $response = $this->get(route('admin_request.index'));

        $response->assertStatus(200);

        $status = $response->viewData('status');
        $this->assertEquals('pending', $status);
    }

    /**
     * 申請が更新日時の降順で表示される
     */
    public function test_申請が更新日時の降順で表示される()
    {
        $this->actingAs($this->adminUser);

        // 古い申請を作成
        $oldAttendance = Attendance::create([
            'user_id' => $this->generalUser1->id,
            'date' => Carbon::today(),
            'start_time' => Carbon::now()->setTime(9, 0, 0),
            'end_time' => Carbon::now()->setTime(18, 0, 0),
            'status' => AttendanceStatus::Unapproved,
        ]);

        // 少し待つ
        sleep(1);

        // 新しい申請を作成
        $newAttendance = Attendance::create([
            'user_id' => $this->generalUser2->id,
            'date' => Carbon::today(),
            'start_time' => Carbon::now()->setTime(9, 0, 0),
            'end_time' => Carbon::now()->setTime(18, 0, 0),
            'status' => AttendanceStatus::Unapproved,
        ]);

        $response = $this->get(route('admin_request.index', ['status' => 'pending']));

        $response->assertStatus(200);

        $applications = $response->viewData('applications');

        // 申請が2件以上あることを確認
        $this->assertGreaterThanOrEqual(2, $applications->count());

        // 新しい申請が古い申請より前に表示されることを確認
        $newIndex = $applications->search(function ($item) use ($newAttendance) {
            return $item->id === $newAttendance->id;
        });

        $oldIndex = $applications->search(function ($item) use ($oldAttendance) {
            return $item->id === $oldAttendance->id;
        });

        // 新しい方が前（インデックスが小さい）
        $this->assertLessThan($oldIndex, $newIndex);
    }

    /**
     * 申請詳細画面にアクセスできる
     */
    public function test_申請詳細画面にアクセスできる()
    {
        $this->actingAs($this->adminUser);

        $attendance = Attendance::create([
            'user_id' => $this->generalUser1->id,
            'date' => Carbon::today(),
            'start_time' => Carbon::now()->setTime(9, 0, 0),
            'end_time' => Carbon::now()->setTime(18, 0, 0),
            'status' => AttendanceStatus::Unapproved,
            'note' => '遅刻のため修正申請',
        ]);

        $response = $this->get(route('admin_request.show', ['id' => $attendance->id]));

        $response->assertStatus(200);
        $response->assertViewIs('admin.application_detail');
    }

    /**
     * 申請詳細の内容が正しく表示される
     */
    public function test_申請詳細の内容が正しく表示される()
    {
        $this->actingAs($this->adminUser);

        $targetDate = Carbon::create(2025, 11, 15);
        $startTime = Carbon::create(2025, 11, 15, 9, 30, 0);
        $endTime = Carbon::create(2025, 11, 15, 18, 15, 0);

        $attendance = Attendance::create([
            'user_id' => $this->generalUser1->id,
            'date' => $targetDate,
            'start_time' => $startTime,
            'end_time' => $endTime,
            'status' => AttendanceStatus::Unapproved,
            'note' => 'テスト修正申請',
        ]);

        // 休憩データを作成
        Breaktime::create([
            'user_id' => $this->generalUser1->id,
            'attendance_id' => $attendance->id,
            'start_time' => Carbon::create(2025, 11, 15, 12, 0, 0),
            'end_time' => Carbon::create(2025, 11, 15, 13, 0, 0),
        ]);

        $response = $this->get(route('admin_request.show', ['id' => $attendance->id]));

        $response->assertStatus(200);

        // ビューに渡されたデータを確認
        $viewAttendance = $response->viewData('attendance');
        $this->assertEquals($targetDate->format('Y-m-d'), Carbon::parse($viewAttendance->date)->format('Y-m-d'));
        $this->assertEquals($startTime->format('H:i:s'), Carbon::parse($viewAttendance->start_time)->format('H:i:s'));
        $this->assertEquals($endTime->format('H:i:s'), Carbon::parse($viewAttendance->end_time)->format('H:i:s'));
        $this->assertEquals('テスト修正申請', $viewAttendance->note);

        // ユーザー情報が含まれている
        $user = $response->viewData('user');
        $this->assertEquals($this->generalUser1->id, $user->id);
        $this->assertEquals($this->generalUser1->name, $user->name);

        // 休憩データが含まれている
        $breaktimes = $response->viewData('breaktimes');
        $this->assertEquals(1, $breaktimes->count());
    }

    /**
     * 承認処理が正常に実行される
     */
    public function test_承認処理が正常に実行される()
    {
        $this->actingAs($this->adminUser);

        $attendance = Attendance::create([
            'user_id' => $this->generalUser1->id,
            'date' => Carbon::today(),
            'start_time' => Carbon::now()->setTime(9, 0, 0),
            'end_time' => Carbon::now()->setTime(18, 0, 0),
            'status' => AttendanceStatus::Unapproved,
            'note' => '遅刻のため修正',
        ]);

        $response = $this->post(route('admin_request.approve', ['id' => $attendance->id]));

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'message' => '承認しました',
        ]);

        // ステータスが承認済みに変更されている
        $attendance->refresh();
        $this->assertEquals(AttendanceStatus::Approved->value, $attendance->status->value);
    }

    /**
     * 承認後は承認待ち一覧に表示されなくなる
     */
    public function test_承認後は承認待ち一覧に表示されなくなる()
    {
        $this->actingAs($this->adminUser);

        $attendance = Attendance::create([
            'user_id' => $this->generalUser1->id,
            'date' => Carbon::today(),
            'start_time' => Carbon::now()->setTime(9, 0, 0),
            'end_time' => Carbon::now()->setTime(18, 0, 0),
            'status' => AttendanceStatus::Unapproved,
        ]);

        // 承認前：承認待ち一覧に表示される
        $response = $this->get(route('admin_request.index', ['status' => 'pending']));
        $applications = $response->viewData('applications');
        $this->assertTrue($applications->contains('id', $attendance->id));

        // 承認処理
        $this->post(route('admin_request.approve', ['id' => $attendance->id]));

        // 承認後：承認待ち一覧に表示されない
        $response = $this->get(route('admin_request.index', ['status' => 'pending']));
        $applications = $response->viewData('applications');
        $this->assertFalse($applications->contains('id', $attendance->id));

        // 承認後：承認済み一覧に表示される
        $response = $this->get(route('admin_request.index', ['status' => 'approved']));
        $applications = $response->viewData('applications');
        $this->assertTrue($applications->contains('id', $attendance->id));
    }

    /**
     * 一般ユーザーは承認処理を実行できない
     */
    public function test_一般ユーザーは承認処理を実行できない()
    {
        $this->actingAs($this->generalUser1);

        $attendance = Attendance::create([
            'user_id' => $this->generalUser1->id,
            'date' => Carbon::today(),
            'start_time' => Carbon::now()->setTime(9, 0, 0),
            'end_time' => Carbon::now()->setTime(18, 0, 0),
            'status' => AttendanceStatus::Unapproved,
        ]);

        $response = $this->post(route('admin_request.approve', ['id' => $attendance->id]));

        // アクセス拒否またはリダイレクト
        $this->assertTrue(
            $response->status() === 403 ||
                $response->status() === 302
        );

        // ステータスが変更されていないことを確認
        $attendance->refresh();
        $this->assertEquals(AttendanceStatus::Unapproved->value, $attendance->status->value);
    }

    /**
     * ユーザー情報が申請一覧に表示される
     */
    public function test_ユーザー情報が申請一覧に表示される()
    {
        $this->actingAs($this->adminUser);

        $attendance = Attendance::create([
            'user_id' => $this->generalUser1->id,
            'date' => Carbon::today(),
            'start_time' => Carbon::now()->setTime(9, 0, 0),
            'end_time' => Carbon::now()->setTime(18, 0, 0),
            'status' => AttendanceStatus::Unapproved,
        ]);

        $response = $this->get(route('admin_request.index', ['status' => 'pending']));

        $response->assertStatus(200);

        $applications = $response->viewData('applications');
        $application = $applications->firstWhere('id', $attendance->id);

        // ユーザー情報がロードされている
        $this->assertNotNull($application->user);
        $this->assertEquals($this->generalUser1->name, $application->user->name);
    }
}
