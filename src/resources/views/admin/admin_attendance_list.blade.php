@extends('layouts.header')

@section('css')
<link rel="stylesheet" href="{{ asset('css/sanitize.css') }}">
<link rel="stylesheet" href="{{ asset('css/header.css') }}">
<link rel="stylesheet" href="{{ asset('css/admin_attendance_list.css') }}">
@endsection

@section('content')
<div class="list-container">
    <h1 class="page-title">{{ \Carbon\Carbon::parse($today)->format('Y年m月d日') }}の勤怠</h1>

    @if (session('success'))
    <div class="success-message" id="successMessage">
        {{ session('success') }}
    </div>
    @endif

    <div class="month-navigation">
        <a href="{{ route('admin_attendance_list.index', ['date' => \Carbon\Carbon::parse($today)->subDay()->format('Y-m-d')]) }}" class="arrow-link">
            <img src="{{ asset('images/arrow-left.png') }}" alt="前日へ" class="arrow-icon">
            前日
        </a>

        <h2 class="current-month">
            <img src="{{ asset('images/calendar.png') }}" alt="カレンダー" class="calendar-icon">
            <span>{{ \Carbon\Carbon::parse($today)->format('Y/m/d') }}</span>
        </h2>

        <a href="{{ route('admin_attendance_list.index', ['date' => \Carbon\Carbon::parse($today)->addDay()->format('Y-m-d')]) }}" class="arrow-link">
           翌日
            <img src="{{ asset('images/arrow-right.png') }}" alt="翌日へ" class="arrow-icon">
        </a>
    </div>

    <table class="attendance-table">
        <thead>
            <tr>
                <th class="date-col">名前</th>
                <th>出勤</th>
                <th>退勤</th>
                <th>休憩</th>
                <th>合計</th>
                <th class="detail-col">詳細</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($attendanceData as $data)
                @if ($data['start'] || $data['end'])
                <tr>
                    <td class="date-col">{{ $data['name'] }}</td>
                    <td>{{ $data['start'] ?? '-' }}</td>
                    <td>{{ $data['end'] ?? '-' }}</td>
                    <td>{{ $data['break'] ?? '-' }}</td>
                    <td>{{ $data['total'] ?? '-' }}</td>
                    <td class="detail-col">
                        @if ($data['start'])
                            <a href="{{ route('admin_attendance_detail.show', ['id' => $data['id']]) }}" class="detail-link">詳細</a>
                        @else
                            -
                        @endif
                    </td>
                </tr>
                @endif
            @empty
                <tr>
                    <td colspan="6" style="text-align: center; padding: 40px;">
                        当日の勤怠データはありません
                    </td>
                </tr>
            @endforelse
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
