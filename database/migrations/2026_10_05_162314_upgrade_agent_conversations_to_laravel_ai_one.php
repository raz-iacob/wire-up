<?php

declare(strict_types=1);

use Illuminate\Database\Query\Builder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Laravel\Ai\Enums\MessageStatus;
use Laravel\Ai\Migrations\AiMigration;

return new class extends AiMigration
{
    public function up(): void
    {
        $table = $this->messagesTable();

        Schema::connection($this->getConnection())->table($table, function (Blueprint $blueprint): void {
            $blueprint->longText('steps')->nullable();
            $blueprint->string('status', 25)->default(MessageStatus::Completed->value);
        });

        $this->query($table)->where('role', 'user')->update(['steps' => '[]']);

        $this->query($table)
            ->select('conversation_id')
            ->distinct()
            ->orderBy('conversation_id')
            ->chunk(100, function (Collection $conversations) use ($table): void {
                foreach ($conversations as $conversation) {
                    $this->backfill($table, (string) $conversation->conversation_id);
                }
            });

        $this->query($table)->whereNull('steps')->update(['steps' => '[]']);

        Schema::connection($this->getConnection())->table($table, function (Blueprint $blueprint): void {
            $blueprint->longText('steps')->nullable(false)->change();
            $blueprint->dropIndex('participant_index');
            $blueprint->dropColumn(['tool_calls', 'tool_results', 'approval_state']);
            $blueprint->index(['participant_type', 'participant_id', 'agent'], 'participant_index');
        });
    }

    public function down(): void
    {
        Schema::connection($this->getConnection())->table($this->messagesTable(), function (Blueprint $blueprint): void {
            $blueprint->dropIndex('participant_index');
            $blueprint->text('tool_calls')->nullable();
            $blueprint->text('tool_results')->nullable();
            $blueprint->text('approval_state')->nullable();
            $blueprint->dropColumn(['steps', 'status']);
            $blueprint->index(['participant_type', 'participant_id'], 'participant_index');
        });
    }

    private function backfill(string $table, string $conversationId): void
    {
        $rows = $this->query($table)
            ->where('conversation_id', $conversationId)
            ->where('role', 'assistant')
            ->orderBy('id')
            ->get();

        $results = $rows
            ->flatMap(fn (object $row): array => $this->decoded($row->tool_results ?? null))
            ->filter(fn (mixed $result): bool => is_array($result) && is_string($result['id'] ?? null))
            ->keyBy('id');

        foreach ($rows as $row) {
            $meta = $this->decoded($row->meta ?? null);

            $calls = collect($this->decoded($row->tool_calls ?? null))
                ->filter(fn (mixed $call): bool => is_array($call) && is_string($call['id'] ?? null) && $results->has($call['id']))
                ->map(fn (array $call): array => $this->withResult($call, $results->get($call['id'])))
                ->values()
                ->all();

            $content = is_string($row->content ?? null) ? $row->content : '';
            $reasoning = is_string($meta['reasoning'] ?? null) ? $meta['reasoning'] : '';

            $steps = $calls !== [] && $content !== ''
                ? [$this->step('', $calls), $this->step($content, [], $reasoning)]
                : [$this->step($content, $calls, $reasoning)];

            unset($meta['provider_steps'], $meta['provider_content_blocks'], $meta['reasoning']);

            $this->query($table)->where('id', $row->id)->update([
                'steps' => json_encode($steps, JSON_THROW_ON_ERROR),
                'meta' => json_encode($meta, JSON_THROW_ON_ERROR),
            ]);
        }
    }

    /**
     * @param  array<array-key, mixed>  $call
     * @return array<array-key, mixed>
     */
    private function withResult(array $call, mixed $result): array
    {
        $result = is_array($result) ? $result : [];

        return [
            ...$call,
            'result' => $result['result'] ?? null,
            ...array_filter([
                'denied' => (bool) ($result['denied'] ?? false),
                'failed' => (bool) ($result['failed'] ?? false),
            ]),
        ];
    }

    /**
     * @param  list<array<array-key, mixed>>  $calls
     * @return array{content: string, tool_calls: list<array<array-key, mixed>>, reasoning: string, replay_blocks: array<never>, provider_tool_calls: array<never>}
     */
    private function step(string $content, array $calls = [], string $reasoning = ''): array
    {
        return [
            'content' => $content,
            'tool_calls' => $calls,
            'reasoning' => $reasoning,
            'replay_blocks' => [],
            'provider_tool_calls' => [],
        ];
    }

    /**
     * @return array<array-key, mixed>
     */
    private function decoded(mixed $json): array
    {
        $decoded = is_string($json) ? json_decode($json, true) : null;

        return is_array($decoded) ? $decoded : [];
    }

    private function query(string $table): Builder
    {
        return DB::connection($this->getConnection())->table($table);
    }

    private function messagesTable(): string
    {
        return config()->string('ai.conversations.tables.messages', 'agent_conversation_messages');
    }
};
