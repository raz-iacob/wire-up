<?php

declare(strict_types=1);

namespace App\Livewire\Forms;

use Livewire\Form;

final class CustomCodeForm extends Form
{
    public string $head_scripts = '';

    public string $body_scripts = '';

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'head_scripts' => ['nullable', 'string', 'max:50000'],
            'body_scripts' => ['nullable', 'string', 'max:50000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function validationAttributes(): array
    {
        return [
            'head_scripts' => __('custom head code'),
            'body_scripts' => __('custom body code'),
        ];
    }
}
