<?php

declare(strict_types=1);

use App\Actions\CreateAccessTokenAction;
use App\Actions\RevokeAccessTokenAction;
use App\Enums\TokenAccess;
use App\Models\AccessToken;
use App\Models\User;
use App\Services\Impersonation;
use App\Services\SettingsService;
use Flux\Flux;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;

return new class extends Component
{
    /**
     * @var list<int>
     */
    public const array EXPIRY_DAYS = [30, 90, 365];

    public User $user;

    public string $name = '';

    public string $access = 'edit';

    public int $days = 90;

    public string $password = '';

    #[Locked]
    public ?string $plainText = null;

    public ?int $revokingId = null;

    public function mount(#[CurrentUser] User $user): void
    {
        $this->user = $user;
    }

    public function create(CreateAccessTokenAction $action): void
    {
        $this->authorize('mcp.use');

        $this->name = mb_trim($this->name);

        $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'access' => ['required', Rule::enum(TokenAccess::class)],
            'days' => ['required', 'integer', Rule::in(self::EXPIRY_DAYS)],
            'password' => ['required', 'current_password'],
        ], attributes: [
            'name' => __('name'),
            'access' => __('access'),
            'days' => __('expiry'),
            'password' => __('password'),
        ]);

        $this->plainText = $action->handle($this->user, $this->name, TokenAccess::from($this->access), $this->days)['plainText'];
        unset($this->tokens);

        $this->reset('name', 'access', 'days', 'password');

        Flux::modal('create-token')->close();
        Flux::modal('token-created')->show();
    }

    public function closeToken(): void
    {
        $this->plainText = null;

        Flux::modal('token-created')->close();
    }

    public function confirmRevoke(int $id): void
    {
        $this->revokingId = $id;

        Flux::modal('revoke-token')->show();
    }

    public function revoke(RevokeAccessTokenAction $action): void
    {
        $action->handle($this->user->accessTokens()->findOrFail($this->revokingId));
        unset($this->tokens);

        $this->reset('revokingId');

        Flux::modal('revoke-token')->close();
        Flux::toast(__('Token revoked.'));
    }

    /**
     * @return Collection<int, AccessToken>
     */
    #[Computed]
    public function tokens(): Collection
    {
        return $this->user->accessTokens()->latest('id')->get();
    }

    #[Computed]
    public function command(): string
    {
        return 'claude mcp add --transport http '.(Str::slug(SettingsService::current()->brandName()) ?: 'wire-up').' '.url('/mcp').' --header "Authorization: Bearer '.$this->plainText.'"';
    }

    #[Computed]
    public function impersonating(): bool
    {
        return Impersonation::impersonatorId() !== null;
    }
};
?>

<div class="space-y-6">
    <div>
        <flux:heading size="lg">{{ __('MCP tokens') }}</flux:heading>
        <flux:text class="mt-1">{{ __('Let AI tools such as Claude Code and Cursor work on this site as you.') }}</flux:text>
    </div>

    @if ($this->tokens->isNotEmpty())
        <div class="divide-y divide-zinc-800/10 dark:divide-white/10">
            @foreach ($this->tokens as $token)
                <div wire:key="token-{{ $token->id }}" class="flex items-center justify-between gap-4 py-3">
                    <div class="min-w-0">
                        <div class="flex items-center gap-2">
                            <flux:heading class="truncate">{{ $token->name }}</flux:heading>
                            <flux:badge
                                size="sm"
                                :color="$token->access === TokenAccess::Full ? 'amber' : 'zinc'"
                            >{{ $token->access->label() }}</flux:badge>
                        </div>
                        <flux:text size="sm">
                            {{ $token->isExpired() ? __('Expired') : __('Expires :date', ['date' => $token->expires_at->isoFormat('LL')]) }} · {{ $token->last_used_at ? __('Last used :time', ['time' => $token->last_used_at->diffForHumans()]) : __('Never used') }}
                        </flux:text>
                    </div>

                    <flux:button
                        wire:click="confirmRevoke({{ $token->id }})"
                        variant="subtle"
                        size="sm"
                        icon="trash"
                    >{{ __('Revoke') }}</flux:button>
                </div>
            @endforeach
        </div>
    @endif

    @if ($this->impersonating)
        <flux:text>{{ __('Tokens can’t be made while you are signed in as someone else.') }}</flux:text>
    @else
        <div>
            <flux:modal.trigger name="create-token">
                <flux:button variant="primary" icon="key">{{ __('Create a token') }}</flux:button>
            </flux:modal.trigger>
        </div>

        <flux:modal name="create-token" class="w-full md:max-w-md">
            <form wire:submit="create" class="space-y-6">
                <flux:heading size="lg">{{ __('Create a token') }}</flux:heading>

                <flux:input
                    wire:model="name"
                    :label="__('Name')"
                    :placeholder="__('Claude Code on my laptop')"
                    maxlength="255"
                    required
                />

                <flux:radio.group wire:model="access" :label="__('Access')">
                    @foreach (TokenAccess::cases() as $level)
                        <flux:radio
                            :value="$level->value"
                            :label="$level->label()"
                            :description="$level->description()"
                        />
                    @endforeach
                </flux:radio.group>

                <flux:select wire:model="days" :label="__('Expires')">
                    <flux:select.option :value="30">{{ __('In 30 days') }}</flux:select.option>
                    <flux:select.option :value="90">{{ __('In 90 days') }}</flux:select.option>
                    <flux:select.option :value="365">{{ __('In a year') }}</flux:select.option>
                </flux:select>

                <flux:input
                    wire:model="password"
                    :label="__('Password')"
                    type="password"
                    required
                    autocomplete="current-password"
                    viewable
                />

                <div class="flex gap-3">
                    <flux:spacer />
                    <flux:modal.close>
                        <flux:button variant="ghost" type="button">{{ __('Cancel') }}</flux:button>
                    </flux:modal.close>
                    <flux:button type="submit" variant="primary">{{ __('Create') }}</flux:button>
                </div>
            </form>
        </flux:modal>
    @endif

    <flux:modal name="token-created" class="w-full md:max-w-lg" :dismissible="false" :closable="false">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Copy your token') }}</flux:heading>
                <flux:text class="mt-2">{{ __('You won’t see it again.') }}</flux:text>
            </div>

            @if ($plainText !== null)
                <flux:input :value="$plainText" :label="__('Token')" readonly copyable />

                <flux:input :value="$this->command" :label="__('Add it to Claude Code')" readonly copyable />
            @endif

            <div class="flex">
                <flux:spacer />
                <flux:button variant="primary" wire:click="closeToken">{{ __('Done') }}</flux:button>
            </div>
        </div>
    </flux:modal>

    <flux:modal name="revoke-token" class="min-w-[22rem]">
        <form wire:submit="revoke" class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Revoke this token?') }}</flux:heading>
                <flux:text class="mt-2">{{ __('Tools using it will stop working straight away.') }}</flux:text>
            </div>

            <div class="flex gap-3">
                <flux:spacer />
                <flux:modal.close>
                    <flux:button variant="ghost" type="button">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>
                <flux:button type="submit" variant="danger">{{ __('Revoke') }}</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
