<?php

declare(strict_types=1);

use App\Ai\Agents\SiteAssistant;
use App\Ai\Contracts\HiddenFromAssistant;
use App\Ai\Tools\ConfirmationTool;
use App\Ai\Tools\DraftOnlyTool;
use App\Ai\Tools\McpResourceTool;
use App\Enums\ContentStatus;
use App\Mcp\Resources\BlockTypesResource;
use App\Mcp\Tools\CreatePageTool;
use App\Mcp\Tools\GetPageTool;
use App\Mcp\Tools\PublishPageTool;
use App\Models\Page;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\JsonSchemaTypeFactory;
use Laravel\Ai\Tools\McpServerTool;
use Laravel\Ai\Tools\Request as ToolRequest;
use Laravel\Mcp\Request as McpRequest;
use Laravel\Mcp\Response as McpResponse;
use Laravel\Mcp\Server\Resource;
use Laravel\Mcp\Server\Tool;

final class HiddenAssistantTool extends Tool implements HiddenFromAssistant
{
    public function handle(McpRequest $request): McpResponse
    {
        return McpResponse::text('hidden');
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [];
    }
}

final class HiddenAssistantResource extends Resource implements HiddenFromAssistant
{
    public function handle(McpRequest $request): McpResponse
    {
        return McpResponse::text('hidden');
    }
}

it('instructs the assistant on the Wire-Up building workflow', function (): void {
    $instructions = (new SiteAssistant)->instructions();

    expect($instructions)->toContain('block-types')
        ->toContain('publish-page')
        ->toContain('cannot manage users')
        ->toContain('untrusted data')
        ->toContain('private-network');
});

it('exposes every WireUp tool and the block-types resource to the assistant', function (): void {
    $names = collect((new SiteAssistant)->tools())->map(fn (object $tool): string => $tool->name());

    expect($names->all())->toEqualCanonicalizing([
        'list-pages', 'get-page', 'scaffold-site', 'create-page', 'update-page-blocks', 'publish-page',
        'list-media', 'import-media-from-url', 'read-webpage', 'search-pexels', 'import-pexels-media',
        'get-settings', 'update-design', 'update-identity',
        'get-menus', 'update-menu', 'update-social',
        'block-types',
    ]);
});

it('wraps tools for the SDK by their assistant policy', function (): void {
    $tools = collect((new SiteAssistant)->tools());

    expect($tools->first(fn (object $t): bool => $t->name() === 'get-page'))->toBeInstanceOf(GetPageTool::class)
        ->and($tools->first(fn (object $t): bool => $t->name() === 'create-page'))->toBeInstanceOf(DraftOnlyTool::class)
        ->and($tools->first(fn (object $t): bool => $t->name() === 'publish-page'))->toBeInstanceOf(ConfirmationTool::class)
        ->and($tools->first(fn (object $t): bool => $t->name() === 'block-types'))->toBeInstanceOf(McpResourceTool::class);
});

it('marks only publishing as requiring confirmation', function (): void {
    expect(SiteAssistant::confirmableToolNames())->toBe(['publish-page'])
        ->and(SiteAssistant::confirmableToolClass('publish-page'))->toBe(PublishPageTool::class)
        ->and(SiteAssistant::confirmableToolClass('create-page'))->toBeNull();
});

it('does not publish when the confirmation tool runs during a stream', function (): void {
    $page = Page::factory()->create(['status' => ContentStatus::DRAFT]);

    $output = new ConfirmationTool(new PublishPageTool)->handle(new ToolRequest(['page' => $page->id]));

    expect($output)->toContain('awaiting_confirmation')
        ->and($page->refresh()->status)->toBe(ContentStatus::DRAFT);
});

it('forces the assistant create-page tool to drafts', function (): void {
    $tool = new DraftOnlyTool(new CreatePageTool);

    expect($tool->schema(new JsonSchemaTypeFactory))->not->toHaveKey('publish');

    $tool->handle(new ToolRequest(['title' => 'Forced Draft', 'publish' => true]));

    expect(Page::query()->latest('id')->firstOrFail()->status)->toBe(ContentStatus::DRAFT);
});

it('bridges the block-types resource so the assistant can read the catalog', function (): void {
    $tool = new McpResourceTool(new BlockTypesResource);

    expect($tool->schema(new JsonSchemaTypeFactory))->toBe([]);

    $output = $tool->handle(new ToolRequest([]));

    expect($output)->toContain('"key":"hero"')
        ->toContain('"key":"rich-text"')
        ->toContain('localizedText');
});

it('invokes a WireUp MCP tool through the SDK bridge', function (): void {
    $tool = new McpServerTool(new CreatePageTool);

    $output = $tool->handle(new ToolRequest(['title' => 'Bridged Page']));

    expect($output)->toContain('bridged-page')
        ->toContain('"status":"draft"')
        ->and(Page::query()->latest('id')->firstOrFail()->title)->toBe('Bridged Page');
});

it('drops primitives marked HiddenFromAssistant while keeping the rest', function (): void {
    $visible = SiteAssistant::visible([
        CreatePageTool::class,
        HiddenAssistantTool::class,
        GetPageTool::class,
        HiddenAssistantResource::class,
    ]);

    expect($visible->values()->all())->toEqual([CreatePageTool::class, GetPageTool::class]);
});
