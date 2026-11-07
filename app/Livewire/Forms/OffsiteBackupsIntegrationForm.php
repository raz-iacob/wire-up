<?php

declare(strict_types=1);

namespace App\Livewire\Forms;

use Livewire\Form;

final class OffsiteBackupsIntegrationForm extends Form
{
    public string $offsite_endpoint = '';

    public string $offsite_region = 'auto';

    public string $offsite_bucket = '';

    public string $offsite_folder = '';

    public string $offsite_access_key = '';

    public string $offsite_secret_key = '';

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'offsite_endpoint' => ['nullable', 'string', 'max:255', 'url', 'regex:#^https?://#'],
            'offsite_region' => ['nullable', 'string', 'max:50', 'regex:/^[a-z0-9-]+$/'],
            'offsite_bucket' => ['required', 'string', 'max:255', 'regex:/^[A-Za-z0-9._-]+$/'],
            'offsite_folder' => ['nullable', 'string', 'max:255', 'regex:#^[A-Za-z0-9._/-]*$#', 'not_regex:#\.\.#'],
            'offsite_access_key' => ['required', 'string', 'max:255'],
            'offsite_secret_key' => [filled(config('site.offsite_secret_key')) ? 'nullable' : 'required', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'offsite_endpoint.url' => __('Enter the endpoint as a full address, like https://example.r2.cloudflarestorage.com.'),
            'offsite_endpoint.regex' => __('Enter the endpoint as a full address, like https://example.r2.cloudflarestorage.com.'),
            'offsite_region.regex' => __('Enter the region exactly as your storage provider shows it, like auto or us-east-1.'),
            'offsite_bucket.regex' => __('Enter the bucket name exactly as your storage provider shows it.'),
            'offsite_folder.regex' => __('Use only letters, numbers, dots, dashes and slashes in the folder.'),
            'offsite_folder.not_regex' => __('Use only letters, numbers, dots, dashes and slashes in the folder.'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function validationAttributes(): array
    {
        return [
            'offsite_endpoint' => __('endpoint'),
            'offsite_region' => __('region'),
            'offsite_bucket' => __('bucket'),
            'offsite_folder' => __('folder'),
            'offsite_access_key' => __('access key ID'),
            'offsite_secret_key' => __('secret access key'),
        ];
    }
}
