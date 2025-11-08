<?php

namespace App\Http\Middleware;

use Illuminate\Auth\Middleware\Authenticate as Middleware;
use Illuminate\Http\Request;

class Authenticate extends Middleware
{
    /**
     * Get the path the user should be redirected to when they are not authenticated.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return string|null
     */
    protected function redirectTo($request)
    {
        if (! $request->expectsJson()) {
            // 管理者ページの場合は管理者ログインへ
            if ($request->is('admin/*') || $request->is('admin')) {
                return route('admin.login');
            }

            // 一般ユーザーはログインページへ
            return route('login');
        }
    }
}
