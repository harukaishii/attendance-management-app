<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class AdminController extends Controller
{
    //管理者ログイン画面の表示
    public function login(){
        return view('auth.admin_login');
    }

    //勤怠一覧の表示
    public function index(){
        return view('admin.admin_attendances');
    }
}
