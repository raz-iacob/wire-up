<?php

declare(strict_types=1);

use App\Models\Submission;
use App\Notifications\SubmissionReceived;
use App\Services\SlackWebhookChannel;
use Illuminate\Mail\Markdown;
use Illuminate\Notifications\AnonymousNotifiable;

it('sends over the mail channel', function (): void {
    $notification = new SubmissionReceived(Submission::factory()->make());

    expect($notification->via(new AnonymousNotifiable))->toBe(['mail']);
});

it('sends over the routed channels of an on-demand notifiable', function (): void {
    $notification = new SubmissionReceived(Submission::factory()->make());
    $notifiable = (new AnonymousNotifiable)->route(SlackWebhookChannel::class, 'https://hooks.slack.com/services/T0/B0/xyz');

    expect($notification->via($notifiable))->toBe([SlackWebhookChannel::class]);
});

it('builds a slack payload with the form name, escaped fields, quoted message and inbox button', function (): void {
    $submission = Submission::factory()->create([
        'form_name' => 'Massage enquiry',
        'name' => 'Ada & <Co>',
        'email' => 'ada@example.com',
        'message' => "Line one\nLine two",
        'metadata' => ['a' => ['label' => 'Budget', 'value' => '1000']],
    ]);

    $payload = new SubmissionReceived($submission)->toSlackWebhook();

    expect($payload['text'])->toBe('New submission from Massage enquiry')
        ->and($payload['blocks'][0]['type'])->toBe('header')
        ->and($payload['blocks'][0]['text']['text'])->toBe('New submission from Massage enquiry')
        ->and($payload['blocks'][1]['text']['text'])->toContain('*Name:* Ada &amp; &lt;Co&gt;')
        ->and($payload['blocks'][1]['text']['text'])->toContain('*Email:* ada@example.com')
        ->and($payload['blocks'][1]['text']['text'])->toContain('*Budget:* 1000')
        ->and($payload['blocks'][2]['text']['text'])->toBe(">Line one\n>Line two")
        ->and($payload['blocks'][3]['type'])->toBe('actions')
        ->and($payload['blocks'][3]['elements'][0]['url'])->toBe(route('admin.inbox-show', $submission));
});

it('omits the fields and message blocks from the slack payload when empty', function (): void {
    $submission = Submission::factory()->create([
        'form_name' => null,
        'name' => null,
        'email' => null,
        'phone' => null,
        'subject' => null,
        'message' => null,
        'metadata' => null,
    ]);

    $payload = new SubmissionReceived($submission)->toSlackWebhook();

    expect($payload['text'])->toBe('New contact form submission')
        ->and($payload['blocks'])->toHaveCount(2)
        ->and($payload['blocks'][1]['type'])->toBe('actions');
});

it('builds a tabular mail with the named form, fields and message', function (): void {
    $submission = Submission::factory()->create([
        'form_name' => 'Massage enquiry',
        'name' => 'Ada Lovelace',
        'email' => 'ada@example.com',
        'phone' => '555-0100',
        'subject' => 'Saying hello',
        'message' => "Line one\nLine two",
        'metadata' => [
            'a' => ['label' => 'Budget', 'value' => '1000'],
            'b' => ['label' => 'Subscribe', 'value' => true],
            'c' => ['label' => 'Declined', 'value' => false],
            'd' => ['label' => 'Empty', 'value' => ''],
            'e' => 'not-an-array',
            'f' => ['label' => '', 'value' => 'No label'],
            'g' => ['label' => 'Missing', 'value' => null],
        ],
    ]);

    $mail = new SubmissionReceived($submission)->toMail(new AnonymousNotifiable);

    expect($mail->subject)->toBe('New submission from Massage enquiry');
    expect($mail->markdown)->toBe('mail.submission-received');
    expect($mail->replyTo)->not->toBeEmpty();
    expect($mail->viewData['formName'])->toBe('Massage enquiry');
    expect($mail->viewData['message'])->toBe("Line one\nLine two");

    $rows = collect($mail->viewData['rows'])->pluck('value', 'label');

    expect($rows['Name'])->toBe('Ada Lovelace');
    expect($rows['Email'])->toBe('ada@example.com');
    expect($rows['Phone'])->toBe('555-0100');
    expect($rows['Subject'])->toBe('Saying hello');
    expect($rows['Budget'])->toBe('1000');
    expect($rows['Subscribe'])->toBe('Yes');
    expect($rows['Declined'])->toBe('No');
    expect($rows->has('Empty'))->toBeFalse();
    expect($rows->has('Missing'))->toBeFalse();
});

it('falls back to a generic subject and omits reply-to without a form name or email', function (): void {
    $submission = Submission::factory()->create([
        'form_name' => null,
        'name' => 'No Email',
        'email' => null,
        'phone' => null,
        'subject' => null,
        'message' => 'Hi',
        'metadata' => null,
    ]);

    $mail = new SubmissionReceived($submission)->toMail(new AnonymousNotifiable);

    expect($mail->subject)->toBe('New contact form submission');
    expect($mail->replyTo)->toBeEmpty();
    expect($mail->viewData['rows'])->toBe([['label' => 'Name', 'value' => 'No Email']]);
});

it('renders the markdown mail to html without errors', function (): void {
    $submission = Submission::factory()->create([
        'form_name' => 'Massage enquiry',
        'name' => 'Ada Lovelace',
        'email' => 'ada@example.com',
        'message' => 'Hello there',
        'metadata' => [],
    ]);

    $mail = new SubmissionReceived($submission)->toMail(new AnonymousNotifiable);
    $html = (string) resolve(Markdown::class)->render($mail->markdown, $mail->viewData);

    expect($html)
        ->toContain('Massage enquiry')
        ->toContain('Ada Lovelace')
        ->toContain('Hello there');
});
