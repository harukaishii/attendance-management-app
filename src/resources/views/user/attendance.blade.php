@extends('layouts.header')

@section('css')
<link rel="stylesheet" href="{{ asset('css/sanitize.css') }}">
<link rel="stylesheet" href="{{ asset('css/header.css') }}">
<link rel="stylesheet" href="{{ asset('css/attendance.css') }}">
@endsection

@section('content')
<div class="main-content">
    <div class="card">
        @if(session('success'))
            <div class="message-box message-success">
                {{session('success')}}
            </div>
        @endif

        @if(session('error'))
            <div class="message-box message-error">
                {{session('error')}}
            </div>
        @endif
        <div class="status-label__wrapper">
        @if ($attendanceStatus === 'initial')
            <div class="status-label">勤務外</div>
        @elseif ($attendanceStatus === 'working')
            <div class="status-label">出勤中</div>
        @elseif ($attendanceStatus === 'onBreak')
            <div class="status-label">休憩中</div>
        @elseif ($attendanceStatus === 'finished')
            <div class="status-label">退勤済</div>
        @endif
        </div>

       <div class="timestamp-display">
            <p class="date">{{ $todayFormatted }}</p>
            <p class="time">{{ \Carbon\Carbon::now()->format('H:i') }}</p>
        </div>

        <div class="button-section">
            @if ($attendanceStatus === 'initial')
                <form action="{{ route('attendance.start') }}" method="POST" class="form-action">
                    @csrf
                    <button type="submit" class="button button-primary">出勤</button>
                </form>

            @elseif ($attendanceStatus === 'working')
                <div class="button-group">
                    <form action="{{ route('attendance.end') }}" method="POST" class="form-action">
                        @csrf
                        <button type="submit" class="button button-primary">退勤</button>
                    </form>
                    <form action="{{ route('break.start') }}" method="POST" class="form-action">
                        @csrf
                        <button type="submit" class="button button-secondary">休憩入</button>
                    </form>
                </div>

            @elseif ($attendanceStatus === 'onBreak')
                <form action="{{ route('break.end') }}" method="POST" class="form-action">
                    @csrf
                    <button type="submit" class="button button-secondary">休憩戻</button>
                </form>

            @elseif ($attendanceStatus === 'finished')
                <p class="message">お疲れ様でした。</p>
            @endif
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
    const messageBoxes = document.querySelectorAll('.message-box');
    messageBoxes.forEach(messageBox => {
        if (messageBox) {
            setTimeout(() => {
                messageBox.style.transition = 'opacity 0.5s';
                messageBox.style.opacity = '0';
                setTimeout(() => {
                    messageBox.remove();
                }, 500);
            }, 3000);
        }
    });
</script>
@endsection
