<?php

declare(strict_types=1);

use App\Mail\IntegrationTestMail;
use App\Models\Settings;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;

it('asks for the email password again when the SMTP host changes', function (): void {
    Settings::set(['mail_provider' => 'custom', 'mail_host' => 'smtp.example.com', 'mail_password' => 'saved-password']);

    $this->actingAsAdmin();

    Livewire::test('admin.integrations.mail')
        ->set('form.mail_host', 'smtp.other.com')
        ->set('form.mail_username', 'user')
        ->set('form.mail_from_address', 'hi@example.com')
        ->set('form.mail_from_name', 'Site')
        ->call('connect')
        ->assertHasErrors(['form.mail_password' => 'required']);
});

it('connects email over custom smtp by persisting the settings', function (): void {
    $this->actingAsAdmin();

    Livewire::test('admin.integrations.mail')
        ->set('form.mail_provider', 'custom')
        ->set('form.mail_host', '  smtp.example.com  ')
        ->set('form.mail_port', 2525)
        ->set('form.mail_username', 'user@example.com')
        ->set('form.mail_password', 'secret-key')
        ->set('form.mail_encryption', 'tls')
        ->set('form.mail_from_address', '  hello@example.com  ')
        ->set('form.mail_from_name', 'Example Site')
        ->call('connect')
        ->assertHasNoErrors();

    expect(Settings::get('mail_host'))->toBe('smtp.example.com')
        ->and(Settings::get('mail_port'))->toBe(2525)
        ->and(Settings::get('mail_username'))->toBe('user@example.com')
        ->and(Settings::get('mail_password'))->toBe('secret-key')
        ->and(Settings::get('mail_from_address'))->toBe('hello@example.com')
        ->and(Settings::get('mail_provider'))->toBe('custom');
});

it('prefills host, port, encryption and username when a provider preset is chosen', function (): void {
    $this->actingAsAdmin();

    Livewire::test('admin.integrations.mail')
        ->set('form.mail_provider', 'resend')
        ->assertSet('form.mail_host', 'smtp.resend.com')
        ->assertSet('form.mail_port', 465)
        ->assertSet('form.mail_encryption', 'ssl')
        ->assertSet('form.mail_username', 'resend');
});

it('rejects invalid email connection settings', function (string $field, mixed $value, string $rule): void {
    $this->actingAsAdmin();

    Livewire::test('admin.integrations.mail')
        ->set('form.mail_provider', 'custom')
        ->set('form.mail_host', 'smtp.example.com')
        ->set('form.mail_username', 'user')
        ->set('form.mail_password', 'secret')
        ->set('form.mail_from_address', 'hi@example.com')
        ->set('form.mail_from_name', 'Site')
        ->set("form.{$field}", $value)
        ->call('connect')
        ->assertHasErrors(["form.{$field}" => $rule]);
})->with([
    'blank host' => ['mail_host', '', 'required'],
    'bad host' => ['mail_host', 'not a host!', 'regex'],
    'bad port' => ['mail_port', 70000, 'between'],
    'blank password' => ['mail_password', '', 'required'],
    'bad encryption' => ['mail_encryption', 'starttls', 'in'],
    'bad from address' => ['mail_from_address', 'not-an-email', 'email'],
]);

it('rejects a syntactically valid from address on a non-routable domain', function (): void {
    $this->actingAsAdmin();

    Livewire::test('admin.integrations.mail')
        ->set('form.mail_provider', 'custom')
        ->set('form.mail_host', 'smtp.example.com')
        ->set('form.mail_username', 'user')
        ->set('form.mail_password', 'secret')
        ->set('form.mail_from_address', 'hello@wire-up-no-such-domain.invalid')
        ->set('form.mail_from_name', 'Site')
        ->call('connect')
        ->assertHasErrors(['form.mail_from_address' => 'email']);
});

it('disconnects email by clearing the credentials', function (): void {
    Settings::set([
        'mail_host' => 'smtp.example.com',
        'mail_username' => 'user',
        'mail_password' => 'secret',
        'mail_from_address' => 'hi@example.com',
        'mail_from_name' => 'Site',
    ]);
    $this->actingAsAdmin();

    Livewire::test('admin.integrations.mail')
        ->call('disconnect')
        ->assertHasNoErrors();

    expect(Settings::get('mail_password'))->toBe('')
        ->and(Settings::get('mail_host'))->toBe('');
});

it('hydrates the saved email settings on mount', function (): void {
    Settings::set([
        'mail_provider' => 'sendgrid',
        'mail_host' => 'smtp.sendgrid.net',
        'mail_password' => 'sg-secret',
        'mail_encryption' => 'tls',
    ]);
    $this->actingAsAdmin();

    Livewire::test('admin.integrations.mail')
        ->assertSet('form.mail_provider', 'sendgrid')
        ->assertSet('form.mail_host', 'smtp.sendgrid.net')
        ->assertSet('form.mail_password', '')
        ->assertSet('form.mail_encryption', 'tls');
});

it('sends the test email to the communication email when one is set', function (): void {
    Mail::fake();

    Settings::set(['contact_email' => 'hello@example.com']);

    $this->actingAsAdmin();

    Livewire::test('admin.integrations.mail')
        ->set('form.mail_provider', 'custom')
        ->set('form.mail_host', 'smtp.example.com')
        ->set('form.mail_port', 2525)
        ->set('form.mail_username', 'user@example.com')
        ->set('form.mail_password', 'secret-key')
        ->set('form.mail_encryption', 'tls')
        ->set('form.mail_from_address', 'hello@example.com')
        ->set('form.mail_from_name', 'Example Site')
        ->call('test')
        ->assertHasNoErrors();

    Mail::assertSent(
        IntegrationTestMail::class,
        fn (IntegrationTestMail $mail): bool => $mail->hasTo('hello@example.com'),
    );

    expect(Settings::get('mail_host'))->toBeNull();
});

it('falls back to the logged-in admin for the test email', function (): void {
    Mail::fake();

    $this->actingAsAdmin();

    Livewire::test('admin.integrations.mail')
        ->assertSet('testEmailRecipient', 'admin@example.com')
        ->set('form.mail_provider', 'custom')
        ->set('form.mail_host', 'smtp.example.com')
        ->set('form.mail_port', 2525)
        ->set('form.mail_username', 'user@example.com')
        ->set('form.mail_password', 'secret-key')
        ->set('form.mail_encryption', 'tls')
        ->set('form.mail_from_address', 'hello@example.com')
        ->set('form.mail_from_name', 'Example Site')
        ->call('test')
        ->assertHasNoErrors();

    Mail::assertSent(
        IntegrationTestMail::class,
        fn (IntegrationTestMail $mail): bool => $mail->hasTo('admin@example.com'),
    );
});
