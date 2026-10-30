<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ActivityEvent;
use App\Enums\RevisionSource;
use App\Services\ShopService;
use Carbon\CarbonInterface;
use Database\Factories\ActivityFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Str;

/**
 * @property-read int $id
 * @property-read int|null $user_id
 * @property-read int|null $impersonator_id
 * @property-read RevisionSource $source
 * @property-read ActivityEvent $event
 * @property-read string|null $subject_type
 * @property-read int|null $subject_id
 * @property-read string|null $subject_label
 * @property-read array<string, mixed>|null $properties
 * @property-read string|null $ip
 * @property-read CarbonInterface $created_at
 * @property-read User|null $user
 * @property-read User|null $impersonator
 * @property-read Model|null $subject
 */
final class Activity extends Model
{
    /** @use HasFactory<ActivityFactory> */
    use HasFactory, MassPrunable;

    public const int DAYS_KEPT = 180;

    public const null UPDATED_AT = null;

    /**
     * @return array<string, string>
     */
    public function casts(): array
    {
        return [
            'id' => 'integer',
            'user_id' => 'integer',
            'impersonator_id' => 'integer',
            'source' => RevisionSource::class,
            'event' => ActivityEvent::class,
            'subject_id' => 'integer',
            'properties' => 'array',
            'created_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function impersonator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'impersonator_id');
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return Builder<self>
     */
    public function prunable(): Builder
    {
        return self::query()->where('created_at', '<', now()->subDays(self::DAYS_KEPT));
    }

    public function actorName(): string
    {
        return match (true) {
            $this->impersonator_id !== null => $this->impersonator->name ?? __('Deleted user'),
            $this->user instanceof User => $this->user->name,
            $this->user_id !== null => __('Deleted user'),
            $this->source === RevisionSource::EDITOR => __('Someone'),
            default => $this->source->label(),
        };
    }

    public function actingAs(): ?string
    {
        return $this->impersonator_id === null ? null : ($this->user->name ?? __('Deleted user'));
    }

    public function viaAssistant(): bool
    {
        return $this->source === RevisionSource::ASSISTANT && $this->user instanceof User;
    }

    public function details(): ?string
    {
        $properties = $this->properties ?? [];

        return match ($this->event) {
            ActivityEvent::SETTINGS_UPDATED => implode(', ', array_map(Str::headline(...), (array) ($properties['keys'] ?? []))),
            ActivityEvent::USER_ROLE_CHANGED => __(':from → :to', ['from' => $properties['from'] ?? __('No role'), 'to' => $properties['to'] ?? __('No role')]),
            ActivityEvent::USER_INVITED => __('As :role', ['role' => $properties['role'] ?? __('No role')]),
            ActivityEvent::ORDER_REFUNDED => resolve(ShopService::class)->formatMinor((int) ($properties['amount'] ?? 0), isset($properties['currency']) ? (string) $properties['currency'] : null),
            ActivityEvent::REDIRECTS_IMPORTED => __(':added added, :updated updated', ['added' => $properties['added'] ?? 0, 'updated' => $properties['updated'] ?? 0]),
            ActivityEvent::BACKUP_FAILED => isset($properties['reason']) ? Str::limit((string) $properties['reason'], 120) : null,
            default => null,
        };
    }

    public function subjectUrl(): ?string
    {
        return match (true) {
            $this->subject instanceof Page => route('admin.pages-edit', $this->subject),
            $this->subject instanceof Record => route('admin.records-edit', [$this->subject->recordType, $this->subject]),
            $this->subject instanceof Category => route('admin.categories-edit', $this->subject),
            $this->subject instanceof RecordType => route('admin.settings-content-types'),
            $this->subject instanceof User => route('admin.users-edit', $this->subject),
            $this->subject instanceof Role => route('admin.settings-roles'),
            $this->subject instanceof Order => route('admin.orders-show', $this->subject),
            $this->subject instanceof Submission => route('admin.inbox-show', $this->subject),
            $this->subject instanceof Redirect => route('admin.settings-redirects', ['q' => $this->subject->from]),
            default => null,
        };
    }
}
