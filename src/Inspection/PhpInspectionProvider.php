<?php

declare(strict_types=1);

namespace Maryeperry\Bindle\Inspection;

use DateTimeImmutable;
use Maryeperry\Bindle\Inspection\Domain\CodeSymbol;
use Maryeperry\Bindle\Inspection\Domain\DiscoveredResource;
use Maryeperry\Bindle\Inspection\Domain\InspectionRequest;
use Maryeperry\Bindle\Inspection\Domain\InspectionSnapshot;
use Maryeperry\Bindle\Inspection\Domain\InspectionState;
use Maryeperry\Bindle\Inspection\Domain\SourceLocation;
use Maryeperry\Bindle\Inspection\Domain\StructuralRelationship;
use PhpParser\Node;
use PhpParser\NodeFinder;
use PhpParser\ParserFactory;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Throwable;

final class PhpInspectionProvider extends AbstractInspectionProvider
{
    public function inspect(InspectionRequest $request): InspectionSnapshot
    {
        $workspaceId = $request->workspaceId;
        $relativePath = $request->relativePath;
        $root = realpath($request->rootPath);
        if ($root === false) {
            return $this->unavailable($workspaceId, $relativePath ?? '.', 'Workspace is unavailable.');
        }

        $target = $relativePath === null ? $root : $this->absolutePath($root, $relativePath);
        if ($target === null) {
            return $this->unavailable($workspaceId, $relativePath ?? '.', 'File is unavailable or outside the workspace.');
        }

        try {
            $files = is_file($target) ? [$target] : $this->files($target);
            $symbols = [];
            $resources = [];
            $relationships = [];
            foreach ($files as $file) {
                $path = $this->relativePath($root, $file);
                $resource = $this->resource($workspaceId, $path);
                if ($resource !== null) {
                    $resources[] = $resource;
                }
                if (str_ends_with($file, '.php')) {
                    [$fileSymbols, $fileRelationships] = $this->phpEvidence($workspaceId, $path, $file);
                    array_push($symbols, ...$fileSymbols);
                    array_push($relationships, ...$fileRelationships);
                }
            }

            $state = ($symbols === [] && $resources === [] && $relationships === [])
                ? InspectionState::Empty
                : InspectionState::Available;

            return new InspectionSnapshot($workspaceId, $relativePath ?? '.', $state, new DateTimeImmutable, $symbols, $resources, $relationships, revision: $request->revision);
        } catch (Throwable $exception) {
            return $this->unavailable($workspaceId, $relativePath ?? '.', $exception->getMessage());
        }
    }

    /** @return list<string> */
    private function files(string $root): array
    {
        $files = [];
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, RecursiveDirectoryIterator::SKIP_DOTS)) as $file) {
            if ($file instanceof SplFileInfo && $file->isFile() && ! str_contains($file->getPathname(), DIRECTORY_SEPARATOR.'vendor'.DIRECTORY_SEPARATOR)) {
                $files[] = $file->getPathname();
            }
        }

        sort($files);

        return $files;
    }

    private function resource(string $workspaceId, string $path): ?DiscoveredResource
    {
        $kind = match (true) {
            str_ends_with($path, '.blade.php') => 'blade',
            preg_match('#(^|/)resources/js/Pages/.+\.(vue|jsx|tsx|svelte)$#i', $path) === 1 => 'inertia-page',
            default => null,
        };

        return $kind === null ? null : new DiscoveredResource($kind, new SourceLocation($workspaceId, $path, 1));
    }

    /** @return array{list<CodeSymbol>, list<StructuralRelationship>} */
    private function phpEvidence(string $workspaceId, string $path, string $file): array
    {
        $ast = (new ParserFactory)->createForNewestSupportedVersion()->parse((string) file_get_contents($file)) ?? [];
        $finder = new NodeFinder;
        $symbols = [];
        foreach ($finder->findInstanceOf($ast, Node\Stmt\ClassLike::class) as $node) {
            if ($node->name === null) {
                continue;
            }
            $children = [];
            foreach ($node->getMethods() as $method) {
                $children[] = new CodeSymbol('method', $method->name->toString(), new SourceLocation($workspaceId, $path, $method->getStartLine(), $method->getEndLine()));
            }
            $symbols[] = new CodeSymbol(strtolower($node->getType()), $node->name->toString(), new SourceLocation($workspaceId, $path, $node->getStartLine(), $node->getEndLine()), children: $children);
        }
        foreach ($finder->findInstanceOf($ast, Node\Stmt\Function_::class) as $node) {
            $symbols[] = new CodeSymbol('function', $node->name->toString(), new SourceLocation($workspaceId, $path, $node->getStartLine(), $node->getEndLine()));
        }

        $relationships = [];
        foreach ($finder->findInstanceOf($ast, Node\Stmt\Class_::class) as $node) {
            if ($node->name === null) {
                continue;
            }
            if ($node->extends !== null) {
                $relationships[] = new StructuralRelationship($node->name->toString(), 'extends', $node->extends->toString(), new SourceLocation($workspaceId, $path, $node->getStartLine()));
            }
            foreach ($node->implements as $interface) {
                $relationships[] = new StructuralRelationship($node->name->toString(), 'implements', $interface->toString(), new SourceLocation($workspaceId, $path, $node->getStartLine()));
            }
        }

        return [$symbols, $relationships];
    }

    private function unavailable(string $workspaceId, string $scope, string $message): InspectionSnapshot
    {
        return new InspectionSnapshot($workspaceId, $scope, InspectionState::Unavailable, new DateTimeImmutable, message: $message);
    }
}
