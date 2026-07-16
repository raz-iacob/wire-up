<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| SMTP mail provider presets
|--------------------------------------------------------------------------
|
| Presets shown in Settings → Integrations → Email. Choosing one pre-fills
| the SMTP host, port, encryption and (where fixed) the username; the admin
| adds their password / API key and From details. "custom" leaves the
| connection fields open. Encryption is "tls" (STARTTLS, usually port 587)
| or "ssl" (implicit TLS, usually port 465).
|
*/

return [
    'gmail' => ['label' => 'Gmail', 'host' => 'smtp.gmail.com', 'port' => 587, 'encryption' => 'tls', 'username' => ''],
    'sendgrid' => ['label' => 'SendGrid', 'host' => 'smtp.sendgrid.net', 'port' => 587, 'encryption' => 'tls', 'username' => 'apikey'],
    'mailgun' => ['label' => 'Mailgun', 'host' => 'smtp.mailgun.org', 'port' => 587, 'encryption' => 'tls', 'username' => ''],
    'mandrill' => ['label' => 'Mandrill', 'host' => 'smtp.mandrillapp.com', 'port' => 587, 'encryption' => 'tls', 'username' => ''],
    'resend' => ['label' => 'Resend', 'host' => 'smtp.resend.com', 'port' => 465, 'encryption' => 'ssl', 'username' => 'resend'],
    'custom' => ['label' => 'Custom SMTP', 'host' => '', 'port' => 587, 'encryption' => 'tls', 'username' => ''],
];
