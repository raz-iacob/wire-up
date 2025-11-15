<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\CreateUserAction;
use Illuminate\Console\Command;

use function Laravel\Prompts\password;
use function Laravel\Prompts\text;

final class CreateAdminUserCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'wireup:admin';

    /**
     * @var string
     */
    protected $description = 'Create a new admin user';

    public function handle(CreateUserAction $action): int
    {
        $this->info('Create a new admin user');
        $this->newLine();

        $name = text(
            label: 'Enter name',
            placeholder: 'Admin full name',
            required: true,
            validate: ['required', 'string', 'max:255'],
        );

        $email = text(
            label: 'Enter email',
            placeholder: 'Admin email address',
            required: true,
            validate: ['required', 'email', 'lowercase', 'max:255', 'unique:users,email'],
        );

        $password = $this->password();

        $user = $action->handle([
            'name' => $name,
            'email' => $email,
            'admin' => true,
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
