@extends('layouts.common')

@section('css')
<link rel="stylesheet" href="{{ asset('css/sanitize.css') }}">
<link rel="stylesheet" href="{{ asset('css/common.css') }}">
<link rel="stylesheet" href="{{ asset('css/verify_email.css') }}">
@endsection

@section('content')

<div class="verify-comment__wrapper">
    <p class="verify-comment">
        登録していただいたメールアドレスに認証メールを送付しました。<br>
        メール認証を完了させてください。
    </p>

    <form method="POST" action="{{ route('verification.send') }}" class="verify-send">
        @csrf
        <button class="verify-send__button" type="submit">
                認証はこちらから
        </button>
    </form>

    <div class="verify-again__link">
        <a href="">認証メールを再送する</a>
    </div>
</div>

@endsection
