<?php

declare(strict_types=1);

use App\Ai\Agents\ConnectionCheck;
use App\Mail\IntegrationTestMail;
use App\Models\Settings;
use App\Services\IntegrationTester;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;

/**
 * @return array{host: string, port: int, encryption: string, username: string, password: string, from_address: string, from_name: string}
 */
function mailCredentials(array $overrides = []): array
{
    return array_merge([
        'host' => 'smtp.example.com',
        'port' => 587,
        'encryption' => 'tls',
        'username' => 'postmaster',
        'password' => 'secret',
        'from_address' => 'hello@example.com',
        'from_name' => 'Example Site',
    ], $overrides);
}

it('posts a test message to the slack webhook', function (): void {
    Settings::set(['title' => ['en' => 'Acme Co']]);

    Http::fake(['hooks.slack.com/*' => Http::response('ok')]);

    $result = resolve(IntegrationTester::class)->slack('https://hooks.slack.com/services/T0/B0/x');

    expect($result->passed)->toBeTrue()
        ->and($result->message)->toBe('Test message posted to Slack.');

    Http::assertSent(fn ($request): bool => str_contains((string) $request['text'], 'Acme Co'));
});

it('reports a slack webhook that the api rejects', function (): void {
    Http::fake(['hooks.slack.com/*' => Http::response('no_service', 404)]);

    $result = resolve(IntegrationTester::class)->slack('https://hooks.slack.com/services/T0/B0/gone');

    expect($result->passed)->toBeFalse()
        ->and($result->message)->toBe('Slack rejected the webhook: no_service');
});

it('reports a slack webhook that cannot be reached', function (): void {
    Http::fake(fn (): never => throw new ConnectionException('cURL error 28: timed out'));

    $result = resolve(IntegrationTester::class)->slack('https://hooks.slack.com/services/T0/B0/slow');

    expect($result->passed)->toBeFalse()
        ->and($result->message)->toBe('Could not reach Slack: cURL error 28: timed out');
});

it('sends a branded test email through the given smtp credentials', function (): void {
    Mail::fake();

    $result = resolve(IntegrationTester::class)->mail(mailCredentials(), 'owner@example.com');

    expect($result->passed)->toBeTrue()
        ->and($result->message)->toBe('Test email sent to owner@example.com.')
        ->and(config('mail.mailers.integration_test'))->toMatchArray([
            'transport' => 'smtp',
            'host' => 'smtp.example.com',
            'port' => 587,
            'username' => 'postmaster',
            'password' => 'secret',
            'scheme' => null,
        ]);

    Mail::assertSent(IntegrationTestMail::class, function (IntegrationTestMail $mail): bool {
        $mail->assertFrom('hello@example.com', 'Example Site');
        $mail->assertHasSubject('Test email from '.config('app.name'));
        $mail->assertSeeInHtml('Your email settings work');

        return $mail->hasTo('owner@example.com');
    });
});

it('uses an implicit tls scheme on ssl and falls back to the brand for a blank from name', function (): void {
    Mail::fake();

    resolve(IntegrationTester::class)->mail(
        mailCredentials(['encryption' => 'ssl', 'port' => 465, 'from_name' => '']),
        'owner@example.com',
    );

    expect(config('mail.mailers.integration_test.scheme'))->toBe('smtps');

    Mail::assertSent(IntegrationTestMail::class, function (IntegrationTestMail $mail): true {
        $mail->assertFrom('hello@example.com', config('app.name'));

        return true;
    });
});

it('reports smtp credentials that cannot send', function (): void {
    Mail::partialMock()
        ->shouldReceive('mailer')
        ->andThrow(new RuntimeException('Connection could not be established with host "smtp.example.com"'));

    $result = resolve(IntegrationTester::class)->mail(mailCredentials(), 'owner@example.com');

    expect($result->passed)->toBeFalse()
        ->and($result->message)->toBe('Sending failed: Connection could not be established with host "smtp.example.com"');
});

it('prompts the configured provider to verify the assistant credentials', function (): void {
    ConnectionCheck::fake(['OK']);

    $result = resolve(IntegrationTester::class)->assistant('anthropic', 'sk-ant-test', 'claude-opus-4-8');

    expect($result->passed)->toBeTrue()
        ->and($result->message)->toBe('Connected to claude-opus-4-8.')
        ->and(config('ai.providers.anthropic.key'))->toBe('sk-ant-test');

    ConnectionCheck::assertPrompted('ping');
});

it('reports an assistant provider that answers with nothing', function (): void {
    ConnectionCheck::fake(['   ']);

    $result = resolve(IntegrationTester::class)->assistant('openai', 'sk-test', 'gpt-5');

    expect($result->passed)->toBeFalse()
        ->and($result->message)->toBe('The provider returned an empty response.');
});

it('reports an assistant provider that rejects the key', function (): void {
    ConnectionCheck::fake(fn (): never => throw new RuntimeException('invalid x-api-key'));

    $result = resolve(IntegrationTester::class)->assistant('gemini', 'nope', 'gemini-3-pro');

    expect($result->passed)->toBeFalse()
        ->and($result->message)->toBe('The provider rejected the request: invalid x-api-key');
});

it('asks the connection check agent for a single word', function (): void {
    expect(new ConnectionCheck()->instructions())->toContain('OK');
});
