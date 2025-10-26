@extends('layouts.header')

@section('css')
<link rel="stylesheet" href="{{ asset('css/sanitize.css') }}">
<link rel="stylesheet" href="{{ asset('css/header.css') }}">
<link rel="stylesheet" href="{{ asset('css/admin_login.css') }}">
@endsection

@section('content')
<div class="login-form__content">
    <div class="login-form__heading">
        <h2>管理者ログイン</h2>
    </div>
    <form class="form" action="{{ route('admin.login') }}" method="POST">
        @csrf
        <div class="form__group-title">
            <span class="form__label--item">メールアドレス</span>
        </div>
        <div class="form__group-content">
            <div class="form__input--text">
                <input type="email" name="email" value="{{old('email')}}" />
            </div>
            <div class="form__error">
                @error('email')
                {{$message}}
                @enderror
            </div>
        </div>
                <div class="form__group-title">
            <span class="form__label--item">パスワード</span>
        </div>
        <div class="form__group-content">
            <div class="form__input--text">
                <input type="password" name="password" value="" />
            </div>
            <div class="form__error">
                @error('password')
                {{$message}}
                @enderror
            </div>
        </div>
        <div class="form__button">
            <button class="form__button-submit">管理者ログインする</button>
        </div>

    </form>
</div>


@endsection
