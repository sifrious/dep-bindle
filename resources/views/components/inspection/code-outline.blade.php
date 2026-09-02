@php($renderSymbols = function ($symbols) use (&$renderSymbols) {
    $html = '<ul class="bindle-record-list">';
    foreach ($symbols as $symbol) {
        $location = e($symbol->source->relativePath.':'.$symbol->source->startLine);
        $label = e($symbol->kind.' '.$symbol->name);
        $html .= '<li><code>'.$label.'</code> <small>'.$location.'</small>';
        if ($symbol->children !== []) {
            $html .= '<details open><summary>'.count($symbol->children).' members</summary>'.$renderSymbols($symbol->children).'</details>';
        }
        $html .= '</li>';
    }
    return $html.'</ul>';
})
<section aria-labelledby="{{ $id }}-title">
    <h2 id="{{ $id }}-title">Code outline</h2>
    @if($snapshot->partial)<p>Partial outline; some inspection evidence was truncated.</p>@endif
    @if($snapshot->symbols === [])<p>No symbols were found.</p>@else{!! $renderSymbols($snapshot->symbols) !!}@endif
</section>
