<?php

declare(strict_types=1);

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $keys = [
            'pexels_api_key',
            'google_analytics_credentials',
            'google_maps_api_key',
            'slack_webhook_url',
            'ai_api_key',
            'mail_password',
            'stripe_secret_key',
            'stripe_webhook_secret',
        ];

        foreach (DB::table('settings')->whereIn('key', $keys)->get(['id', 'value']) as $row) {
            $value = json_decode((string) $row->value, true);

            if (! is_string($value) || $value === '' || $this->isEncrypted($value)) {
                continue;
            }

            DB::table('settings')->where('id', $row->id)->update(['value' => json_encode(Crypt::encryptString($value))]);
        }

        cache()->forget('site-config');
    }

    private function isEncrypted(string $value): bool
    {
        try {
            Crypt::decryptString($value);

            return true;
        } catch (DecryptException) {
            return false;
        }
    }
};
