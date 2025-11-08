<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class IsAdmin
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Illuminate\Http\Response|\Illuminate\Http\RedirectResponse)  $next
     * @return \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
     */
    public function handle(Request $request, Closure $next)
    {
        // ユーザーが認証済みで、かつ is_admin プロパティが truthy (1 または true) であるかを確認
        if (auth()->check() && auth()->user()->is_admin) {
            return $next($request);
        }

        // 認証されていないか、管理者ではない場合
        if (!auth()->check()) {
            // 認証されていない場合はログインページへ
            return redirect()->route('login');
        }

        // 認証済みだが管理者ではない場合はアクセス拒否
        abort(403, 'This action is unauthorized.');
    }
}
