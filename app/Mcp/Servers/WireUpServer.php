<?php

declare(strict_types=1);

namespace App\Mcp\Servers;

use App\Mcp\Resources\BlockTypesResource;
use App\Mcp\Tools\CreatePageTool;
use App\Mcp\Tools\GetMenusTool;
use App\Mcp\Tools\GetPageTool;
use App\Mcp\Tools\GetSettingsTool;
use App\Mcp\Tools\ImportMediaFromUrlTool;
use App\Mcp\Tools\ImportPexelsMediaTool;
use App\Mcp\Tools\ListMediaTool;
use App\Mcp\Tools\ListPagesTool;
use App\Mcp\Tools\PublishPageTool;
use App\Mcp\Tools\ReadWebpageTool;
use App\Mcp\Tools\ScaffoldSiteTool;
use App\Mcp\Tools\SearchPexelsTool;
use App\Mcp\Tools\UpdateDesignTool;
use App\Mcp\Tools\UpdateIdentityTool;
use App\Mcp\Tools\UpdateMenuTool;
use App\Mcp\Tools\UpdatePageBlocksTool;
use App\Mcp\Tools\UpdateSocialTool;
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
2. Set the look with `get-settings` + `update-design` (theme, colors, fonts,
   shape) and the site identity with `update-identity`.
3. Bring in imagery with `import-media-from-url`, or `search-pexels` +
   `import-pexels-media` for stock photos, then reference the returned source
   paths in block content.
4. Create pages as drafts with `create-page`, passing blocks in the same call
   or adding them later with `update-page-blocks`. To lay out a whole site at
   once, `scaffold-site` creates the pages, header/footer navigation and
   homepage in a single call — then fill each page's blocks with
   `update-page-blocks`.
5. Wire the pages into navigation with `get-menus` + `update-menu` (header and
   footer), and set social profile links with `update-social`.
6. Verify your work by fetching the page URL with an `Accept: text/markdown`
   header — every page serves a markdown representation of its content.
7. Publish with `publish-page` when the page looks right.
MD)]
final class WireUpServer extends Server
{
    /**
     * @var array<int, class-string<Server\Tool>>
     */
    public const array TOOLS = [
        ListPagesTool::class,
        GetPageTool::class,
        ScaffoldSiteTool::class,
        CreatePageTool::class,
        UpdatePageBlocksTool::class,
        PublishPageTool::class,
        ListMediaTool::class,
        ImportMediaFromUrlTool::class,
        ReadWebpageTool::class,
        SearchPexelsTool::class,
        ImportPexelsMediaTool::class,
        GetSettingsTool::class,
        UpdateDesignTool::class,
        UpdateIdentityTool::class,
        GetMenusTool::class,
        UpdateMenuTool::class,
        UpdateSocialTool::class,
    ];

    /**
     * @var array<int, class-string<Server\Resource>>
     */
    public const array RESOURCES = [
        BlockTypesResource::class,
    ];

    /**
     * @var array<int, class-string<Server\Tool>>
     */
    protected array $tools = self::TOOLS;

    /**
     * @var array<int, class-string<Server\Resource>>
     */
    protected array $resources = self::RESOURCES;

    /**
     * @return array<int, class-string<Server\Tool>>
     */
    public static function toolClasses(): array
    {
        return self::TOOLS;
    }

    /**
     * @return array<int, class-string<Server\Resource>>
     */
    public static function resourceClasses(): array
    {
        return self::RESOURCES;
    }
}
