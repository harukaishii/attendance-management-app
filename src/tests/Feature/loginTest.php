<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use RefreshDatabase;

    private $userEmail = 'user@example.com';
    private $rawPassword = 'user1234';
    private $testUser;

    /**
     * 各テストメソッドの前に実行される処理
     */
    protected function setUp(): void
    {
        parent::setUp();
        if (User::where('email', $this->userEmail)->doesntExist()) {
            $this->testUser = User::factory()->create([
                'email' => $this->userEmail,
                'password' => \Hash::make($this->rawPassword),
            ]);
        } else {
            $this->testUser = User::where('email', $this->userEmail)->first();
        }
    }

    /**
     * @test
     * 正常なログイン処理のテスト
     */
    public function a_user_can_login_with_valid_credentials()
    {
        $response = $this->post('/login', [
            'email' => $this->userEmail,
            'password' => $this->rawPassword,
        ]);

        // ユーザーが認証された状態になったことを確認
        $this->assertAuthenticatedAs($this->testUser);

        // Fortifyが認証に成功すると、指定されたホームルートにリダイレクトします
        $response->assertStatus(302);
        $response->assertRedirect('/attendance');
    }

    // ---

    /**
     * @test
     * メールアドレスが空の場合のバリデーションテスト
     */
    public function email_field_is_required_for_login()
    {
        $response = $this->post('/login', [
            // 'email' => '', // 意図的に空にする
            'password' => $this->rawPassword,
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    // ---

    /**
     * @test
     * 不正なパスワードでのログイン失敗テスト
     */
    public function user_cannot_login_with_incorrect_password()
    {
        $response = $this->post('/login', [
            'email' => $this->userEmail,
            'password' => 'wrongpassword', // 間違ったパスワード
        ]);

        // 認証失敗のエラーメッセージがセッションにあることを確認
        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

}
