@extends('layouts.header')

@section('css')
<link rel="stylesheet" href="{{ asset('css/sanitize.css') }}">
<link rel="stylesheet" href="{{ asset('css/header.css') }}">
<link rel="stylesheet" href="{{ asset('css/admin_application_list.css') }}">
@endsection

@section('content')
<div class="list-container">
    <h1 class="page-title">申請一覧</h1>

    <div class="navigation">
        <nav class="main-nav">
            <ul class="main-nav__list">
                <li class="main-nav__item">
                    <a href="{{ route('admin_request.index', ['status' => 'pending']) }}"
                       class="main-nav__link @if ($status === 'pending') is-active @endif">
                        承認待ち
                    </a>
                </li>
                <li class="main-nav__item">
                    <a href="{{ route('admin_request.index', ['status' => 'approved']) }}"
                       class="main-nav__link @if ($status === 'approved') is-active @endif">
                        承認済み
                    </a>
                </li>
            </ul>
        </nav>
    </div>

    <table class="attendance-table">
        <thead>
            <tr>
                <th class="status-col">状態</th>
                <th>名前</th>
                <th class="date-col">対象日時</th>
                <th>申請理由</th>
                <th class="date-col">申請日時</th>
                <th class="detail-col">詳細</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($applications as $application)
            <tr>
                <td class="status-col">{{ $application->status->label() }}</td>

                <td>{{ $application->user->name ?? 'ユーザー名不明' }}</td>

                <td class="date-col">{{ \Carbon\Carbon::parse($application->date)->format('Y/m/d') }}</td>

                <td class="note-col">{{ $application->note ?? '-' }}</td>

                <td class="date-col">{{ $application->updated_at->format('Y/m/d') }}</td>

                <td class="detail-col">
                    <a href="{{ route('admin_request.show', ['id' => $application->id]) }}" class="detail-link">
                        詳細
                    </a>
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="6" class="no-data">該当する申請はありません。</td>
            </tr>
            @endforelse
        </tbody>
    </table>
</div>
@endsection

@section('scripts')
<script>
</script>
@endsection
