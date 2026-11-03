<?php

declare(strict_types=1);

namespace App\Models;

use App\Services\UpdateService;
use Carbon\CarbonInterface;
use Database\Factories\ErrorLogFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;
use Throwable;

/**
 * @property-read int $id
 * @property-read string $fingerprint
 * @property-read string $type
 * @property-read string $message
 * @property-read string $file
 * @property-read int $line
 * @property-read string|null $address
 * @property-read int|null $user_id
 * @property-read list<string> $trace
 * @property-read array<string, string> $context
 * @property-read int $occurrences
 * @property-read CarbonInterface $last_seen_at
 * @property-read CarbonInterface|null $resolved_at
 * @property-read int|null $resolved_by
 * @property-read CarbonInterface $created_at
 * @property-read CarbonInterface $updated_at
 * @property-read User|null $user
 * @property-read User|null $resolver
 */
final class ErrorLog extends Model
{
    /** @use HasFactory<ErrorLogFactory> */
    use HasFactory, MassPrunable;

    public const int DAYS_KEPT = 90;

    public static function fingerprint(Throwable $exception): string
    {
        return hash('sha256', $exception::class.'|'.$exception->getFile().'|'.$exception->getLine());
    }

    public static function forException(Throwable $exception): self
    {
        $fingerprint = self::fingerprint($exception);

        return rescue(fn (): ?self => self::query()->firstWhere('fingerprint', $fingerprint), null, report: false)
            ?? new self(['fingerprint' => $fingerprint]);
    }

    public static function unresolvedCount(): int
    {
        return self::query()->whereNull('resolved_at')->count();
    }

    /**
     * @return array<string, string>
     */
    public function casts(): array
    {
        return [
            'id' => 'integer',
            'line' => 'integer',
            'user_id' => 'integer',
            'trace' => 'array',
            'context' => 'array',
            'occurrences' => 'integer',
            'last_seen_at' => 'datetime',
            'resolved_at' => 'datetime',
            'resolved_by' => 'integer',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
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
    public function resolver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    public function isResolved(): bool
    {
        return $this->exists && $this->resolved_at !== null;
    }

    public function describe(Throwable $exception): self
    {
        return $this->fill([
            'type' => $exception::class,
            'message' => Str::limit(mb_trim($exception->getMessage()), 5000),
            'file' => Str::limit($this->relative($exception->getFile()), 250),
            'line' => $exception->getLine(),
            'address' => app()->runningInConsole() && request()->route() === null ? null : request()->method().' '.request()->url(),
            'user_id' => auth()->id(),
            'trace' => array_map(
                fn (array $frame): string => $this->relative($frame['file']).':'.($frame['line'] ?? '').' '.($frame['class'] ?? '').($frame['type'] ?? '').$frame['function'].'()',
                array_slice(array_filter($exception->getTrace(), fn (array $frame): bool => isset($frame['file'])), 0, 30),
            ),
            'context' => [
                'version' => resolve(UpdateService::class)->currentVersion() ?? '?',
                'php' => PHP_VERSION,
                'environment' => app()->environment(),
            ],
            'occurrences' => $this->exists ? $this->occurrences + 1 : 1,
            'last_seen_at' => now(),
            'resolved_at' => null,
            'resolved_by' => null,
        ]);
    }

    public function resolve(?User $user): void
    {
        $this->update(['resolved_at' => now(), 'resolved_by' => $user?->id]);
    }

    public function reopen(): void
    {
        $this->update(['resolved_at' => null, 'resolved_by' => null]);
    }

    /**
     * @return Builder<self>
     */
    public function prunable(): Builder
    {
        return self::query()->where('last_seen_at', '<', now()->subDays(self::DAYS_KEPT));
    }

    private function relative(string $path): string
    {
        return Str::after($path, base_path().DIRECTORY_SEPARATOR);
    }
}
