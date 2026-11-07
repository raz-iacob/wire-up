<?php

declare(strict_types=1);

namespace App\Mcp\Support;

use App\Ai\Contracts\HiddenFromAssistant;
use App\Ai\Contracts\RequiresConfirmation;
use App\Enums\PermissionAction;
use App\Enums\TokenAccess;
use App\Mcp\Servers\WireUpServer;
use App\Mcp\Tools\CreateCategoryTool;
use App\Mcp\Tools\CreatePageTool;
use App\Mcp\Tools\CreateRecordTool;
use App\Mcp\Tools\DeleteMediaTool;
use App\Mcp\Tools\DeletePageTool;
use App\Mcp\Tools\DeleteRecordTool;
use App\Mcp\Tools\EditBlocksTool;
use App\Mcp\Tools\GetContentStringsTool;
use App\Mcp\Tools\GetInterfaceTranslationsTool;
use App\Mcp\Tools\GetMenusTool;
use App\Mcp\Tools\GetPageTool;
use App\Mcp\Tools\GetRecordTool;
use App\Mcp\Tools\GetSettingsTool;
use App\Mcp\Tools\ImportMediaFromUrlTool;
use App\Mcp\Tools\ImportPexelsMediaTool;
use App\Mcp\Tools\ListCategoriesTool;
use App\Mcp\Tools\ListContentTypesTool;
use App\Mcp\Tools\ListImportFilesTool;
use App\Mcp\Tools\ListMediaTool;
use App\Mcp\Tools\ListPagesTool;
use App\Mcp\Tools\ListRecordsTool;
use App\Mcp\Tools\ListRedirectsTool;
use App\Mcp\Tools\PublishPageTool;
use App\Mcp\Tools\PublishRecordTool;
use App\Mcp\Tools\ReadWebpageTool;
use App\Mcp\Tools\RenderPageTool;
use App\Mcp\Tools\ReplaceTextTool;
use App\Mcp\Tools\ScaffoldSiteTool;
use App\Mcp\Tools\SearchContentTool;
use App\Mcp\Tools\SearchPexelsTool;
use App\Mcp\Tools\UpdateContentStringsTool;
use App\Mcp\Tools\UpdateMediaTool;
use App\Mcp\Tools\UpdatePageBlocksTool;
use App\Mcp\Tools\UpdatePageTool;
use App\Mcp\Tools\UpdateRecordTool;
use App\Mcp\Tools\UploadMediaTool;
use App\Models\AccessToken;
use App\Models\Record;
use App\Models\RecordType;
use App\Models\User;
use App\Services\PermissionRegistry;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Laravel\Mcp\Server\Tool;

final readonly class RemoteAccess
{
    /**
     * @var list<class-string<Tool>>
     */
    public const array READ_TOOLS = [
        ListPagesTool::class,
        GetPageTool::class,
        ListContentTypesTool::class,
        ListRecordsTool::class,
        GetRecordTool::class,
        ListCategoriesTool::class,
        ListMediaTool::class,
        ListImportFilesTool::class,
        RenderPageTool::class,
        ReadWebpageTool::class,
        SearchPexelsTool::class,
        ListRedirectsTool::class,
        GetSettingsTool::class,
        GetMenusTool::class,
        GetInterfaceTranslationsTool::class,
        GetContentStringsTool::class,
        SearchContentTool::class,
    ];

    public function __construct(private AccessToken $token, private User $user) {}

    public static function current(): ?self
    {
        $token = request()->attributes->get(AccessToken::REQUEST_ATTRIBUTE);

        return $token instanceof AccessToken ? new self($token, $token->user) : null;
    }

    public static function level(string $class): ?TokenAccess
    {
        return match (true) {
            is_a($class, HiddenFromAssistant::class, true) => null,
            is_a($class, RequiresConfirmation::class, true) => TokenAccess::Full,
            in_array($class, self::READ_TOOLS, true) => TokenAccess::Read,
            default => TokenAccess::Edit,
        };
    }

    /**
     * @param  array<int, class-string<Tool>>  $classes
     * @return list<class-string<Tool>>
     */
    public function visibleTools(array $classes): array
    {
        return array_values(array_filter($classes, function (string $class): bool {
            $level = self::level($class);

            return $level instanceof TokenAccess
                && $this->token->access->allows($level)
                && $this->unmet($this->abilities($class, [])) === null;
        }));
    }

    public function hiddenTool(string $name): ?Tool
    {
        foreach (WireUpServer::TOOLS as $class) {
            $tool = resolve($class);

            if ($tool->name() === $name && self::level($class) instanceof TokenAccess) {
                return $tool;
            }
        }

        return null;
    }

    /**
     * @param  array<array-key, mixed>  $arguments
     */
    public function problem(Tool $tool, array $arguments): ?string
    {
        $level = self::level($tool::class) ?? TokenAccess::Full;

        if (! $this->token->access->allows($level)) {
            return $level === TokenAccess::Full
                ? __('Publishing, deleting and replace-text need a token with full access.')
                : __('This token can only read. Make an edit token under Account → Security.');
        }

        if ($this->token->access !== TokenAccess::Full && $this->publishes($tool, $arguments)) {
            return __('Publishing needs a token with full access. Leave out :argument to save a draft.', [
                'argument' => $tool instanceof UpdateRecordTool ? '"status"' : '"publish"',
            ]);
        }

        $unmet = $this->unmet($this->abilities($tool::class, $arguments));

        return $unmet === null ? null : $this->roleRefusal($unmet);
    }

    /**
     * @param  array<array-key, mixed>  $arguments
     */
    private function publishes(Tool $tool, array $arguments): bool
    {
        return match (true) {
            $tool instanceof CreatePageTool, $tool instanceof CreateRecordTool => filter_var($arguments['publish'] ?? false, FILTER_VALIDATE_BOOLEAN),
            $tool instanceof UpdateRecordTool => ($arguments['status'] ?? null) !== null,
            default => false,
        };
    }

    /**
     * @param  array<array-key, mixed>  $arguments
     * @return list<list<string>>
     */
    private function abilities(string $class, array $arguments): array
    {
        return match ($class) {
            ListPagesTool::class, GetPageTool::class => [['pages.view']],
            CreatePageTool::class => [['pages.create']],
            UpdatePageBlocksTool::class, UpdatePageTool::class, PublishPageTool::class => [['pages.edit']],
            DeletePageTool::class => [['pages.delete']],
            ScaffoldSiteTool::class => [['pages.create'], ['pages.edit'], ['settings.edit']],
            EditBlocksTool::class => $this->pageOrRecord($arguments['page'] ?? null, $arguments['record'] ?? null, PermissionAction::Edit),
            ListRecordsTool::class => $this->recordType($arguments['type'] ?? null, PermissionAction::View),
            CreateRecordTool::class => $this->recordType($arguments['type'] ?? null, PermissionAction::Create),
            GetRecordTool::class => $this->pageOrRecord(null, $arguments['record'] ?? null, PermissionAction::View, pages: false),
            UpdateRecordTool::class, PublishRecordTool::class => $this->pageOrRecord(null, $arguments['record'] ?? null, PermissionAction::Edit, pages: false),
            DeleteRecordTool::class => $this->pageOrRecord(null, $arguments['record'] ?? null, PermissionAction::Delete, pages: false),
            GetContentStringsTool::class => $this->contentStrings($arguments, PermissionAction::View),
            UpdateContentStringsTool::class => $this->contentStrings($arguments, PermissionAction::Edit),
            SearchContentTool::class => $this->search($arguments, PermissionAction::View),
            ReplaceTextTool::class => $this->search($arguments, PermissionAction::Edit),
            ListCategoriesTool::class => [['categories.view']],
            CreateCategoryTool::class => [['categories.create']],
            ListContentTypesTool::class, ListMediaTool::class, ImportMediaFromUrlTool::class, ListImportFilesTool::class,
            UploadMediaTool::class, UpdateMediaTool::class, DeleteMediaTool::class, RenderPageTool::class,
            ReadWebpageTool::class, SearchPexelsTool::class, ImportPexelsMediaTool::class => [],
            GetSettingsTool::class, GetMenusTool::class, GetInterfaceTranslationsTool::class, ListRedirectsTool::class => [['settings.view']],
            default => [['settings.edit']],
        };
    }

    /**
     * @return list<list<string>>
     */
    private function pageOrRecord(mixed $page, mixed $record, PermissionAction $action, bool $pages = true): array
    {
        if (is_numeric($record)) {
            return $this->recordType(Record::query()->with('recordType')->find((int) $record)?->recordType->key, $action);
        }

        if ($pages && is_numeric($page)) {
            return [['pages.'.$action->value]];
        }

        return [[...($pages ? ['pages.'.$action->value] : []), ...$this->anyRecordType($action)]];
    }

    /**
     * @return list<list<string>>
     */
    private function recordType(mixed $type, PermissionAction $action): array
    {
        return in_array($type, $this->typeKeys(), true)
            ? [["records.{$type}.{$action->value}"]]
            : [$this->anyRecordType($action)];
    }

    /**
     * @param  array<array-key, mixed>  $arguments
     * @return list<list<string>>
     */
    private function contentStrings(array $arguments, PermissionAction $action): array
    {
        return match ($arguments['type'] ?? null) {
            'page' => [['pages.'.$action->value]],
            'record' => $this->pageOrRecord(null, $arguments['id'] ?? null, $action, pages: false),
            default => $this->pageOrRecord(null, null, $action),
        };
    }

    /**
     * @param  array<array-key, mixed>  $arguments
     * @return list<list<string>>
     */
    private function search(array $arguments, PermissionAction $action): array
    {
        $pages = is_array($arguments['pages'] ?? null) ? $arguments['pages'] : [];
        $records = is_array($arguments['records'] ?? null) ? $arguments['records'] : [];

        if ($pages === [] && $records === []) {
            return [['pages.'.$action->value], ...array_map(fn (string $key): array => ["records.{$key}.{$action->value}"], $this->typeKeys())];
        }

        $types = Record::query()
            ->whereIn('id', array_filter($records, is_numeric(...)))
            ->with('recordType')
            ->get()
            ->map(fn (Record $record): string => $record->recordType->key)
            ->unique()
            ->values()
            ->all();

        return [
            ...($pages === [] ? [] : [['pages.'.$action->value]]),
            ...array_map(fn (string $key): array => ["records.{$key}.{$action->value}"], $types),
        ];
    }

    /**
     * @return list<string>
     */
    private function anyRecordType(PermissionAction $action): array
    {
        return array_map(fn (string $key): string => "records.{$key}.{$action->value}", $this->typeKeys());
    }

    /**
     * @return list<string>
     */
    private function typeKeys(): array
    {
        /** @var list<string> */
        return RecordType::query()->orderBy('id')->pluck('key')->all();
    }

    /**
     * @param  list<list<string>>  $requirements
     * @return list<string>|null
     */
    private function unmet(array $requirements): ?array
    {
        $gate = Gate::forUser($this->user);

        foreach ($requirements as $anyOf) {
            if (! array_any($anyOf, fn (string $ability): bool => $gate->allows($ability))) {
                return $anyOf;
            }
        }

        return null;
    }

    /**
     * @param  list<string>  $anyOf
     */
    private function roleRefusal(array $anyOf): string
    {
        if (count($anyOf) !== 1) {
            return __('Your role doesn’t allow this.');
        }

        $resource = Str::beforeLast($anyOf[0], '.');
        $action = PermissionAction::from(Str::afterLast($anyOf[0], '.'));
        $label = collect(PermissionRegistry::resources())->firstWhere('key', $resource)['label'] ?? $resource;

        return __('Your role can’t :action :resource.', [
            'action' => mb_strtolower($action->label()),
            'resource' => $label,
        ]);
    }
}
