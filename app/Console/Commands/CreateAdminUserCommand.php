<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\CreateUserAction;
use App\Models\Role;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;

use function Laravel\Prompts\password;
use function Laravel\Prompts\text;

#[Description('Create a new admin user')]
#[Signature('wireup:admin {--name= : The name to give the admin} {--email= : The email address they sign in with} {--password= : The password to set}')]
final class CreateAdminUserCommand extends Command
{
    public function handle(CreateUserAction $action): int
    {
        $this->info('Create a new admin user');
        $this->newLine();

        $name = $this->filledOption('name');
        $email = $this->filledOption('email');
        $password = $this->filledOption('password');

        if (! $this->optionsAreValid($name, $email, $password)) {
            return self::FAILURE;
        }

        $name ??= text(
            label: 'Enter name',
            placeholder: 'Admin full name',
            required: true,
            validate: ['required', 'string', 'max:255'],
        );

        $email ??= text(
            label: 'Enter email',
            placeholder: 'Admin email address',
            required: true,
            validate: ['required', 'email:rfc,dns', 'lowercase', 'max:255', 'unique:users,email'],
        );

        $password ??= $this->password();

        $user = $action->handle([
            'name' => $name,
            'email' => $email,
            'role_id' => Role::query()->where('key', 'owner')->value('id'),
            'email_verified_at' => now(),
        ], $password);

        $this->newLine();
        $this->info('Admin user created successfully!');
        $this->table(
            ['Field', 'Value'],
            [
                ['Name', $user->name],
                ['Email', $user->email],
                ['Created', $user->created_at->format('Y-m-d H:i:s')],
            ]
        );

        return 0;
    }

    private function filledOption(string $key): ?string
    {
        $value = $this->option($key);

        return is_string($value) && $value !== '' ? $value : null;
    }

    private function optionsAreValid(?string $name, ?string $email, ?string $password): bool
    {
        $rules = [
            'name' => ['max:255'],
            'email' => ['email', 'lowercase', 'max:255', 'unique:users,email'],
            'password' => ['min:8'],
        ];

        $messages = [
            'name.max' => 'The name cannot be longer than 255 characters.',
            'email.email' => 'That email address is not valid.',
            'email.lowercase' => 'The email address must be lowercase.',
            'email.max' => 'The email address cannot be longer than 255 characters.',
            'email.unique' => 'An account with that email address already exists.',
            'password.min' => 'The password must be at least 8 characters.',
        ];

        $provided = array_filter([
            'name' => $name,
            'email' => $email,
            'password' => $password,
        ], fn (?string $value): bool => $value !== null);

        $validator = Validator::make($provided, array_intersect_key($rules, $provided), $messages);

        if ($validator->passes()) {
            return true;
        }

        $this->newLine();

        foreach ($validator->errors()->all() as $message) {
            $this->components->error($message);
        }

        return false;
    }

    private function password(): string
    {
        $password = password(
            label: 'Please enter your desired password',
            required: true,
            validate: ['required', 'string', 'min:8'],
        );

        $confirmPassword = password(
            label: 'Please confirm your password',
            required: true,
        );

        if ($password !== $confirmPassword) {
            $this->components->error('Passwords do not match. Please try again.');

            return $this->password();
        }

        return $password;
    }
}
