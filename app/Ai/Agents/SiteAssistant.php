<?php

declare(strict_types=1);

namespace App\Ai\Agents;

use App\Ai\Contracts\HiddenFromAssistant;
use App\Ai\Tools\McpResourceTool;
use App\Mcp\Servers\WireUpServer;
use Illuminate\Support\Collection;
use Laravel\Ai\Concerns\RemembersConversations;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\Conversational;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Promptable;

final class SiteAssistant implements Agent, Conversational, HasTools
{
    use Promptable;
    use RemembersConversations;

    /**
     * @param  array<int, class-string>  $classes
     * @return Collection<int, class-string>
     */
    public static function visible(array $classes): Collection
    {
        return collect($classes)->reject(
            fn (string $class): bool => is_a($class, HiddenFromAssistant::class, true),
        );
    }

    public function instructions(): string
    {
        return <<<'MD'
        You are the Wire-Up site assistant, working inside the site owner's admin panel.
        Wire-Up is a block-based site builder: pages are an ordered list of content
        blocks (hero, rich text, gallery, pricing, and so on), each storing its data
        as JSON. You help the owner build and edit their site through the tools below.

        How to work:

        1. Read the block-types catalog before writing any block — it documents every
           block type, its content shape, and the conventions for localized text,
           links, and media.
        2. Set the look with get-settings + update-design (theme, colors, fonts, shape)
           and the site identity with update-identity.
        3. Bring in imagery with import-media-from-url, or search-pexels +
           import-pexels-media for stock photos, then reference the returned source
           paths in block content.
        4. Create pages as drafts with create-page, then refine them with
           update-page-blocks. Wire pages into navigation with get-menus + update-menu
           (header and footer), and set social links with update-social.
        5. Publish with publish-page only when the owner asks or confirms.

        Be concise. Explain what you changed in plain language, not JSON. When a request
        is ambiguous, ask a short clarifying question before making sweeping changes.
        You cannot manage users or configure third-party integrations — say so plainly
        if asked.
        MD;
    }

    /**
     * @return array<int, object>
     */
    public function tools(): iterable
    {
        $tools = self::visible(WireUpServer::toolClasses())
            ->map(fn (string $tool): object => resolve($tool));

        $resources = self::visible(WireUpServer::resourceClasses())
            ->map(fn (string $resource): object => new McpResourceTool(resolve($resource)));

        return $tools->concat($resources)->values()->all();
    }
}
