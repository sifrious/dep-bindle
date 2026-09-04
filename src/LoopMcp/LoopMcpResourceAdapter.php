<?php

declare(strict_types=1);

namespace Maryeperry\Bindle\LoopMcp;

/**
 * Provider-neutral MCP transport for already-authorized Loop projections.
 *
 * This adapter intentionally exposes resources only. Mutation tools belong to
 * a separately authorized capability and are outside this contract.
 */
final class LoopMcpResourceAdapter
{
    public const string MIME_TYPE = 'application/vnd.sifrious.loop-resource+json;version=1';

    /** @return array{contents: list<array{uri: string, mimeType: string, text: string}>} */
    public function read(LoopResourceDocument $resource): array
    {
        return [
            'contents' => [[
                'uri' => $resource->uri,
                'mimeType' => self::MIME_TYPE,
                'text' => $resource->toJson(),
            ]],
        ];
    }

    /** @return list<array{uriTemplate: string, name: string, description: string, mimeType: string}> */
    public function resourceTemplates(): array
    {
        return array_map(
            static fn (LoopResourceType $type): array => [
                'uriTemplate' => sprintf(
                    'loop://resources/%s{?owner,type,id,object_version}',
                    $type->value,
                ),
                'name' => "loop.{$type->value}",
                'description' => "Read an authorized {$type->value} projection by canonical cross-package reference.",
                'mimeType' => self::MIME_TYPE,
            ],
            LoopResourceType::cases(),
        );
    }
}
