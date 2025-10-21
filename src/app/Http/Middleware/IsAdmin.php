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
        // ユーザーがログインしているか確認
        if (!auth()->check()) {
            return redirect()->route('login');
        }

        $user = auth()->user();


        // ユーザーが管理者か確認（複数の条件をチェック）
        if (!$user->is_admin && $user->is_admin !== 1 && $user->is_admin !== true) {
            abort(403, 'This action is unauthorized.');
        }


        return $next($request);
    }
}
