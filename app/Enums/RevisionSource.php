<?php

declare(strict_types=1);

namespace App\Enums;

enum RevisionSource: string
{
    case EDITOR = 'editor';
    case ASSISTANT = 'assistant';
    case AGENT = 'agent';
    case SYSTEM = 'system';

    public const string CONTEXT_KEY = 'revision_source';

    public function label(): string
    {
        return match ($this) {
            self::EDITOR => __('Editor'),
            self::ASSISTANT => __('AI assistant'),
            self::AGENT => __('AI agent'),
            self::SYSTEM => __('System'),
        };
    }
}
