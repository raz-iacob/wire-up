<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

final readonly class NewsletterService
{
    /**
     * @var array<string, string>
     */
    public const array PROVIDERS = ['mailchimp' => 'Mailchimp', 'kit' => 'Kit', 'buttondown' => 'Buttondown'];

    /**
     * @var list<string>
     */
    public const array DOUBLE_OPT_IN_PROVIDERS = ['mailchimp', 'buttondown'];

    public function provider(): ?string
    {
        $provider = config('site.newsletter_provider');

        return is_string($provider) && array_key_exists($provider, self::PROVIDERS) && $this->setting('newsletter_api_key') !== '' ? $provider : null;
    }

    public function providerName(): ?string
    {
        $provider = $this->provider();

        return $provider === null ? null : self::PROVIDERS[$provider];
    }

    public function subscribe(string $email, ?string $name = null, ?string $ip = null): NewsletterResult
    {
        $provider = $this->provider();

        if ($provider === null) {
            return NewsletterResult::failed(__('No newsletter service is connected.'), retryable: false);
        }

        $client = $this->client($provider, $this->setting('newsletter_api_key'));
        $listId = $this->setting('newsletter_list_id');
        $doubleOptIn = (bool) config('site.newsletter_double_opt_in', true);

        try {
            $response = match ($provider) {
                'mailchimp' => $client->put("lists/{$listId}/members/".md5(mb_strtolower($email)), array_filter([
                    'email_address' => $email,
                    'status_if_new' => $doubleOptIn ? 'pending' : 'subscribed',
                    'merge_fields' => $name !== null ? ['FNAME' => $name] : null,
                    'ip_signup' => $ip,
                ])),
                'kit' => $this->subscribeToKit($client, $listId, $email, $name),
                default => $client->post('subscribers', array_filter([
                    'email_address' => $email,
                    'type' => $doubleOptIn ? null : 'regular',
                    'metadata' => $name !== null ? ['first_name' => $name] : null,
                    'ip_address' => $ip,
                ])),
            };
        } catch (ConnectionException $exception) {
            return NewsletterResult::failed(Str::limit($exception->getMessage(), 300), retryable: true);
        }

        if ($response->successful() || $response->json('code') === 'email_already_exists') {
            return NewsletterResult::synced();
        }

        return NewsletterResult::failed($this->errorMessage($response), retryable: $response->serverError() || $response->status() === 429);
    }

    public function client(string $provider, string $apiKey): PendingRequest
    {
        $request = Http::acceptJson()->asJson()->timeout(10)->withUserAgent('Wire Up');

        return match ($provider) {
            'mailchimp' => $request->baseUrl('https://'.$this->mailchimpDataCenter($apiKey).'.api.mailchimp.com/3.0/')->withBasicAuth('wireup', $apiKey),
            'kit' => $request->baseUrl('https://api.kit.com/v4/')->withHeaders(['X-Kit-Api-Key' => $apiKey]),
            default => $request->baseUrl('https://api.buttondown.com/v1/')->withToken($apiKey, 'Token'),
        };
    }

    public function errorMessage(Response $response): string
    {
        $detail = $response->json('detail') ?? $response->json('errors.0');

        return is_string($detail) && $detail !== ''
            ? Str::limit($detail, 300)
            : __('The service answered with HTTP :status.', ['status' => $response->status()]);
    }

    private function subscribeToKit(PendingRequest $client, string $formId, string $email, ?string $name): Response
    {
        $response = $client->post('subscribers', array_filter(['email_address' => $email, 'first_name' => $name]));

        if (! $response->successful()) {
            return $response;
        }

        return $client->post("forms/{$formId}/subscribers", ['email_address' => $email]);
    }

    private function mailchimpDataCenter(string $apiKey): string
    {
        return preg_match('/-([a-z]{2,4}\d{1,3})$/', $apiKey, $matches) === 1 ? $matches[1] : 'us1';
    }

    private function setting(string $key): string
    {
        $value = config('site.'.$key);

        return is_string($value) ? $value : '';
    }
}
