<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;

class AdminStaffController extends Controller
{
    /**
     * スタッフ一覧の表示
     *
     * @return \Illuminate\View\View
     */
    public function index()
    {
        // 全ユーザーを取得（管理者も含む）
        $users = User::orderBy('created_at', 'desc')->get();

        return view('admin.staff_list', compact('users'));
    }
}
