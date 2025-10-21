<!DOCTYPE html>
<html lang="ja">

<head>
  <meta charset="UTF-8">
  <meta http-equiv="X-UA-Compatible" content="IE=edge">
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>COACH TECH</title>
  <link rel="stylesheet" href="{{ asset('css/sanitize.css') }}">
  <link rel="stylesheet" href="{{ asset('css/header.css') }}">
  @yield('css')
</head>

<body>
    <header class="header">
        <div class="header__inner">
            <div class="header__logo">
                @auth
                    @if (auth()->user()->is_admin)
                        <a href="{{ route('admin_attendance_list.index')}}">
                            <img src="{{ asset('images/logo.png') }}" alt="header_logo">
                        </a>
                    @else
                        <a href="{{ route('attendance.index')}}">
                            <img src="{{ asset('images/logo.png') }}" alt="header_logo">
                        </a>
                    @endif
                @else
                    <a href="/">
                        <img src="{{ asset('images/logo.png') }}" alt="header_logo">
                    </a>
                @endauth
            </div>
            <nav class="header__nav">
                <ul class="header__nav-list">
                    @auth
                        {{-- 管理者ナビメニュー --}}
                        @if (auth()->user()->is_admin)
                            <li class="header__nav-item">
                                <a href="{{ route('admin_attendance_list.index')}}" class="header__nav-link">勤怠一覧</a>
                            </li>
                            <li class="header__nav-item">
                                <a href="{{ route('admin_staff.index')}}" class="header__nav-link">スタッフ一覧</a>
                            </li>
                            <li class="header__nav-item">
                                <a href="{{ route('admin_request.index')}}" class="header__nav-link">申請一覧</a>
                            </li>
                            <li class="header__nav-item">
                                <a href="#" class="header__nav-link"
                                onclick="event.preventDefault(); document.getElementById('logout-form').submit();">
                                    ログアウト
                                </a>
                                <form id="logout-form" action="{{ route('admin.logout') }}" method="POST" style="display: none;">
                                    @csrf
                                </form>
                            </li>
                        {{-- 一般ユーザーナビメニュー --}}
                        @else
                            <li class="header__nav-item">
                                <a href="{{ route('attendance.index')}}" class="header__nav-link">勤怠</a>
                            </li>
                            <li class="header__nav-item">
                                <a href="{{ route('attendance.list.index')}}" class="header__nav-link">勤怠一覧</a>
                            </li>
                            <li class="header__nav-item">
                                <a href="{{ route('stamp_correction.index')}}" class="header__nav-link">申請</a>
                            </li>
                            <li class="header__nav-item">
                                <a href="#" class="header__nav-link"
                                onclick="event.preventDefault(); document.getElementById('logout-form').submit();">
                                    ログアウト
                                </a>
                                <form id="logout-form" action="{{ route('logout') }}" method="POST" style="display: none;">
                                    @csrf
                                </form>
                            </li>
                        @endif
                    @endauth
                </ul>
            </nav>
        </div>
    </header>


    <main>
        @yield('content')
        @yield('scripts')
    </main>
</body>
</html>
