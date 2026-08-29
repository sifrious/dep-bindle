@extends('bindle::layout')

@section('content')
    <header>
        <div>
            <h1>Wireframe board</h1>
            <p class="muted">
                Documents-first capture board: named regions, explicit states, and implementation notes.
            </p>
        </div>
        <a href="{{ route('bindle.panel.index') }}">&larr; Back to inventory</a>
    </header>

    <p class="toolbar">
        @foreach ($stateOptions as $state)
            <a
                href="{{ route('bindle.panel.wireframe-board', ['state' => $state]) }}"
                class="state-link {{ $activeState === $state ? 'active' : '' }}"
            >
                {{ ucfirst($state) }}
            </a>
        @endforeach
    </p>

    @if ($showFallbackNotice)
        <div class="banner">
            Unknown state requested. Showing <strong>empty</strong> as the safe default.
        </div>
    @endif

    <h2>
        Board state:
        <span class="badge state-{{ $activeState }}">{{ ucfirst($activeState) }}</span>
    </h2>
    <p class="muted">
        This board is static HTML+CSS by design (no canvas, no Figma-clone behavior, no client-side drawing state).
    </p>

    <section class="board-grid" aria-label="Wireframe regions">
        @foreach ($regions as $region)
            <article class="region-card state-{{ $activeState }}">
                <h3>{{ $region['name'] }}</h3>
                <p><strong>Role:</strong> {{ $region['role'] }}</p>
                <p><strong>State note:</strong> {{ $region['note'] }}</p>
            </article>
        @endforeach
    </section>

    <h2>State definitions</h2>
    <table>
        <thead>
            <tr>
                <th>State</th>
                <th>Meaning</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td><span class="badge state-empty">Empty</span></td>
                <td>Region exists but has no mapped capture content yet.</td>
            </tr>
            <tr>
                <td><span class="badge state-loading">Loading</span></td>
                <td>Capture details are expected but still being prepared.</td>
            </tr>
            <tr>
                <td><span class="badge state-error">Error</span></td>
                <td>Region mapping failed; notes should describe the failure and fallback.</td>
            </tr>
            <tr>
                <td><span class="badge state-populated">Populated</span></td>
                <td>Region has capture-backed detail and documentable structure.</td>
            </tr>
        </tbody>
    </table>
@endsection
