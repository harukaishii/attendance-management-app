@extends('layouts.header')

@section('css')
<link rel="stylesheet" href="{{ asset('css/sanitize.css') }}">
<link rel="stylesheet" href="{{ asset('css/header.css') }}">
<link rel="stylesheet" href="{{ asset('css/attendance_list.css') }}">
@endsection

@section('content')
<div class="list-container">
    <h1 class="page-title">勤怠一覧</h1>

     @if (session('success'))
    <div class="success-message" id="successMessage">
        {{ session('success') }}
    </div>
    @endif

    <div class="month-navigation">
        <a href="{{ $prevMonthUrl }}" class="arrow-link">
            <img src="{{ asset('images/arrow-left.png') }}" alt="前月へ" class="arrow-icon">
            前月
        </a>

        <h2 class="current-month">
            <img src="{{ asset('images/calendar.png') }}" alt="カレンダー" class="calendar-icon">
            <span>{{ $currentMonth->format('Y/m') }}</span>
        </h2>

        <a href="{{ $nextMonthUrl }}" class="arrow-link">
           翌月
            <img src="{{ asset('images/arrow-right.png') }}" alt="翌月へ" class="arrow-icon">
        </a>
    </div>

    <table class="attendance-table">
        <thead>
            <tr>
                <th class="date-col">日付</th>
                <th>出勤</th>
                <th>退勤</th>
                <th>休憩</th>
                <th>合計</th>
                <th class="detail-col">詳細</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($calendar as $data)
            <tr @if ($data['isFuture'] && $data['id']) class="future-date" @endif>
                <td class="date-col">{{ $data['date'] }}</td>
                <td>{{ $data['start'] ?? '-' }}</td>
                <td>{{ $data['end'] ?? '-' }}</td>
                <td>{{ $data['break'] ?? '-' }}</td>
                <td>{{ $data['total'] ?? '-' }}</td>
                <td class="detail-col">
                    @if (!$data['isFuture'] && $data['start']) <a href="{{ route('attendance.detail.show', ['id' => $data['id']]) }}" class="detail-link">詳細</a>
                @else
                    -
                @endif
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>
</div>
@endsection

@section('scripts')
<script>
    // 成功メッセージを3秒後に自動で消す
    const successMessage = document.getElementById('successMessage');
    if (successMessage) {
        setTimeout(() => {
            successMessage.style.transition = 'opacity 0.5s';
            successMessage.style.opacity = '0';
            setTimeout(() => {
                successMessage.remove();
            }, 500);
        }, 3000); // 3秒後にフェードアウト開始
    }
</script>
@endsection
