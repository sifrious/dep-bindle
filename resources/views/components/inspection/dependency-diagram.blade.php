<figure aria-labelledby="{{ $id }}-caption">
    <figcaption id="{{ $id }}-caption">Structural relationships</figcaption>
    @if($snapshot->partial)<p>Partial graph; filters or inspection limits excluded some relationships.</p>@endif
    @if($snapshot->relationships === [])
        <p>No structural relationships were found.</p>
    @else
        <table class="bindle-record-table">
            <caption>Textual equivalent of the structural diagram</caption>
            <thead><tr><th scope="col">Source</th><th scope="col">Relationship</th><th scope="col">Target</th><th scope="col">Evidence</th></tr></thead>
            <tbody>@foreach($snapshot->relationships as $edge)<tr><td><code>{{ $edge->from }}</code></td><td>{{ $edge->type }}</td><td><code>{{ $edge->to }}</code></td><td><code>{{ $edge->evidence->relativePath }}:{{ $edge->evidence->startLine }}</code></td></tr>@endforeach</tbody>
        </table>
    @endif
</figure>
