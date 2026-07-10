<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\FieldType;
use App\Models\Page;
use App\Models\Record;

final readonly class ContentMarkdown
{
    private BlockMarkdown $blocks;

    public function __construct()
    {
        $this->blocks = new BlockMarkdown;
    }

    public static function current(): self
    {
        return new self;
    }

    public function render(Page|Record $content): string
    {
        $locale = app()->getLocale();
        $content->loadMissing('blocks');

        $sections = $content instanceof Record
            ? $this->recordHeader($content)
            : $this->pageHeader($content);

        foreach ($content->blocks as $block) {
            $sections[] = $this->blocks->render($block, $locale);
        }

        return implode("\n\n", array_values(array_filter($sections, fn (string $section): bool => $section !== '')))."\n";
    }

    /**
     * @return array<int, string>
     */
    private function pageHeader(Page $page): array
    {
        $title = $page->title !== '' ? $page->title : (SettingsService::current()->title() ?: config()->string('app.name'));
        $sections = ['# '.$title];

        $description = mb_trim($page->description);
        if ($description !== '') {
            $sections[] = '> '.$description;
        }

        return $sections;
    }

    /**
     * @return array<int, string>
     */
    private function recordHeader(Record $record): array
    {
        $record->loadMissing('recordType', 'media', 'translations');

        $sections = ['# '.$record->displayHeading()];

        $overview = $this->blocks->fromHtml((string) ($record->fieldValue('overview', true) ?? ''));

        if ($overview !== '') {
            $sections[] = $overview;
        } elseif (mb_trim($record->description) !== '') {
            $sections[] = mb_trim($record->description);
        }

        $fields = [];

        foreach ($record->recordType->displayableFields() as $field) {
            $line = $this->fieldLine($record, $field);

            if ($line !== '') {
                $fields[] = $line;
            }
        }

        if ($fields !== []) {
            $sections[] = implode("\n", $fields);
        }

        return $sections;
    }

    /**
     * @param  array<string, mixed>  $field
     */
    private function fieldLine(Record $record, array $field): string
    {
        $value = $record->fieldValue((string) ($field['key'] ?? ''), (bool) ($field['translatable'] ?? false));

        if (! is_scalar($value) || $value === '') {
            return '';
        }

        $formatted = match (FieldType::tryFrom((string) ($field['type'] ?? ''))) {
            FieldType::BOOLEAN => $value ? __('Yes') : __('No'),
            FieldType::MONEY => SettingsService::current()->formatMoney(is_numeric($value) ? $value : null),
            FieldType::DATE => str((string) $value)->substr(0, 10)->value(),
            FieldType::DATETIME => str((string) $value)->replace('T', ' ')->substr(0, 16)->value(),
            FieldType::RICH_TEXT => (string) str(html_entity_decode(strip_tags((string) $value), ENT_QUOTES | ENT_HTML5, 'UTF-8'))->squish(),
            default => (string) $value,
        };

        if (mb_trim($formatted) === '') {
            return '';
        }

        return '- **'.$record->recordType->fieldLabel($field).':** '.$formatted;
    }
}
