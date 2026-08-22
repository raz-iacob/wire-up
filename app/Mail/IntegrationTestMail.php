<?php

declare(strict_types=1);

namespace App\Mail;

use App\Services\SettingsService;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

final class IntegrationTestMail extends Mailable
{
    public function __construct(
        private readonly string $fromAddress,
        private readonly string $fromName,
    ) {}

    public function envelope(): Envelope
    {
        $brand = resolve(SettingsService::class)->brandName();

        return new Envelope(
            from: new Address($this->fromAddress, $this->fromName !== '' ? $this->fromName : $brand),
            subject: __('Test email from :site', ['site' => $brand]),
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.integration-test',
            with: ['brand' => resolve(SettingsService::class)->brandName()],
        );
    }
}
