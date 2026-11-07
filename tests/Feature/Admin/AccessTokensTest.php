<?php

declare(strict_types=1);

use App\Enums\ActivityEvent;
use App\Enums\TokenAccess;
use App\Models\AccessToken;
use App\Models\Activity;
use App\Models\User;
use App\Services\Impersonation;
use Livewire\Livewire;

beforeEach(function (): void {
    config()->set('site.remote_mcp_enabled', true);
});

function tokenOwner(): User
{
    $user = User::factory()->owner()->create(['active' => true]);
    test()->actingAs($user);

    return $user;
}

it('shows MCP tokens on the security screen only when remote MCP is on and the role allows it', function (bool $enabled, string $role, bool $visible): void {
    config()->set('site.remote_mcp_enabled', $enabled);
    $this->actingAs(User::factory()->{$role}()->create(['active' => true]));

    $response = $this->get(route('admin.account-security'))->assertOk();

    $visible ? $response->assertSee('MCP tokens') : $response->assertDontSee('MCP tokens');
})->with([
    'on, for the owner' => [true, 'owner', true],
    'on, for an administrator' => [true, 'admin', true],
    'on, for an editor' => [true, 'editor', false],
    'off' => [false, 'owner', false],
]);

it('creates a token, shows it once and keeps only its hash', function (): void {
    $user = tokenOwner();
    $this->freezeSecond();

    $component = Livewire::test('admin.access-tokens')
        ->set('name', '  Cursor  ')
        ->set('access', 'read')
        ->set('days', 30)
        ->set('password', 'password')
        ->call('create')
        ->assertHasNoErrors()
        ->assertSet('name', '')
        ->assertSet('password', '');

    $plainText = (string) $component->get('plainText');
    $token = AccessToken::query()->sole();

    expect($plainText)->toStartWith(AccessToken::PREFIX)
        ->and($token->only(['user_id', 'name', 'access']))->toBe(['user_id' => $user->id, 'name' => 'Cursor', 'access' => TokenAccess::Read])
        ->and($token->token_hash)->toBe(hash('sha256', $plainText))
        ->and($token->expires_at->equalTo(now()->addDays(30)))->toBeTrue()
        ->and(Activity::query()->sole()->only(['event', 'user_id', 'subject_label', 'properties']))->toBe([
            'event' => ActivityEvent::ACCESS_TOKEN_CREATED,
            'user_id' => $user->id,
            'subject_label' => 'Cursor',
            'properties' => ['access' => 'read'],
        ]);

    $component->assertSee($plainText)
        ->assertSee('claude mcp add --transport http')
        ->call('closeToken')
        ->assertSet('plainText', null)
        ->assertDontSee($plainText);
});

it('explains what is missing when creating a token', function (string $field, mixed $value, string $message): void {
    tokenOwner();

    $component = Livewire::test('admin.access-tokens')
        ->set('name', 'Cursor')
        ->set('password', 'password')
        ->set($field, $value)
        ->call('create')
        ->assertHasErrors($field);

    expect($component->errors()->first($field))->toBe($message)
        ->and(AccessToken::query()->count())->toBe(0);
})->with([
    'no name' => ['name', '   ', 'The name field is required.'],
    'unknown access' => ['access', 'admin', 'The selected access is invalid.'],
    'unlisted expiry' => ['days', 45, 'The selected expiry is invalid.'],
    'wrong password' => ['password', 'not-my-password', 'The password is incorrect.'],
]);

it('needs the remote MCP ability to create a token', function (): void {
    $this->actingAs(User::factory()->editor()->create(['active' => true]));

    Livewire::test('admin.access-tokens')
        ->set('name', 'Cursor')
        ->set('password', 'password')
        ->call('create')
        ->assertForbidden();
});

it('refuses to create a token while signed in as someone else', function (): void {
    tokenOwner();
    session()->put(Impersonation::SESSION_KEY, User::factory()->owner()->create()->id);

    Livewire::test('admin.access-tokens')
        ->assertSee('Tokens can’t be made while you are signed in as someone else.')
        ->assertDontSee('Create a token')
        ->set('name', 'Cursor')
        ->set('password', 'password')
        ->call('create')
        ->assertForbidden();

    expect(AccessToken::query()->count())->toBe(0);
});

it('lists tokens with their access and expiry', function (): void {
    $user = tokenOwner();
    AccessToken::factory()->for($user)->access(TokenAccess::Full)->create(['name' => 'Laptop', 'last_used_at' => now()->subHour()]);
    AccessToken::factory()->for($user)->expired()->create(['name' => 'Old desktop']);
    AccessToken::factory()->create(['name' => 'Someone else’s']);

    Livewire::test('admin.access-tokens')
        ->assertSeeInOrder(['Old desktop', 'Expired', 'Never used', 'Laptop', 'Full access', 'Last used 1 hour ago'])
        ->assertDontSee('Someone else’s');
});

it('revokes a token straight away', function (): void {
    $user = tokenOwner();
    $token = AccessToken::factory()->for($user)->create(['name' => 'Laptop']);

    Livewire::test('admin.access-tokens')
        ->call('confirmRevoke', $token->id)
        ->call('revoke')
        ->assertDispatched('toast-show', toastSays('Token revoked.'));

    expect(AccessToken::query()->count())->toBe(0)
        ->and(Activity::query()->sole()->only(['event', 'subject_label']))->toBe(['event' => ActivityEvent::ACCESS_TOKEN_REVOKED, 'subject_label' => 'Laptop']);
});

it('can’t revoke someone else’s token', function (): void {
    tokenOwner();
    $token = AccessToken::factory()->create();

    Livewire::test('admin.access-tokens')
        ->call('confirmRevoke', $token->id)
        ->call('revoke')
        ->assertNotFound();

    expect(AccessToken::query()->count())->toBe(1);
});
