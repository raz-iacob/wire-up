<?php

declare(strict_types=1);

use App\Ai\Agents\SiteAssistant;
use App\Models\Page;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Laravel\Ai\Streaming\Events\TextDelta;
use Laravel\Ai\Streaming\Events\ToolCall as ToolCallEvent;
use Laravel\Ai\Streaming\Events\ToolResult as ToolResultEvent;
use Laravel\Ai\Tools\McpServerTool;
use Laravel\Ai\Tools\Request as ToolRequest;
use Livewire\Attributes\Locked;
use Livewire\Component;

return new class extends Component
{
    /**
     * @var array<string, array{0: string, 1: string}>
     */
    private const array TOOL_LABELS = [
        'create-page' => ['Creating page', 'Created page'],
        'update-page-blocks' => ['Updating page', 'Updated page'],
        'publish-page' => ['Publishing', 'Published'],
        'list-pages' => ['Reading pages', 'Read pages'],
        'get-page' => ['Reading page', 'Read page'],
        'update-design' => ['Updating design', 'Updated design'],
        'update-identity' => ['Updating identity', 'Updated identity'],
        'get-settings' => ['Reading settings', 'Read settings'],
        'get-menus' => ['Reading menus', 'Read menus'],
        'update-menu' => ['Updating menu', 'Updated menu'],
        'update-social' => ['Updating social links', 'Updated social links'],
        'list-media' => ['Reading media', 'Read media'],
        'import-media-from-url' => ['Importing media', 'Imported media'],
        'search-pexels' => ['Searching photos', 'Searched photos'],
        'import-pexels-media' => ['Importing photo', 'Imported photo'],
        'block-types' => ['Reading block types', 'Read block types'],
    ];

    #[Locked]
    public ?string $conversationId = null;

    /**
     * @var array<int, array{role: string, content: string, tools?: array<int, array{name: string, status: string}>, pending?: array<int, array{name: string, args: array<string, mixed>, status: string}>, error?: bool, animate?: bool}>
     */
    public array $messages = [];

    public function mount(): void
    {
        $agent = new SiteAssistant;
        $agent->continueLastConversation(auth()->user());

        $this->conversationId = $agent->currentConversation();
        $this->messages = $this->historyFrom($this->conversationId);
    }

    public function send(string $prompt): void
    {
        $this->authorize('assistant.use');

        $prompt = mb_substr(mb_trim($prompt), 0, 4000);

        if ($prompt === '') {
            return;
        }

        $key = 'assistant:'.auth()->id();

        if (RateLimiter::tooManyAttempts($key, maxAttempts: 20)) {
            $this->messages[] = [
                'role' => 'assistant',
                'content' => __('You’re sending messages too quickly. Please wait a moment and try again.'),
                'error' => true,
            ];

            return;
        }

        RateLimiter::hit($key, decaySeconds: 60);

        $this->stream(content: e($prompt), replace: true, to: 'question');

        $agent = new SiteAssistant;

        $this->conversationId !== null
            ? $agent->continue($this->conversationId, auth()->user())
            : $agent->forUser(auth()->user());

        $model = is_string(config('site.ai_model')) ? config()->string('site.ai_model') : '';
        $conversationId = $this->conversationId;
        $answer = '';
        $confirmable = SiteAssistant::confirmableToolNames();

        /** @var array<int, array{id: string, name: string, status: string}> $tools */
        $tools = [];

        /** @var array<int, array{name: string, args: array<string, mixed>, status: string}> $pending */
        $pending = [];

        try {
            $response = $model !== ''
                ? $agent->stream($prompt, model: $model)
                : $agent->stream($prompt);

            $response->then(function (object $streamed) use (&$conversationId): void {
                $conversationId = $streamed->conversationId ?? $conversationId;
            });

            foreach ($response as $event) {
                if ($event instanceof TextDelta) {
                    $answer .= $event->delta;
                } elseif ($event instanceof ToolCallEvent) {
                    if (in_array($event->toolCall->name, $confirmable, true)) {
                        $pending[] = ['name' => $event->toolCall->name, 'args' => $event->toolCall->arguments, 'status' => 'awaiting'];
                    } else {
                        $tools[] = ['id' => $event->toolCall->id, 'name' => $event->toolCall->name, 'status' => 'running'];
                        $this->stream(content: $this->toolChipsHtml($tools), replace: true, to: 'tools');
                    }
                } elseif ($event instanceof ToolResultEvent && ! in_array($event->toolResult->name, $confirmable, true)) {
                    $this->settleTool($tools, $event->toolResult->id, $event->toolResult->name, $event->successful);
                    $this->stream(content: $this->toolChipsHtml($tools), replace: true, to: 'tools');
                }
            }
        } catch (Throwable $exception) {
            report($exception);
            $this->messages[] = ['role' => 'user', 'content' => $prompt];
            $this->messages[] = [
                'role' => 'assistant',
                'content' => __('Sorry — I ran into a problem completing that request. Check the AI provider settings and try again.'),
                'error' => true,
            ];

            return;
        }

        $this->conversationId = $conversationId;
        $this->messages[] = ['role' => 'user', 'content' => $prompt];
        $this->messages[] = [
            'role' => 'assistant',
            'content' => $answer,
            'tools' => array_map(fn (array $tool): array => ['name' => $tool['name'], 'status' => $tool['status']], $tools),
            'pending' => $pending,
            'animate' => true,
        ];
    }

    public function confirmAction(int $message, int $action): void
    {
        $this->authorize('assistant.use');

        $pending = $this->messages[$message]['pending'][$action] ?? null;

        if ($pending === null || $pending['status'] !== 'awaiting') {
            return;
        }

        $class = SiteAssistant::confirmableToolClass($pending['name']);

        if ($class === null) {
            return;
        }

        new McpServerTool(resolve($class))->handle(new ToolRequest($pending['args']));

        $this->messages[$message]['pending'][$action]['status'] = 'confirmed';
    }

    public function rejectAction(int $message, int $action): void
    {
        $this->authorize('assistant.use');

        if (($this->messages[$message]['pending'][$action]['status'] ?? null) === 'awaiting') {
            $this->messages[$message]['pending'][$action]['status'] = 'rejected';
        }
    }

    /**
     * @param  array{name: string, args: array<string, mixed>, status: string}  $action
     */
    public function confirmLabel(array $action): string
    {
        if ($action['name'] === 'publish-page') {
            $title = Page::query()->whereKey($action['args']['page'] ?? null)->first()?->title;
            $draft = ($action['args']['status'] ?? 'published') === 'draft';

            return match (true) {
                $draft && $title !== null => __('Set “:title” back to draft?', ['title' => $title]),
                $draft => __('Set this page back to draft?'),
                $title !== null => __('Publish “:title”?', ['title' => $title]),
                default => __('Publish this page?'),
            };
        }

        return __('Approve this action?');
    }

    public function startNewConversation(): void
    {
        $this->authorize('assistant.use');

        $this->conversationId = null;
        $this->messages = [];
    }

    public function markdown(string $content): string
    {
        return Str::markdown($content, ['html_input' => 'escape', 'allow_unsafe_links' => false]);
    }

    /**
     * @param  array<int, array{name: string, status: string, id?: string}>  $tools
     */
    public function toolChipsHtml(array $tools): string
    {
        return collect($tools)->map(function (array $tool): string {
            $marker = match ($tool['status']) {
                'done' => '<span class="text-green-600 dark:text-green-400">&check;</span>',
                'failed' => '<span class="text-red-600 dark:text-red-400">&times;</span>',
                default => '<span class="size-1.5 rounded-full bg-amber-500 animate-pulse"></span>',
            };

            return '<span class="inline-flex items-center gap-1.5 rounded-md bg-zinc-100 px-2 py-1 text-xs text-zinc-600 dark:bg-zinc-700 dark:text-zinc-300">'
                .$marker.'<span>'.e($this->toolLabel($tool['name'], $tool['status'])).'</span></span>';
        })->implode('');
    }

    private function toolLabel(string $name, string $status): string
    {
        $labels = self::TOOL_LABELS[$name] ?? null;

        if ($labels === null) {
            return $name;
        }

        return __($status === 'done' ? $labels[1] : $labels[0]);
    }

    /**
     * @param  array<int, array{id: string, name: string, status: string}>  $tools
     */
    private function settleTool(array &$tools, string $id, string $name, bool $successful): void
    {
        foreach ($tools as $index => $tool) {
            if ($tool['id'] === $id || ($tool['status'] === 'running' && $tool['name'] === $name)) {
                $tools[$index]['status'] = $successful ? 'done' : 'failed';

                return;
            }
        }
    }

    /**
     * @return array<int, array{role: string, content: string, tools?: array<int, array{name: string, status: string}>}>
     */
    private function historyFrom(?string $conversationId): array
    {
        if ($conversationId === null) {
            return [];
        }

        $confirmable = SiteAssistant::confirmableToolNames();

        return DB::table('agent_conversation_messages')
            ->where('conversation_id', $conversationId)
            ->orderByDesc('id')
            ->limit(60)
            ->get()
            ->reverse()
            ->flatMap(fn (object $row): array => $this->historyMessage($row, $confirmable))
            ->values()
            ->all();
    }

    /**
     * @param  array<int, string>  $confirmable
     * @return array<int, array{role: string, content: string, tools?: array<int, array{name: string, status: string}>}>
     */
    private function historyMessage(object $row, array $confirmable): array
    {
        if ($row->role === 'user') {
            return filled($row->content) ? [['role' => 'user', 'content' => (string) $row->content]] : [];
        }

        if ($row->role !== 'assistant') {
            return [];
        }

        $decoded = json_decode((string) $row->tool_calls, true);

        $tools = collect(is_array($decoded) ? $decoded : [])
            ->pluck('name')
            ->filter(fn (mixed $name): bool => is_string($name) && ! in_array($name, $confirmable, true))
            ->map(fn (string $name): array => ['name' => $name, 'status' => 'done'])
            ->values()
            ->all();

        if (blank($row->content) && $tools === []) {
            return [];
        }

        return [['role' => 'assistant', 'content' => (string) $row->content, 'tools' => $tools]];
    }
};
?>

<div>
    <div class="fixed bottom-6 end-6 z-30">
        <flux:modal.trigger name="assistant">
            <flux:tooltip :content="__('AI Assistant')" position="left" class="contents">
                <flux:button
                    icon="sparkles"
                    variant="primary"
                    :aria-label="__('AI Assistant')"
                    class="size-14 rounded-full shadow-lg"
                />
            </flux:tooltip>
        </flux:modal.trigger>
    </div>

    <flux:modal
        name="assistant"
        flyout
        variant="floating"
        :closable="false"
        class="flex h-[85vh] max-h-[85vh] w-full flex-col p-6! md:w-md"
    >
        <div class="flex items-center gap-2 border-b border-zinc-200 pb-3 dark:border-zinc-700">
            <flux:heading size="lg">{{ __('AI Assistant') }}</flux:heading>
            @if ($messages !== [])
                <flux:tooltip :content="__('New chat')" position="bottom">
                    <flux:button
                        size="sm"
                        variant="subtle"
                        icon="pencil-square"
                        :aria-label="__('New chat')"
                        wire:click="startNewConversation"
                    />
                </flux:tooltip>
            @endif
            <flux:spacer />
            <flux:modal.close>
                <flux:button variant="subtle" icon="x-mark" :aria-label="__('Close')" />
            </flux:modal.close>
        </div>

        <div
            x-data
            x-init="
                const pin = (force = false) => { if (force || $el.scrollHeight - $el.scrollTop - $el.clientHeight < 150) { $el.scrollTop = $el.scrollHeight; } };
                $nextTick(() => pin(true));
                new MutationObserver(() => pin()).observe($el, { childList: true, subtree: true, characterData: true });
                window.addEventListener('modal-show', () => $nextTick(() => pin(true)));
            "
            class="-mx-2 flex flex-1 flex-col space-y-4 overflow-y-auto px-2 py-4"
        >
            @forelse ($messages as $index => $message)
                <div wire:key="assistant-message-{{ $index }}" @class([
                    'flex',
                    'justify-end' => $message['role'] === 'user',
                    'justify-start' => $message['role'] !== 'user',
                ])>
                    @if ($message['role'] === 'user')
                        <div class="max-w-[85%] rounded-2xl rounded-br-sm bg-zinc-900 px-4 py-2 text-sm text-white dark:bg-white dark:text-zinc-900">
                            {{ $message['content'] }}
                        </div>
                    @else
                        <div class="w-full space-y-2">
                            @if (($message['tools'] ?? []) !== [])
                                <div class="flex flex-wrap gap-1">{!! $this->toolChipsHtml($message['tools']) !!}</div>
                            @endif
                            @if ($message['error'] ?? false)
                                <div class="flex items-start gap-2 rounded-lg bg-red-50 px-3 py-2 text-sm text-red-700 dark:bg-red-500/10 dark:text-red-400">
                                    <flux:icon icon="exclamation-triangle" variant="micro" class="mt-0.5 shrink-0" />
                                    <span>{{ $message['content'] }}</span>
                                </div>
                            @elseif ($message['content'] !== '')
                                <div
                                    @if ($message['animate'] ?? false)
                                        x-data
                                        x-init="window.assistantTypewriter($el)"
                                        style="visibility: hidden"
                                    @endif
                                    class="prose prose-sm dark:prose-invert max-w-none text-sm text-zinc-800 dark:text-zinc-200"
                                >
                                    {!! $this->markdown($message['content']) !!}
                                </div>
                            @endif

                            @foreach ($message['pending'] ?? [] as $pendingIndex => $action)
                                <div wire:key="pending-{{ $index }}-{{ $pendingIndex }}" class="rounded-lg border border-zinc-200 bg-zinc-50 p-3 dark:border-zinc-700 dark:bg-zinc-800/60">
                                    <div class="flex items-center gap-2 text-sm text-zinc-700 dark:text-zinc-200">
                                        <flux:icon icon="megaphone" variant="micro" class="shrink-0 text-zinc-400" />
                                        <span>{{ $this->confirmLabel($action) }}</span>
                                    </div>
                                    @if ($action['status'] === 'awaiting')
                                        <div class="mt-3 flex gap-2">
                                            <flux:button size="xs" variant="primary" wire:click="confirmAction({{ $index }}, {{ $pendingIndex }})">
                                                {{ __('Confirm') }}
                                            </flux:button>
                                            <flux:button size="xs" variant="subtle" wire:click="rejectAction({{ $index }}, {{ $pendingIndex }})">
                                                {{ __('Cancel') }}
                                            </flux:button>
                                        </div>
                                    @elseif ($action['status'] === 'confirmed')
                                        <div class="mt-2 flex items-center gap-1.5 text-xs text-green-600 dark:text-green-400">
                                            <flux:icon icon="check" variant="micro" />{{ __('Done') }}
                                        </div>
                                    @else
                                        <div class="mt-2 text-xs text-zinc-500">{{ __('Cancelled') }}</div>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            @empty
                <div wire:loading.remove wire:target="send" class="m-auto flex flex-col items-center justify-center gap-3 text-center">
                    <flux:icon icon="sparkles" class="size-8 text-zinc-400" />
                    <flux:text class="max-w-xs">
                        {{ __('Ask me to build pages, edit content, adjust the design or set up your menus.') }}
                    </flux:text>
                </div>
            @endforelse

            <div wire:loading.flex wire:target="send" class="flex-col gap-4" wire:key="assistant-active">
                <div class="flex justify-end">
                    <div class="max-w-[85%] rounded-2xl rounded-br-sm bg-zinc-900 px-4 py-2 text-sm text-white dark:bg-white dark:text-zinc-900">
                        <span wire:stream="question"></span>
                    </div>
                </div>
                <div class="flex justify-start">
                    <div class="w-full space-y-2 text-sm text-zinc-800 dark:text-zinc-200">
                        <div wire:stream="tools" class="flex flex-wrap gap-1"></div>
                        <div class="flex items-center gap-1 py-1">
                            <span class="size-1.5 animate-bounce rounded-full bg-zinc-400 [animation-delay:-0.3s]"></span>
                            <span class="size-1.5 animate-bounce rounded-full bg-zinc-400 [animation-delay:-0.15s]"></span>
                            <span class="size-1.5 animate-bounce rounded-full bg-zinc-400"></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <form
            x-data
            x-on:submit.prevent="
                const input = $el.querySelector('textarea');
                const value = (input?.value ?? '').trim();
                if (value === '') return;
                window.assistantAborted = false;
                input.value = '';
                input.dispatchEvent(new Event('input', { bubbles: true }));
                $wire.send(value);
            "
            class="pt-3"
        >
            <flux:composer
                name="prompt"
                submit="enter"
                rows="1"
                inline
                :label="__('Message')"
                label:sr-only
                :placeholder="__('Ask the assistant to build or edit your site…')"
            >
                <x-slot name="actionsTrailing">
                    <flux:button
                        type="submit"
                        size="sm"
                        variant="primary"
                        icon="paper-airplane"
                        :aria-label="__('Send')"
                        wire:loading.remove
                        wire:target="send"
                    />
                    <flux:button
                        type="button"
                        size="sm"
                        variant="filled"
                        icon="stop"
                        :aria-label="__('Stop')"
                        wire:loading.flex
                        wire:target="send"
                        x-on:click="window.assistantStop()"
                    />
                </x-slot>
            </flux:composer>
        </form>
    </flux:modal>

    @script
    <script>
        window.assistantTypewriter = function (el) {
            if (el.dataset.tw) return;
            el.dataset.tw = '1';

            let textNodes = [];
            let walker = document.createTreeWalker(el, NodeFilter.SHOW_TEXT);
            while (walker.nextNode()) textNodes.push(walker.currentNode);

            let words = [];
            textNodes.forEach(function (node) {
                if (! node.textContent) return;
                let frag = document.createDocumentFragment();
                node.textContent.split(/(\s+)/).forEach(function (part) {
                    if (part === '') return;
                    let span = document.createElement('span');
                    span.textContent = part;
                    if (/\S/.test(part)) {
                        span.style.opacity = '0';
                        span.style.transition = 'opacity 220ms ease';
                        words.push(span);
                    }
                    frag.appendChild(span);
                });
                node.replaceWith(frag);
            });

            el.style.visibility = 'visible';

            let i = 0;
            let step = function () {
                if (window.assistantAborted) {
                    words.forEach(function (word) { word.style.opacity = '1'; });
                    return;
                }
                for (let n = 0; n < 2 && i < words.length; n++, i++) {
                    words[i].style.opacity = '1';
                }
                if (i < words.length) setTimeout(step, 24);
            };
            step();
        };

        let inFlight = null;

        $wire.$interceptRequest(function (intercept) {
            let request = intercept && intercept.request ? intercept.request : intercept;
            inFlight = request;

            let clear = function () { if (inFlight === request) inFlight = null; };

            if (intercept && typeof intercept.onFinish === 'function') intercept.onFinish(clear);
            if (intercept && typeof intercept.onError === 'function') intercept.onError(clear);
            if (intercept && typeof intercept.onCancel === 'function') intercept.onCancel(clear);
        });

        window.assistantStop = function () {
            window.assistantAborted = true;

            try {
                if (inFlight) {
                    if (typeof inFlight.cancel === 'function') inFlight.cancel();
                    else if (inFlight.controller && typeof inFlight.controller.abort === 'function') inFlight.controller.abort();
                }
            } catch (error) {
                // Nothing else to do — the reveal has already been completed.
            }

            inFlight = null;
        };
    </script>
    @endscript
</div>
