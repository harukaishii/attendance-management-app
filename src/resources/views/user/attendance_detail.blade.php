@extends('layouts.header')

@section('css')
<link rel="stylesheet" href="{{ asset('css/sanitize.css') }}">
<link rel="stylesheet" href="{{ asset('css/header.css') }}">
<link rel="stylesheet" href="{{ asset('css/attendance_detail.css') }}">
@endsection

@section('content')
<div class="detail-container">
    <h1 class="page-title">勤怠詳細</h1>

    <form action="{{ route('attendance.detail.update', ['id' => $attendance->id]) }}" method="POST" class="detail-form">
        @csrf

        {{-- ステータスをhiddenで渡す --}}
        <input type="hidden" name="current_status" value="{{ $attendance->status->value }}">

        <div class="detail-card">

            <table class="detail-table">
                <tr>
                    <th class="label">名前</th>
                    <td>{{ Auth::user()->name }}</td>
                </tr>
                <tr>
                    <th class="label">日付</th>
                    <td>{{ \Carbon\Carbon::parse($attendance->date)->format('Y年m月d日') }}</td>
                </tr>

                <tr>
                    <th class="label">出勤・退勤</th>
                        <td>
                            <div class="time-group">
                                <div class="time-field">
                                    @if ($isEditable)
                                        <input type="time" name="start_time" value="{{ \Carbon\Carbon::parse($attendance->start_time)->format('H:i') }}" class="time-input">
                                    @else
                                        <span class="time-display">{{ \Carbon\Carbon::parse($attendance->start_time)->format('H:i') }}</span>
                                    @endif
                                    <div class="form__error @error('start_time') error-has-message @enderror">
                                        @error('start_time')
                                            {{ $message }}
                                        @enderror
                                    </div>
                                </div>

                                <span class="separator">~</span>

                                <div class="time-field">
                                    @if ($isEditable)
                                        <input type="time" name="end_time" value="{{ $attendance->end_time ? \Carbon\Carbon::parse($attendance->end_time)->format('H:i') : '' }}" class="time-input">
                                    @else
                                        <span class="time-display">{{ $attendance->end_time ? \Carbon\Carbon::parse($attendance->end_time)->format('H:i') : '-' }}</span>
                                    @endif
                                    <div class="form__error @error('end_time') error-has-message @enderror">
                                        @error('end_time')
                                            {{ $message }}
                                        @enderror
                                    </div>
                                </div>
                            </div>
                        </td>
                </tr>

                @php
                    $breakCount = $breaktimes->count();
                    $displayCount = $breakCount + 1;
                @endphp

                @for ($i = 0; $i < $displayCount; $i++)
                    @php
                        $break = $breaktimes->get($i);
                        $breakStart = $break ? \Carbon\Carbon::parse($break->start_time)->format('H:i') : '';
                        $breakEnd = $break ? ($break->end_time ? \Carbon\Carbon::parse($break->end_time)->format('H:i') : '') : '';
                        if ($break === null && $i < $breakCount) {
                            continue;
                        }
                    @endphp

                    <tr class="break-row">
                        <th class="label">休憩{{ $i + 1 }}</th>
                        <td>
                            <div class="time-group">
                                <div class="time-field">
                                    @if ($isEditable)
                                        <input type="time" name="breaks[{{ $i }}][start]" value="{{ $breakStart }}" class="time-input">
                                    @else
                                        <span class="time-display">{{ $breakStart ?: '--:--' }}</span>
                                    @endif
                                    <div class="form__error @error("breaks.{$i}.start") error-has-message @enderror">
                                        @error("breaks.{$i}.start")
                                            {{ $message }}
                                        @enderror
                                    </div>
                                </div>

                                <span class="separator">~</span>

                                <div class="time-field">
                                    @if ($isEditable)
                                        <input type="time" name="breaks[{{ $i }}][end]" value="{{ $breakEnd }}" class="time-input">
                                    @else
                                        <span class="time-display">{{ $breakEnd ?: '--:--' }}</span>
                                    @endif
                                    <div class="form__error @error("breaks.{$i}.end") error-has-message @enderror">
                                        @error("breaks.{$i}.end")
                                            {{ $message }}
                                        @enderror
                                    </div>
                                </div>
                            </div>
                        </td>
                    </tr>
                @endfor

                <tr>
                    <th class="label">備考</th>
                    <td>
                        @if ($isEditable)
                            <textarea name="note" class="note-input" placeholder="修正理由などの備考">{{ $request->note ?? $note ?? '' }}</textarea>
                        <div class="form__error @error('note') error-has-message @enderror">
                            @error('note')
                                {{ $message }}
                            @enderror
                        </div>
                        @else
                            {{ $note ?? '（備考なし）' }}
                        @endif
                    </td>
                </tr>
            </table>
        </div>

            @if (!$isEditable)
                <p class="error-message">
                    *承認待ちのため修正できません。
                </p>
            @endif

        @if ($isEditable)
            <button type="submit" class="button-submit">修正</button>
        @endif
    </form>
</div>
@endsection

@section('scripts')
<script>
</script>
@endsection
