@extends('layouts.header')

@section('css')
<link rel="stylesheet" href="{{ asset('css/sanitize.css') }}">
<link rel="stylesheet" href="{{ asset('css/header.css') }}">
<link rel="stylesheet" href="{{ asset('css/admin_staff_list.css') }}">
@endsection

@section('content')
<div class="staff-container">
    <h1 class="page-title">スタッフ一覧</h1>

    <div class="staff-card">
        <table class="staff-table">
            <thead>
                <tr>
                    <th>名前</th>
                    <th>メールアドレス</th>
                    <th>月次勤怠</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($users as $user)
                    <tr>
                        <td>{{ $user->name }}</td>
                        <td>{{ $user->email }}</td>
                        <td>
                            <a href="{{ route('admin_user_attendance.index', ['user' => $user->id]) }}" class="btn-detail">
                                詳細
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="3" class="no-data">ユーザーが登録されていません</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
