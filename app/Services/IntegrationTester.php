<?php

declare(strict_types=1);

namespace App\Services;

use App\Ai\Agents\ConnectionCheck;
use App\Mail\IntegrationTestMail;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;
use RuntimeException;
use Sentry\EventId;
use Sentry\State\HubInterface;
use Throwable;

use function Illuminate\Support\php_binary;

final readonly class IntegrationTester
{
    public function __construct(private SettingsService $settings, private NewsletterService $newsletter, private HubInterface $sentry, private OffsiteBackupService $offsite) {}

    public function slack(string $webhookUrl): IntegrationTestResult
    {
        try {
            $response = Http::timeout(10)->post($webhookUrl, [
                'text' => __('Test message from :site. Your Slack notifications are working.', ['site' => $this->settings->brandName()]),
            ]);
        } catch (Throwable $exception) {
            return IntegrationTestResult::failed(__('Could not reach Slack: :reason', ['reason' => $this->reason($exception)]));
        }

        if (! $response->successful()) {
            return IntegrationTestResult::failed(__('Slack rejected the webhook: :reason', ['reason' => Str::limit(mb_trim($response->body()), 100)]));
        }

        return IntegrationTestResult::passed(__('Test message posted to Slack.'));
    }

    public function turnstile(string $secretKey): IntegrationTestResult
    {
        try {
            $response = Http::asForm()->timeout(10)->post(Turnstile::VERIFY_URL, [
                'secret' => $secretKey,
                'response' => 'wire-up-connection-test',
            ]);
        } catch (Throwable $exception) {
            return IntegrationTestResult::failed(__('Could not reach Cloudflare: :reason', ['reason' => $this->reason($exception)]));
        }

        $errors = $response->json('error-codes');

        if (is_array($errors) && in_array('invalid-input-secret', $errors, true)) {
            return IntegrationTestResult::failed(__('Cloudflare doesn’t recognise this secret key.'));
        }

        return IntegrationTestResult::passed(__('Cloudflare accepted the secret key.'));
    }

    /**
     * @param  array<string, mixed>  $settings
     */
    public function offsiteBackups(array $settings): IntegrationTestResult
    {
        $folder = $this->offsite->folder($settings);
        $probe = mb_ltrim($folder.'/.wire-up-connection-test', '/');

        try {
            $disk = $this->offsite->disk($settings);
            $disk->put($probe, 'Wire Up connection test');
            $disk->files($folder);
            $disk->delete($probe);
        } catch (Throwable $exception) {
            return IntegrationTestResult::failed(__('The storage refused the test: :reason', ['reason' => OffsiteBackupService::reason($exception)]));
        }

        return IntegrationTestResult::passed(__('Connected. Backups will be copied to :location.', ['location' => $this->offsite->location($settings)]));
    }

    public function newsletter(string $provider, string $apiKey, string $listId): IntegrationTestResult
    {
        $service = NewsletterService::PROVIDERS[$provider] ?? $provider;
        $client = $this->newsletter->client($provider, $apiKey);

        try {
            $response = match ($provider) {
                'mailchimp' => $client->get("lists/{$listId}", ['fields' => 'name']),
                'kit' => $client->get('forms', ['per_page' => 1000]),
                default => $client->get('newsletters'),
            };
        } catch (Throwable $exception) {
            return IntegrationTestResult::failed(__('Could not reach :service: :reason', ['service' => $service, 'reason' => $this->reason($exception)]));
        }

        if (! $response->successful()) {
            return IntegrationTestResult::failed(__(':service said: :reason', ['service' => $service, 'reason' => $this->newsletter->errorMessage($response)]));
        }

        $name = match ($provider) {
            'mailchimp' => $response->json('name'),
            'kit' => data_get($response->collect('forms')->firstWhere('id', (int) $listId), 'name'),
            default => $response->json('results.0.name'),
        };

        if (! is_string($name)) {
            return IntegrationTestResult::failed(__(':service has no form with the ID :id.', ['service' => $service, 'id' => $listId]));
        }

        return IntegrationTestResult::passed(__('Connected to :name on :service.', ['name' => $name, 'service' => $service]));
    }

    /**
     * @param  array{host: string, port: int, encryption: string, username: string, password: string, from_address: string, from_name: string}  $credentials
     */
    public function mail(array $credentials, string $recipient): IntegrationTestResult
    {
        config()->set('mail.mailers.integration_test', [
            'transport' => 'smtp',
            'host' => $credentials['host'],
            'port' => $credentials['port'],
            'username' => $credentials['username'],
            'password' => $credentials['password'],
            'scheme' => $credentials['encryption'] === 'ssl' ? 'smtps' : null,
            'timeout' => 15,
        ]);

        try {
            Mail::purge('integration_test');

            Mail::mailer('integration_test')
                ->to($recipient)
                ->send(new IntegrationTestMail($credentials['from_address'], $credentials['from_name']));
        } catch (Throwable $exception) {
            return IntegrationTestResult::failed(__('Sending failed: :reason', ['reason' => $this->reason($exception)]));
        }

        return IntegrationTestResult::passed(__('Test email sent to :email.', ['email' => $recipient]));
    }

    public function assistant(string $provider, string $apiKey, string $model): IntegrationTestResult
    {
        config()->set('ai.providers.'.$provider.'.key', $apiKey);

        try {
            $response = new ConnectionCheck()->prompt('ping', provider: $provider, model: $model, timeout: 20);
        } catch (Throwable $exception) {
            return IntegrationTestResult::failed(__('The provider rejected the request: :reason', ['reason' => $this->reason($exception)]));
        }

        if (mb_trim((string) $response) === '') {
            return IntegrationTestResult::failed(__('The provider returned an empty response.'));
        }

        return IntegrationTestResult::passed(__('Connected to :model.', ['model' => $model]));
    }

    public function sentry(): IntegrationTestResult
    {
        $eventId = $this->sentry->captureException(new RuntimeException(__('Test event from :site. Sentry is receiving its errors.', ['site' => $this->settings->brandName()])));

        if (! $eventId instanceof EventId) {
            return IntegrationTestResult::failed(__('Sentry didn’t take the test event. Check the DSN and try again.'));
        }

        return IntegrationTestResult::passed(__('Test event sent to Sentry with the ID :id.', ['id' => (string) $eventId]));
    }

    public function nightwatch(): IntegrationTestResult
    {
        $result = Process::path(base_path())->timeout(15)->run([php_binary(), 'artisan', 'nightwatch:status']);

        if ($result->successful()) {
            return IntegrationTestResult::passed(__('The Nightwatch agent is running and accepting connections.'));
        }

        $reason = preg_replace(['/\e\[[\d;]*m/', '/^\s*ERROR\s+/', '/\s+/'], ['', '', ' '], mb_trim($result->output().' '.$result->errorOutput()));

        return IntegrationTestResult::failed(__('The Nightwatch agent didn’t answer: :reason', ['reason' => Str::limit(mb_trim((string) $reason), 200)]));
    }

    private function reason(Throwable $exception): string
    {
        return Str::limit(mb_trim($exception->getMessage()), 200);
    }
}
