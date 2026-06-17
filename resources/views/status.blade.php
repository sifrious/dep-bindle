@extends('bindle::layout')

@php($isRunning = $run !== null && $run->status === 'running')

@section('head')
    @if ($isRunning)
        {{-- Pure-HTML polling: reload this page until the run completes. --}}
        <meta http-equiv="refresh" content="{{ $pollSeconds }};url={{ route('bindle.panel.status', ['run' => $run->id]) }}">
    @endif
@endsection

@section('content')
    <header>
        <h1>Scan status</h1>
        <a href="{{ route('bindle.panel.index') }}">&larr; Back to panel</a>
    </header>

    @if ($run === null)
        <p class="muted">No scans have run yet.</p>
        <form method="POST" action="{{ route('bindle.panel.scan-all') }}" class="toolbar">
            @csrf
            <button type="submit">Run full scan</button>
        </form>
    @else
        <table>
            <tbody>
                <tr>
                    <th>Run</th>
                    <td>#{{ $run->id }}</td>
                </tr>
                <tr>
                    <th>Status</th>
                    <td><span class="badge {{ $run->status }}">{{ $run->status }}</span></td>
                </tr>
                <tr>
                    <th>Started</th>
                    <td>{{ $run->started_at ?? '—' }}</td>
                </tr>
                <tr>
                    <th>Finished</th>
                    <td>{{ $run->finished_at ?? '—' }}</td>
                </tr>
            </tbody>
        </table>

        @if ($isRunning)
            <p class="muted toolbar">Refreshing every {{ $pollSeconds }}s until the scan completes…</p>
        @else
            <p class="toolbar">
                <a href="{{ route('bindle.panel.index') }}">View results &rarr;</a>
            </p>
        @endif
    @endif
@endsection
