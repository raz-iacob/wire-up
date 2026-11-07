<?php

declare(strict_types=1);

use App\Enums\ActivityEvent;
use App\Enums\RevisionSource;
use App\Enums\TokenAccess;
use App\Http\Middleware\AuthenticateMcpToken;
use App\Models\AccessToken;
use App\Models\Activity;
use App\Models\Page;
use App\Models\Record;
use App\Models\RecordType;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;

beforeEach(function (): void {
    config()->set('site.remote_mcp_enabled', true);
});

function remoteMcpToken(User $user, TokenAccess $access = TokenAccess::Edit): string
{
    $plainText = AccessToken::generate();

    AccessToken::factory()->for($user)->plainText($plainText)->access($access)->create();

    return $plainText;
}

function remoteMcpOwner(): User
{
    return User::factory()->owner()->create(['active' => true]);
}

/**
 * @param  list<string>  $abilities
 */
function remoteMcpUser(array $abilities): User
{
    return User::factory()->role(Role::factory()->create(['abilities' => [...$abilities, 'mcp.use']]))->create(['active' => true]);
}

/**
 * @param  array<string, mixed>  $params
 */
function remoteMcp(?string $token, string $method, array $params = [], string $url = '/mcp'): TestResponse
{
    return test()->postJson($url, ['jsonrpc' => '2.0', 'id' => 1, 'method' => $method, 'params' => $params], $token === null ? [] : ['Authorization' => 'Bearer '.$token]);
}

/**
 * @param  array<string, mixed>  $arguments
 */
function remoteMcpCall(string $token, string $tool, array $arguments = []): TestResponse
{
    return remoteMcp($token, 'tools/call', ['name' => $tool, 'arguments' => $arguments]);
}

/**
 * @return list<string>
 */
function remoteMcpTools(string $token): array
{
    return collect(remoteMcp($token, 'tools/list', ['per_page' => 100])->assertOk()->json('result.tools'))->pluck('name')->all();
}

it('hides the endpoint while remote MCP is off', function (): void {
    config()->set('site.remote_mcp_enabled', false);

    remoteMcp(remoteMcpToken(remoteMcpOwner()), 'tools/list')->assertNotFound();
});

it('turns away a request without a valid token', function (Closure $token): void {
    remoteMcp($token(), 'tools/list')
        ->assertUnauthorized()
        ->assertHeader('WWW-Authenticate', 'Bearer realm="mcp", error="invalid_token"')
        ->assertExactJson(['error' => 'This token isn’t valid. Make a new one under Account → Security.']);
})->with([
    'no token' => fn (): ?string => null,
    'a made-up token' => AccessToken::generate(...),
    'a token from another service' => fn (): string => 'token-'.Str::random(40),
    'an expired token' => function (): string {
        $plainText = AccessToken::generate();
        AccessToken::factory()->for(remoteMcpOwner())->plainText($plainText)->expired()->create();

        return $plainText;
    },
    'a disabled account' => fn (): string => remoteMcpToken(User::factory()->owner()->create(['active' => false])),
    'a member account' => fn (): string => remoteMcpToken(User::factory()->member()->create(['active' => true])),
    'a role without remote MCP' => fn (): string => remoteMcpToken(User::factory()->editor()->create(['active' => true])),
]);

it('refuses plain http on a live site', function (): void {
    app()->detectEnvironment(fn (): string => 'production');
    $token = remoteMcpToken(remoteMcpOwner());

    remoteMcp($token, 'tools/list', url: 'http://localhost/mcp')
        ->assertForbidden()
        ->assertExactJson(['error' => 'Connect to this site over https://.']);

    remoteMcp($token, 'tools/list', url: 'https://localhost/mcp')->assertOk();
});

it('slows down a token that sends too many requests', function (): void {
    $token = remoteMcpToken(remoteMcpOwner());
    $id = AccessToken::query()->sole()->id;

    foreach (range(1, AuthenticateMcpToken::REQUESTS_PER_MINUTE) as $attempt) {
        RateLimiter::hit('mcp-token:'.$id);
    }

    remoteMcp($token, 'tools/list')->assertTooManyRequests()->assertHeader('Retry-After');
});

it('slows down an address that keeps sending bad tokens', function (): void {
    $token = remoteMcpToken(remoteMcpOwner());

    foreach (range(1, AuthenticateMcpToken::FAILURES_PER_MINUTE) as $attempt) {
        remoteMcp(AccessToken::generate(), 'tools/list')->assertUnauthorized();
    }

    remoteMcp($token, 'tools/list')->assertTooManyRequests();
});

it('lists only the tools the token’s level allows and never the orders', function (TokenAccess $access, array $present, array $absent): void {
    $tools = remoteMcpTools(remoteMcpToken(remoteMcpOwner(), $access));

    expect($tools)->toContain(...$present)
        ->and(array_intersect($tools, $absent))->toBeEmpty()
        ->and($tools)->not->toContain('list-orders', 'get-order');
})->with([
    'read only' => [TokenAccess::Read, ['list-pages', 'get-page', 'get-settings', 'search-content'], ['update-page-blocks', 'create-page', 'update-design', 'publish-page', 'delete-page', 'replace-text']],
    'edit' => [TokenAccess::Edit, ['list-pages', 'update-page-blocks', 'create-page', 'update-design', 'edit-blocks'], ['publish-page', 'delete-page', 'delete-media', 'replace-text']],
    'full access' => [TokenAccess::Full, ['update-page-blocks', 'publish-page', 'delete-page', 'delete-media', 'delete-redirect', 'replace-text'], []],
]);

it('lists only the tools the person’s role can use', function (): void {
    RecordType::factory()->create(['key' => 'products', 'name' => 'Products']);

    $tools = remoteMcpTools(remoteMcpToken(remoteMcpUser(['pages.view', 'pages.edit', 'records.products.view']), TokenAccess::Full));

    expect($tools)->toContain('list-pages', 'update-page-blocks', 'publish-page', 'list-records', 'get-record', 'list-media')
        ->not->toContain('create-page', 'delete-page', 'scaffold-site', 'create-record', 'update-design', 'get-settings', 'list-categories', 'create-content-type');
});

it('works as the token’s owner and marks the change as made by an agent', function (): void {
    $user = remoteMcpOwner();
    $token = remoteMcpToken($user);

    $this->freezeSecond();

    remoteMcpCall($token, 'create-page', ['title' => 'Remote launch'])
        ->assertOk()
        ->assertJsonPath('result.isError', false);

    $page = Page::query()->latest('id')->firstOrFail();
    $activity = Activity::query()->where('event', ActivityEvent::PAGE_CREATED)->sole();

    expect($page->created_by)->toBe($user->id)
        ->and($activity->user_id)->toBe($user->id)
        ->and($activity->source)->toBe(RevisionSource::AGENT)
        ->and(AccessToken::query()->sole()->last_used_at?->equalTo(now()))->toBeTrue();
});

it('records when a token was last used at most once a minute', function (): void {
    $token = remoteMcpToken(remoteMcpOwner());
    $this->freezeSecond();

    remoteMcp($token, 'tools/list')->assertOk();
    $this->travel(30)->seconds();
    remoteMcp($token, 'tools/list')->assertOk();

    expect(AccessToken::query()->sole()->last_used_at?->equalTo(now()->subSeconds(30)))->toBeTrue();

    $this->travel(31)->seconds();
    remoteMcp($token, 'tools/list')->assertOk();

    expect(AccessToken::query()->sole()->last_used_at?->equalTo(now()))->toBeTrue();
});

it('explains why a token can’t use a tool', function (TokenAccess $access, string $tool, Closure $arguments, string $reason): void {
    remoteMcpCall(remoteMcpToken(remoteMcpOwner(), $access), $tool, $arguments(Record::factory()->create()))
        ->assertOk()
        ->assertJsonPath('result.isError', true)
        ->assertJsonPath('result.content.0.text', $reason);
})->with([
    'reading token changing a page' => [TokenAccess::Read, 'update-page-blocks', fn (): array => ['page' => 1, 'blocks' => []], 'This token can only read. Make an edit token under Account → Security.'],
    'edit token deleting a page' => [TokenAccess::Edit, 'delete-page', fn (): array => ['page' => 1, 'confirm' => true], 'Publishing, deleting and replace-text need a token with full access.'],
    'edit token replacing text' => [TokenAccess::Edit, 'replace-text', fn (): array => ['find' => 'old', 'replace' => 'new'], 'Publishing, deleting and replace-text need a token with full access.'],
    'edit token publishing a new page' => [TokenAccess::Edit, 'create-page', fn (): array => ['title' => 'Live', 'publish' => true], 'Publishing needs a token with full access. Leave out "publish" to save a draft.'],
    'edit token publishing a record' => [TokenAccess::Edit, 'update-record', fn (Record $record): array => ['record' => $record->id, 'status' => 'published'], 'Publishing needs a token with full access. Leave out "status" to save a draft.'],
]);

it('explains which part of the role is missing', function (array $abilities, string $tool, Closure $arguments, string $reason): void {
    $products = RecordType::factory()->create(['key' => 'products', 'name' => 'Products']);
    $events = RecordType::factory()->create(['key' => 'events', 'name' => 'Events']);
    $records = ['products' => Record::factory()->for($products)->create(), 'events' => Record::factory()->for($events)->create()];

    remoteMcpCall(remoteMcpToken(remoteMcpUser($abilities), TokenAccess::Full), $tool, $arguments($records))
        ->assertOk()
        ->assertJsonPath('result.isError', true)
        ->assertJsonPath('result.content.0.text', $reason);
})->with([
    'design without settings' => [['pages.edit'], 'update-design', fn (): array => ['radius' => 'none'], 'Your role can’t edit Settings.'],
    'another content type' => [['records.products.view'], 'get-record', fn (array $records): array => ['record' => $records['events']->id], 'Your role can’t view Events.'],
    'creating in another content type' => [['records.products.create'], 'create-record', fn (): array => ['type' => 'events', 'title' => 'Gala'], 'Your role can’t create Events.'],
    'deleting a record' => [['records.products.edit'], 'delete-record', fn (array $records): array => ['record' => $records['products']->id, 'confirm' => true], 'Your role can’t delete Products.'],
    'page blocks without pages' => [['records.products.edit'], 'edit-blocks', fn (): array => ['page' => 1, 'changes' => []], 'Your role can’t edit Pages.'],
    'record blocks of another type' => [['records.products.edit'], 'edit-blocks', fn (array $records): array => ['record' => $records['events']->id, 'changes' => []], 'Your role can’t edit Events.'],
    'translating a page' => [['records.products.edit'], 'update-content-strings', fn (): array => ['type' => 'page', 'id' => 1, 'locale' => 'fr', 'translations' => []], 'Your role can’t edit Pages.'],
    'translating another record' => [['records.products.view'], 'get-content-strings', fn (array $records): array => ['type' => 'record', 'id' => $records['events']->id, 'locale' => 'fr'], 'Your role can’t view Events.'],
    'searching every content type' => [['pages.view', 'records.products.view'], 'search-content', fn (): array => ['find' => 'sale'], 'Your role can’t view Events.'],
    'replacing in another content type' => [['pages.edit', 'records.products.edit'], 'replace-text', fn (array $records): array => ['find' => 'old', 'replace' => 'new', 'pages' => [1], 'records' => [$records['events']->id]], 'Your role can’t edit Events.'],
    'an unknown content type' => [['pages.view'], 'list-records', fn (): array => ['type' => 'unknown'], 'Your role doesn’t allow this.'],
    'menus without settings' => [['pages.view'], 'get-menus', fn (): array => [], 'Your role can’t view Settings.'],
    'categories' => [['pages.view'], 'create-category', fn (): array => ['name' => 'News'], 'Your role can’t create Categories.'],
]);

it('lets a role search the records it can see', function (): void {
    $products = RecordType::factory()->create(['key' => 'products', 'name' => 'Products']);
    RecordType::factory()->create(['key' => 'events', 'name' => 'Events']);
    $product = Record::factory()->for($products)->create();

    remoteMcpCall(remoteMcpToken(remoteMcpUser(['records.products.view']), TokenAccess::Read), 'search-content', ['find' => 'sale', 'records' => [$product->id]])
        ->assertOk()
        ->assertJsonPath('result.isError', false);
});

it('keeps orders out of reach entirely', function (): void {
    remoteMcpCall(remoteMcpToken(remoteMcpOwner(), TokenAccess::Full), 'list-orders')
        ->assertJsonPath('error.message', 'Tool [list-orders] not found.');
});
