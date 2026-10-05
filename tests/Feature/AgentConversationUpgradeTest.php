<?php

declare(strict_types=1);

use App\Ai\Agents\SiteAssistant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

function upgradeMigration(): object
{
    return require database_path('migrations/2026_10_05_162314_upgrade_agent_conversations_to_laravel_ai_one.php');
}

/**
 * @param  array<string, mixed>  $overrides
 */
function seedLegacyMessage(string $conversationId, string $role, array $overrides = []): string
{
    $id = (string) Str::uuid7();

    DB::table('agent_conversation_messages')->insert([
        'id' => $id,
        'conversation_id' => $conversationId,
        'participant_type' => (new User)->getMorphClass(),
        'participant_id' => 1,
        'agent' => SiteAssistant::class,
        'role' => $role,
        'content' => '',
        'attachments' => '[]',
        'tool_calls' => '[]',
        'tool_results' => '[]',
        'usage' => '[]',
        'meta' => '[]',
        'approval_state' => null,
        'created_at' => now(),
        'updated_at' => now(),
        ...$overrides,
    ]);

    return $id;
}

/**
 * @return list<array<string, mixed>>
 */
function stepsOf(string $id): array
{
    return json_decode((string) DB::table('agent_conversation_messages')->where('id', $id)->value('steps'), true);
}

beforeEach(function (): void {
    upgradeMigration()->down();
});

it('folds a completed tool call and its result into one step', function (): void {
    $id = seedLegacyMessage('c1', 'assistant', [
        'tool_calls' => json_encode([['id' => 'call_1', 'name' => 'list-pages', 'arguments' => []]]),
        'tool_results' => json_encode([['id' => 'call_1', 'result' => 'three pages']]),
    ]);

    upgradeMigration()->up();

    expect(stepsOf($id))->toHaveCount(1)
        ->and(stepsOf($id)[0]['tool_calls'][0])->toMatchArray(['id' => 'call_1', 'name' => 'list-pages', 'result' => 'three pages']);
});

it('splits a turn with both tool calls and text into a tool step then a text step', function (): void {
    $id = seedLegacyMessage('c2', 'assistant', [
        'content' => 'Here are your pages.',
        'tool_calls' => json_encode([['id' => 'call_1', 'name' => 'list-pages', 'arguments' => []]]),
        'tool_results' => json_encode([['id' => 'call_1', 'result' => 'ok']]),
        'meta' => json_encode(['reasoning' => 'Look them up first.']),
    ]);

    upgradeMigration()->up();

    $steps = stepsOf($id);

    expect($steps)->toHaveCount(2)
        ->and($steps[0]['content'])->toBe('')
        ->and($steps[0]['tool_calls'][0]['name'])->toBe('list-pages')
        ->and($steps[1]['content'])->toBe('Here are your pages.')
        ->and($steps[1]['reasoning'])->toBe('Look them up first.');
});

it('moves reasoning out of meta and leaves the rest of meta alone', function (): void {
    $id = seedLegacyMessage('c3', 'assistant', [
        'content' => 'Done.',
        'meta' => json_encode(['reasoning' => 'Thinking.', 'provider_steps' => ['x'], 'keep' => 'me']),
    ]);

    upgradeMigration()->up();

    expect(json_decode((string) DB::table('agent_conversation_messages')->where('id', $id)->value('meta'), true))
        ->toBe(['keep' => 'me']);
});

it('drops a tool call that never got a result, as the upgrade guide warns', function (): void {
    $id = seedLegacyMessage('c4', 'assistant', [
        'tool_calls' => json_encode([
            ['id' => 'call_done', 'name' => 'list-pages', 'arguments' => []],
            ['id' => 'call_pending', 'name' => 'delete-page', 'arguments' => []],
        ]),
        'tool_results' => json_encode([['id' => 'call_done', 'result' => 'ok']]),
        'approval_state' => 'pending',
    ]);

    upgradeMigration()->up();

    expect(array_column(stepsOf($id)[0]['tool_calls'], 'id'))->toBe(['call_done']);
});

it('gives user messages and unexpected roles an empty step list', function (): void {
    $user = seedLegacyMessage('c5', 'user', ['content' => 'Hello']);
    $system = seedLegacyMessage('c5', 'system', ['content' => 'Be nice.']);

    upgradeMigration()->up();

    expect(stepsOf($user))->toBe([])
        ->and(stepsOf($system))->toBe([]);
});

it('marks every migrated message completed and removes the old columns', function (): void {
    $id = seedLegacyMessage('c6', 'assistant', ['content' => 'Hi.']);

    upgradeMigration()->up();

    expect(DB::table('agent_conversation_messages')->where('id', $id)->value('status'))->toBe('completed')
        ->and(Schema::hasColumns('agent_conversation_messages', ['tool_calls', 'tool_results', 'approval_state']))->toBeFalse()
        ->and(Schema::hasColumns('agent_conversation_messages', ['steps', 'status']))->toBeTrue();
});

it('ignores garbage in the legacy json columns instead of failing the upgrade', function (): void {
    $id = seedLegacyMessage('c7', 'assistant', [
        'content' => 'Hi.',
        'tool_calls' => 'not json',
        'tool_results' => json_encode(['not', 'a', 'list of results']),
        'meta' => 'nope',
    ]);

    upgradeMigration()->up();

    expect(stepsOf($id))->toHaveCount(1)
        ->and(stepsOf($id)[0]['content'])->toBe('Hi.')
        ->and(stepsOf($id)[0]['tool_calls'])->toBe([]);
});
