<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\MediaType;
use App\Models\Media;
use App\Models\Record;
use App\Models\RecordType;

final readonly class MakeDocumentsPrivateAction
{
    public function handle(Record|RecordType $owner): void
    {
        $records = $owner instanceof RecordType
            ? $owner->records()->with('media')->get()
            : collect([$owner->load('media')]);

        $privacy = new SetMediaPrivacyAction;

        $records
            ->flatMap(fn (Record $record) => $record->media)
            ->filter(fn (Media $media): bool => $media->type === MediaType::DOCUMENT && ! $media->is_private)
            ->unique('id')
            ->each(fn (Media $media) => $privacy->handle($media, true));
    }
}
