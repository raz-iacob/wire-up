<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Submission;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class SubmissionReceived extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Submission $submission)
    {
        $this->afterCommit = true;
    }

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $email = is_string($this->submission->email) ? $this->submission->email : '';

        $mail = new MailMessage()
            ->subject($this->subject())
            ->markdown('mail.submission-received', [
                'formName' => $this->formName(),
                'rows' => $this->rows(),
                'message' => is_string($this->submission->message) ? $this->submission->message : '',
                'submittedAt' => $this->submission->created_at,
                'replyTo' => $email !== '' ? $email : null,
            ]);

        if ($email !== '') {
            $mail->replyTo($email);
        }

        return $mail;
    }

    private function subject(): string
    {
        $formName = $this->formName();

        return $formName !== ''
            ? __('New submission from :form', ['form' => $formName])
            : __('New contact form submission');
    }

    private function formName(): string
    {
        return is_string($this->submission->form_name) ? $this->submission->form_name : '';
    }

    /**
     * @return array<int, array{label: string, value: string}>
     */
    private function rows(): array
    {
        $fields = [
            __('Name') => $this->submission->name,
            __('Email') => $this->submission->email,
            __('Phone') => $this->submission->phone,
            __('Subject') => $this->submission->subject,
        ];

        $rows = [];

        foreach ($fields as $label => $value) {
            if (is_string($value) && $value !== '') {
                $rows[] = ['label' => $label, 'value' => $this->clean($value)];
            }
        }

        foreach ($this->customRows() as $row) {
            $rows[] = $row;
        }

        return $rows;
    }

    /**
     * @return array<int, array{label: string, value: string}>
     */
    private function customRows(): array
    {
        $metadata = $this->submission->metadata;

        if (! is_array($metadata)) {
            return [];
        }

        $rows = [];

        foreach ($metadata as $field) {
            if (! is_array($field)) {
                continue;
            }

            $label = is_string($field['label'] ?? null) ? $field['label'] : '';
            $value = $field['value'] ?? null;

            if ($label === '') {
                continue;
            }
            if ($value === null) {
                continue;
            }
            if ($value === '') {
                continue;
            }

            $rows[] = [
                'label' => $label,
                'value' => is_bool($value) ? ($value ? __('Yes') : __('No')) : $this->clean((string) $value),
            ];
        }

        return $rows;
    }

    private function clean(string $value): string
    {
        return mb_trim((string) preg_replace('/\s+/', ' ', str_replace('|', '/', $value)));
    }
}
