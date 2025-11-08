<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class RegisterTest extends TestCase
{
    use RefreshDatabase;

    // 登録エンドポイント
    private $registerUrl = '/register';

    // バリデーションに失敗するデータ (必須項目抜け)
    private $invalidData = [
        'name' => '',
        'email' => '',
        'password' => '',
        'password_confirmation' => '',
    ];

    // 登録成功のための有効なデータ
    private $validData = [
        'name' => 'Test User',
        'email' => 'newuser@example.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ];
    /** @test */
    public function validation_messages_are_displayed_for_missing_fields()
    {
        // 不正なデータでPOSTリクエストを送信
        $response = $this->post($this->registerUrl, $this->invalidData);

        // セッションにエラーが含まれていることを確認
        $response->assertSessionHasErrors([
            'name',
            'email',
            'password' // パスワードは空なので、このエラーと、後述の "8文字以上" のエラーを両方確認
        ]);

        // 1. 「お名前を入力してください」というバリデーションメッセージが表示される
        $response->assertSessionHasErrors(['name' => 'お名前を入力してください']);

        // 2. 「メールアドレスを入力してください」というバリデーションメッセージが表示される
        $response->assertSessionHasErrors(['email' => 'メールアドレスを入力してください']);

        // 3. 「パスワードを入力してください」というバリデーションメッセージが表示される (必須項目抜けの場合)
        $response->assertSessionHasErrors(['password' => 'パスワードを入力してください']);
    }

    /** @test */
    public function validation_password_must_be_at_least_8_characters()
    {
        $data = array_merge($this->validData, [
            'password' => 'short', // 7文字
            'password_confirmation' => 'short',
        ]);

        $response = $this->post($this->registerUrl, $data);

        // 4. 「パスワードは8文字以上で入力してください」というバリデーションメッセージが表示される
        $response->assertSessionHasErrors(['password' => 'パスワードは８文字以上で入力してください']);
    }

    /** @test */
    public function validation_password_confirmation_must_match_password()
    {
        $data = array_merge($this->validData, [
            'password' => 'password123',
            'password_confirmation' => 'mismatch456', // 不一致な値
        ]);

        $response = $this->post($this->registerUrl, $data);

        // 5. 「パスワードと一致しません」というバリデーションメッセージが表示される
        $response->assertSessionHasErrors(['password_confirmation' => 'パスワードと一致しません']);
    }

    public function user_information_is_saved_to_the_database_upon_successful_registration()
    {
        // 6. データベースに登録したユーザー情報が保存される

        $response = $this->post($this->registerUrl, $this->validData);

        $response->assertStatus(302); // 成功時のリダイレクトを確認

        // データベースにユーザーが保存されたことを確認
        $this->assertDatabaseHas('users', [
            'name' => $this->validData['name'],
            'email' => $this->validData['email'],
        ]);

        // ユーザー数が1人増えたことを確認
        $this->assertEquals(1, User::count());
    }
}
