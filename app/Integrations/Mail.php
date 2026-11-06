<?php

declare(strict_types=1);

namespace App\Integrations;

use App\Enums\SettingKind;

final class Mail extends CardIntegration
{
    protected ?array $disconnects = ['mail_host', 'mail_username', 'mail_password', 'mail_from_address', 'mail_from_name'];

    public function key(): string
    {
        return 'mail';
    }

    public function settings(): array
    {
        return [
            'mail_provider' => SettingKind::EXPORTED,
            'mail_host' => SettingKind::PRIVATE,
            'mail_port' => SettingKind::EXPORTED,
            'mail_username' => SettingKind::PRIVATE,
            'mail_password' => SettingKind::SECRET,
            'mail_encryption' => SettingKind::PRIVATE,
            'mail_from_address' => SettingKind::PRIVATE,
            'mail_from_name' => SettingKind::PRIVATE,
        ];
    }

    public function connected(array $saved): bool
    {
        return $this->text($saved, 'mail_password') !== '';
    }

    /**
     * @param  array<string, mixed>  $saved
     */
    public function complete(array $saved): bool
    {
        return array_all(['mail_host', 'mail_username', 'mail_password', 'mail_from_address'], fn (string $key): bool => $this->text($saved, $key) !== '');
    }

    public function configKeys(): array
    {
        return [
            'mail.default',
            'mail.mailers.smtp.host',
            'mail.mailers.smtp.port',
            'mail.mailers.smtp.username',
            'mail.mailers.smtp.password',
            'mail.mailers.smtp.scheme',
            'mail.from.address',
            'mail.from.name',
        ];
    }

    public function config(array $saved): array
    {
        if (! $this->complete($saved)) {
            return [];
        }

        $fromName = $this->text($saved, 'mail_from_name');

        return [
            'mail.default' => 'smtp',
            'mail.mailers.smtp.host' => $this->text($saved, 'mail_host'),
            'mail.mailers.smtp.port' => is_numeric($saved['mail_port'] ?? null) ? (int) $saved['mail_port'] : 587,
            'mail.mailers.smtp.username' => $this->text($saved, 'mail_username'),
            'mail.mailers.smtp.password' => $this->text($saved, 'mail_password'),
            'mail.mailers.smtp.scheme' => ($saved['mail_encryption'] ?? null) === 'ssl' ? 'smtps' : null,
            'mail.from.address' => $this->text($saved, 'mail_from_address'),
            'mail.from.name' => $fromName !== '' ? $fromName : config()->string('app.name'),
        ];
    }

    public function hint(string $setting, string $saved): string
    {
        return $this->lastFour($saved);
    }
}
