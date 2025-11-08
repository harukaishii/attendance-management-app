<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Tests\Traits\RefreshDatabaseWithoutSeeding;
use Tests\TestCase;

class AdminLoginTest extends TestCase
{
    // テストごとにデータベースをリフレッシュし、シーダーを実行
    use RefreshDatabaseWithoutSeeding;

    // 管理者ログインエンドポイント
    private $loginUrl = '/admin/login';

    // 管理者ユーザーの固定データ
    private $adminData = [
        'name' => '管理者',
        'email' => 'admin@example.com',
        'password' => 'admin123',
        'is_admin' => true,
    ];

    /**
     * テスト実行前に管理者ユーザーをDBに登録する
     * RegisterTestと異なり、先にユーザーが存在している必要があるため
     */
    protected function setUp(): void
    {
        parent::setUp();
        \App\Models\User::where('email', $this->adminData['email'])->delete();

        // データベースに管理者ユーザーを登録
        User::create([
            'name' => $this->adminData['name'],
            'email' => $this->adminData['email'],
            'email_verified_at' => now(),
            'password' => Hash::make($this->adminData['password']),
            'is_admin' => $this->adminData['is_admin'],
        ]);
    }


    // 1. 必須項目に関するバリデーションメッセージのテスト
    /** @test */
    public function validation_messages_are_displayed_for_missing_fields()
    {
        // 不正なデータ (必須項目抜け)
        $invalidData = [
            'email' => '',
            'password' => '',
        ];

        $response = $this->post($this->loginUrl, $invalidData);

        // セッションにエラーが含まれていることを確認
        $response->assertSessionHasErrors([
            'email',
            'password',
        ]);

        // 「メールアドレスを入力してください」というメッセージを確認
        $response->assertSessionHasErrors(['email' => 'メールアドレスを入力してください']);

        // 「パスワードを入力してください」というメッセージを確認
        $response->assertSessionHasErrors(['password' => 'パスワードを入力してください']);
    }

    // 2. 存在しないユーザーやパスワード不一致の場合のテスト
    /** @test */
    public function validation_message_is_displayed_for_invalid_credentials()
    {
        // 存在しないメールアドレス
        $invalidEmailData = [
            'email' => 'nonexistent@example.com',
            'password' => $this->adminData['password'],
        ];

        // パスワード不一致
        $invalidPasswordData = [
            'email' => $this->adminData['email'],
            'password' => 'wrongpassword',
        ];

        // --- 存在しないメールアドレスの場合 ---
        $response = $this->post($this->loginUrl, $invalidEmailData);
        // 'email' または 'password' フィールドにエラーとして表示される
        $response->assertSessionHasErrors(['email' => 'ログイン情報が登録されていません']);


        // --- パスワード不一致の場合 ---
        $response = $this->post($this->loginUrl, $invalidPasswordData);
        // 再度、'email' または 'password' フィールドにエラーとして表示される
        $response->assertSessionHasErrors(['email' => 'ログイン情報が登録されていません']);
    }

    // 3. 正常な管理者ログインのテスト
    /** @test */
    public function admin_user_can_login_with_correct_credentials()
    {
        $validData = [
            'email' => $this->adminData['email'],
            'password' => $this->adminData['password'],
        ];

        // 管理者ログインページにアクセス（これでセッションが設定される）
        $this->get('/admin/login');

        // ログインリクエストを送信
        $response = $this->post($this->loginUrl, $validData);

        // ログイン成功後は、/admin/attendances へリダイレクトされることを確認
        $response->assertStatus(302);
        $response->assertRedirect('/admin/attendances');

        // ユーザーが認証されたことを確認
        $this->assertAuthenticatedAs(User::where('email', $validData['email'])->first());
    }
}
