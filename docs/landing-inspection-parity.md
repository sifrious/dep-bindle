# Landing inspection parity

Bindle owns the migrated inspection behavior. Stacks contributes an
`InspectionRequest` containing workspace identity and checkout context; it does
not persist a second symbol, resource, or graph index.

| Landing story | Bindle replacement | Evidence |
|---|---|---|
| `landing.web.files.symbols` | `InspectionProvider` file-scoped request and typed `CodeSymbol`/`SourceLocation` results | `PhpInspectionProviderTest` distinguishes unavailable, empty, and populated evidence |
| `landing.cli.code.symbols.reindex.resources` | workspace-scoped Blade and Inertia discovery | resource discovery test preserves workspace and relative-path provenance |
| `landing.cli.code.symbols.reindex.routes` | existing `RouteEnumerator` plus `RouteList`; code relationships remain inspection evidence | component test renders route method, URI, name, and controller action |
| `landing.cli.code.symbols.reindex.controllers` | PHP inspection of controller classes and methods; `InspectionService` batches one or many workspace requests | provider and batch-service tests |
| `landing.web.inspections.diagram` | typed `StructuralRelationship` evidence and `DependencyDiagram` textual/visual projection | component test requires figure, caption, semantic evidence table, and revision context |

The default adapter reads a checkout and does not persist its findings. A future
language or remote analyzer implements the same provider contract and returns the
same provider-neutral evidence objects.
