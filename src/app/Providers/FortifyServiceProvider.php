<?php

namespace App\Providers;

use App\Actions\Fortify\CreateNewUser;
use App\Http\Requests\LoginRequest;
use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;
use Laravel\Fortify\Fortify;
use Laravel\Fortify\Contracts\LoginResponse;
use Laravel\Fortify\Http\Requests\LoginRequest as FortifyLoginRequest;

class FortifyServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // LoginResponseをカスタマイズ
        $this->app->singleton(LoginResponse::class, function () {
            return new class implements LoginResponse {
                public function toResponse($request)
                {
                    // セッションから判定
                    $loginType = session('login_type');

                    // セッションがない場合はURLから判定（テスト用）
                    if (!$loginType && $request->is('admin/login')) {
                        $loginType = 'admin';
                    }

                    // セッションをクリア
                    session()->forget('login_type');
                    session()->forget('auth_checked');
                    session()->forget('auth_call_count');

                    // 管理者ログインからの場合のみ管理者ページへ
                    if ($loginType === 'admin') {
                        return redirect()->intended(route('admin_attendance_list.index'));
                    }

                    // それ以外は一般ユーザーページへ
                    return redirect()->intended(route('attendance.index'));
                }
            };
        });

        // LogoutResponseをカスタマイズ
        $this->app->singleton(\Laravel\Fortify\Contracts\LogoutResponse::class, function () {
            return new class implements \Laravel\Fortify\Contracts\LogoutResponse {
                public function toResponse($request)
                {

                    // 管理者ページからのログアウトの場合
                    if ($request->is('admin/*')) {
                        return redirect()->route('admin.login');
                    }

                    // 一般ユーザーページからのログアウトの場合
                    return redirect()->route('login');
                }
            };
        });

        // ★ RegisterResponseを追加
        $this->app->singleton(\Laravel\Fortify\Contracts\RegisterResponse::class, function () {
            return new class implements \Laravel\Fortify\Contracts\RegisterResponse {
                public function toResponse($request)
                {
                    // 登録後のリダイレクト先を指定
                    return redirect('/email/verify');
                }
            };
        });
    }

    public function boot(): void
    {
        Fortify::createUsersUsing(CreateNewUser::class);

        Fortify::registerView(function () {
            return view('auth.register');
        });

        Fortify::loginView(function (Request $request) {

            if ($request->is('admin/login')) {
                session(['login_type' => 'admin']);
                session()->save();
                return view('auth.admin_login');
            }
            session(['login_type' => 'user']);
            session()->save();

            return view('auth.login');
        });

        RateLimiter::for('login', function (Request $request) {
            $email = (string) $request->email;
            return Limit::perMinute(10)->by($email . $request->ip());
        });

        // フォームリクエストを自作のものにバインド
        app()->bind(FortifyLoginRequest::class, LoginRequest::class);

        // 認証ロジック
        Fortify::authenticateUsing(function (Request $request) {
            session()->increment('auth_call_count');
            $user = User::where('email', $request->email)->first();

            if (!$user) {
                return null;
            }

            if (!Hash::check($request->password, $user->password)) {
                return null;
            }

            // 既に1回チェック済みの場合はスキップ
            if (session('auth_checked') === true) {
                return $user;
            }

            $loginType = session('login_type');

            // セッションがない場合はURLから判定（テスト用）
            if (!$loginType && $request->is('admin/login')) {
                $loginType = 'admin';
            }

            // 管理者ログインページからのアクセスの場合のみ is_admin をチェック
            if ($loginType === 'admin') {
                if ($user->is_admin !== 1) {
                    return null;
                }
            }

            // 1回チェック済みフラグを立てる
            session(['auth_checked' => true]);
            return $user;
        });

        Fortify::verifyEmailView(function () {
            return view('auth.verify_email');
        });
    }
}
