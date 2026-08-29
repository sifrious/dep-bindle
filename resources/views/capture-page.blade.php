@extends('bindle::layout')

@php($isPlaceholderRun = $run === null || ! $run->driverKind()->producesRealScreenshots())

@section('content')
    <header>
        <div>
            <h1>Capture detail</h1>
            <p class="muted">
                {{ $page->route_name ?? $page->slug }} · {{ $page->http_method }} {{ $page->uri }}
            </p>
        </div>
        <a href="{{ route('bindle.panel.index') }}">&larr; Back to inventory</a>
    </header>

    <div class="toolbar">
        <span class="badge {{ $isPlaceholderRun ? 'driver-null' : 'driver-dusk' }}">
            {{ $run?->driverKind()->label() ?? 'unknown driver' }}
        </span>
        <span class="muted">Run #{{ $page->run_id }}</span>
    </div>

    @if ($isPlaceholderRun)
        <div class="banner">
            <strong>Placeholder capture.</strong>
            This run used the placeholder driver, so screenshot files are 1x1 placeholders and DOM capture is empty.
        </div>
    @endif

    <h2>Screenshots</h2>
    <div class="capture-grid">
        @foreach ([
            'Desktop' => $desktopScreenshot,
            'Mobile' => $mobileScreenshot,
        ] as $label => $shot)
            <section class="capture-card">
                <h3>{{ $label }}</h3>
                @if ($shot === null)
                    <p class="muted">No screenshot recorded.</p>
                @elseif ($isPlaceholderRun)
                    <div class="placeholder-shot">
                        <strong>Placeholder screenshot</strong><br>
                        <span class="muted">{{ $shot->viewport_w }}×{{ $shot->viewport_h }} · {{ $shot->viewport_label ?? 'viewport' }}</span><br>
                        <code>{{ $shot->path }}</code>
                    </div>
                @else
                    <img
                        src="{{ route('bindle.panel.captures.screenshot', ['screenshot' => $shot->id]) }}"
                        alt="{{ $label }} screenshot for {{ $page->route_name ?? $page->slug }}"
                        class="capture-image"
                    >
                    <p class="muted">{{ $shot->viewport_w }}×{{ $shot->viewport_h }} · {{ $shot->viewport_label ?? 'viewport' }}</p>
                @endif
            </section>
        @endforeach
    </div>

    <h2>Variants &amp; props</h2>
    @if ($componentsOnPage->isEmpty())
        <p class="muted">No components were linked to this capture.</p>
    @else
        <table>
            <thead>
            <tr>
                <th>Component</th>
                <th>Depth</th>
                <th>Props passed on page</th>
                <th>Known prop signatures</th>
                <th>Known variants</th>
            </tr>
            </thead>
            <tbody>
            @foreach ($componentsOnPage as $item)
                @php($component = $item->component)
                <tr>
                    <td><strong>{{ $component?->name ?? 'unknown' }}</strong></td>
                    <td>{{ $item->depth }}</td>
                    <td><pre>{{ json_encode($item->props_passed ?? new stdClass(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre></td>
                    <td>
                        @if ($component === null || $component->props->isEmpty())
                            <span class="muted">none</span>
                        @else
                            @foreach ($component->props as $prop)
                                <div>
                                    <code>{{ $prop->name }}</code>
                                    <span class="muted">{{ $prop->type ?? 'unknown type' }} · {{ $prop->required ? 'required' : 'optional' }}</span>
                                </div>
                            @endforeach
                        @endif
                    </td>
                    <td>
                        @if ($component === null || $component->variants->isEmpty())
                            <span class="muted">none</span>
                        @else
                            @foreach ($component->variants as $variant)
                                <div>
                                    <strong>{{ $variant->variant_name }}</strong>
                                    <pre>{{ json_encode($variant->props_combo, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
                                </div>
                            @endforeach
                        @endif
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    @endif

    <h2>DOM / semantic snippet</h2>
    <table>
        <tbody>
        <tr>
            <th>DOM fingerprint</th>
            <td><code>{{ $page->html_hash ?? 'not captured' }}</code></td>
        </tr>
        <tr>
            <th>Semantic summary</th>
            <td>
                @if ($semanticSummary === null)
                    <span class="muted">No generated summary found for this capture.</span>
                @else
                    <pre>{{ $semanticSummary }}</pre>
                @endif
            </td>
        </tr>
        </tbody>
    </table>

    <h2>Accessibility notes</h2>
    <ul>
        @foreach ($accessibilityNotes as $note)
            <li>{{ $note }}</li>
        @endforeach
    </ul>

    <h2>Scan errors</h2>
    @if ($errors->isEmpty())
        <p class="muted">No run/page errors were recorded for this capture.</p>
    @else
        <table>
            <thead>
            <tr>
                <th>Severity</th>
                <th>Phase</th>
                <th>Message</th>
            </tr>
            </thead>
            <tbody>
            @foreach ($errors as $error)
                <tr>
                    <td><span class="badge running">{{ $error->severity }}</span></td>
                    <td>{{ $error->phase }}</td>
                    <td>{{ $error->message }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    @endif
@endsection
