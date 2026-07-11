<?php

declare(strict_types=1);

namespace App\Mcp\Servers;

use App\Mcp\Resources\BlockTypesResource;
use App\Mcp\Tools\CreatePageTool;
use App\Mcp\Tools\GetPageTool;
use App\Mcp\Tools\ListPagesTool;
use App\Mcp\Tools\PublishPageTool;
use App\Mcp\Tools\UpdatePageBlocksTool;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Attributes\Instructions;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Version;

#[Name('Wire-Up')]
#[Version('1.0.0')]
#[Instructions(<<<'MD'
Wire-Up is a block-based site builder. Pages are composed of ordered content
blocks (hero, rich text, gallery, pricing, and so on), each storing its data as JSON.

Typical workflow for building or replicating a site:

1. Read the `block-types` resource first — it documents every block type, its
   content shape, and the conventions for localized text and links.
2. Create pages as drafts with `create-page`, passing blocks in the same call
   or adding them later with `update-page-blocks`.
3. Verify your work by fetching the page URL with an `Accept: text/markdown`
   header — every page serves a markdown representation of its content.
4. Publish with `publish-page` when the page looks right.
MD)]
final class WireUpServer extends Server
{
    /**
     * @var array<int, class-string<Server\Tool>>
     */
    protected array $tools = [
        ListPagesTool::class,
        GetPageTool::class,
        CreatePageTool::class,
        UpdatePageBlocksTool::class,
        PublishPageTool::class,
    ];

    /**
     * @var array<int, class-string<Server\Resource>>
     */
    protected array $resources = [
        BlockTypesResource::class,
    ];
}
