<?php

declare(strict_types=1);

namespace App\Traits;

use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int|null $created_by
 * @property int|null $updated_by
 */
trait HasUserstamps
{
    protected bool $userstamping = true;

    public static function bootHasUserstamps(): void
    {
        static::creating(function (self $model): void {
            if (! $model->isUserstamping()) {
                return;
            }

            $userId = auth()->id();

            $model->created_by ??= $userId;
            $model->updated_by ??= $userId;
        });

        static::updating(function (self $model): void {
            if (! $model->isUserstamping()) {
                return;
            }

            if ($userId = auth()->id()) {
                $model->updated_by = $userId;
            }
        });
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function editor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function isUserstamping(): bool
    {
        return $this->userstamping;
    }

    public function stopUserstamping(): void
    {
        $this->userstamping = false;
    }

    public function startUserstamping(): void
    {
        $this->userstamping = true;
    }
}
