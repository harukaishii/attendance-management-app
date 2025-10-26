@extends('layouts.header')

@section('css')
<link rel="stylesheet" href="{{ asset('css/sanitize.css') }}">
<link rel="stylesheet" href="{{ asset('css/header.css') }}">
<link rel="stylesheet" href="{{ asset('css/admin_application_detail.css') }}">
@endsection

@section('content')
<div class="detail-container">
    <h1 class="page-title">勤怠詳細</h1>

    <div class="detail-card">
        <table class="detail-table">
            <tr>
                <th class="label">名前</th>
                <td>{{ $user->name }}</td>
            </tr>
            <tr>
                <th class="label">日付</th>
                <td>{{ \Carbon\Carbon::parse($attendance->date)->format('Y年m月d日') }}</td>
            </tr>

            <tr>
                <th class="label">出勤・退勤</th>
                <td>
                    <div class="time-group">
                        <span class="time-display">{{ \Carbon\Carbon::parse($attendance->start_time)->format('H:i') }}</span>
                        <span class="separator">~</span>
                        <span class="time-display">{{ $attendance->end_time ? \Carbon\Carbon::parse($attendance->end_time)->format('H:i') : '-' }}</span>
                    </div>
                </td>
            </tr>

            @foreach ($breaktimes as $index => $break)
                <tr class="break-row">
                    <th class="label">休憩{{ $index + 1 }}</th>
                    <td>
                        <div class="time-group">
                            <span class="time-display">{{ \Carbon\Carbon::parse($break->start_time)->format('H:i') }}</span>
                            <span class="separator">~</span>
                            <span class="time-display">{{ $break->end_time ? \Carbon\Carbon::parse($break->end_time)->format('H:i') : '--:--' }}</span>
                        </div>
                    </td>
                </tr>
            @endforeach

            <tr>
                <th class="label">備考</th>
                <td>{{ $attendance->note ?? '（備考なし）' }}</td>
            </tr>
        </table>
    </div>

    <div class="button-wrapper">
        @if ($attendance->status->value === \App\Enums\AttendanceStatus::Unapproved->value)
            <button type="button" class="button-approve" id="approveBtn">承認</button>
        @else
            <button type="button" class="button-approved" disabled style="background-color: #696969; cursor: not-allowed;">承認済み</button>
        @endif
    </div>
</div>
@endsection

@section('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    const approveBtn = document.getElementById('approveBtn');

    if (approveBtn) {
        approveBtn.addEventListener('click', function() {
            if (!confirm('この申請を承認しますか？')) {
                return;
            }

            // ボタンを無効化
            approveBtn.disabled = true;
            approveBtn.textContent = '承認中...';

            // 承認リクエストを送信
            fetch('{{ route("admin_request.approve", ["id" => $attendance->id]) }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('承認しました');

                    // ボタンを「承認済み」に変更
                    approveBtn.textContent = '承認済み';
                    approveBtn.style.backgroundColor = '#696969';
                    approveBtn.style.cursor = 'not-allowed';
                    approveBtn.disabled = true;

                    // リダイレクトを削除（画面に留まる）
                } else {
                    alert('承認に失敗しました');
                    approveBtn.disabled = false;
                    approveBtn.textContent = '承認';
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('エラーが発生しました');
                approveBtn.disabled = false;
                approveBtn.textContent = '承認';
            });
        });
    }
});
</script>
@endsection
