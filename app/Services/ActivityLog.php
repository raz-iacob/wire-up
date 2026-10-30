<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\ActivityEvent;
use App\Enums\ContentStatus;
use App\Enums\RevisionSource;
use App\Models\Activity;
use App\Models\Category;
use App\Models\Media;
use App\Models\Order;
use App\Models\Page;
use App\Models\Record;
use App\Models\RecordType;
use App\Models\Redirect;
use App\Models\Role;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Str;

final class ActivityLog
{
    /**
     * @param  array<string, mixed>  $properties
     */
    public static function record(ActivityEvent $event, Model|string|null $subject = null, array $properties = [], ?int $userId = null): void
    {
        $userId ??= self::signedInUserId();

        rescue(fn (): Activity => Activity::query()->create([
            'user_id' => $userId,
            'impersonator_id' => Impersonation::impersonatorId(),
            'source' => RevisionSource::tryFrom((string) Context::get(RevisionSource::CONTEXT_KEY)) ?? ($userId === null ? RevisionSource::SYSTEM : RevisionSource::EDITOR),
            'event' => $event,
            'subject_type' => $subject instanceof Model ? $subject->getMorphClass() : null,
            'subject_id' => $subject instanceof Model ? $subject->getKey() : null,
            'subject_label' => $subject instanceof Model ? self::labelFor($subject) : $subject,
            'properties' => $properties === [] ? null : $properties,
            'ip' => request()->ip(),
            'created_at' => now(),
        ]));
    }

    public static function contentSaved(Page|Record $content, ContentStatus $before, bool $revised): void
    {
        $after = $content->computed_status;

        $change = match (true) {
            $after === $before => $revised ? 'updated' : null,
            $after === ContentStatus::PUBLISHED => 'published',
            $after === ContentStatus::SCHEDULED => 'scheduled',
            in_array($before, [ContentStatus::PUBLISHED, ContentStatus::SCHEDULED], true) => 'unpublished',
            default => 'updated',
        };

        if ($change !== null) {
            self::record(ActivityEvent::from($content->getMorphClass().'.'.$change), $content);
        }
    }

    private static function signedInUserId(): ?int
    {
        $id = auth()->id();

        return is_numeric($id) ? (int) $id : null;
    }

    private static function labelFor(Model $subject): string
    {
        $label = match (true) {
            $subject instanceof Page, $subject instanceof Record => $subject->title,
            $subject instanceof Media => (string) $subject->filename,
            $subject instanceof User, $subject instanceof Role, $subject instanceof RecordType, $subject instanceof Category => $subject->name,
            $subject instanceof Order => $subject->reference,
            $subject instanceof Submission => (string) ($subject->name ?? $subject->email ?? $subject->form_name),
            $subject instanceof Redirect => $subject->from,
            default => (string) $subject->getKey(),
        };

        return Str::limit($label, 250);
    }
}
