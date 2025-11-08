@extends('layouts.header')

@section('css')
<link rel="stylesheet" href="{{ asset('css/sanitize.css') }}">
<link rel="stylesheet" href="{{ asset('css/header.css') }}">
<link rel="stylesheet" href="{{ asset('css/verify_email.css') }}">
@endsection

@section('content')

<div class="verify-comment__wrapper">
    {{-- (メール再送成功時のメッセージはそのまま) --}}
    @if (session('status') == 'verification-link-sent')
        <div class="alert alert-success">
            新しい認証リンクをメールアドレスに送信しました。
        </div>
    @endif

    <p class="verify-comment">
        登録していただいたメールアドレスに認証メールを送付しました。<br>
        メール認証を完了させてください。
    </p>

    <div class="mailhog-link__wrapper">
        <a href="http://127.0.0.1:8025" target="_blank" class="mailhog-link__button">
            認証メールはこちらから確認
        </a>
    </div>

    <div class="verify-again__link">
        <form method="POST" action="{{ route('verification.send') }}">
            @csrf
            <button type="submit" class="verify-send__button">
                認証メールを再送する
            </button>
        </form>
    </div>
</div>

@endsection
