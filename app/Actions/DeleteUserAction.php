<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\User;
use Illuminate\Validation\ValidationException;
use Laravel\Cashier\Subscription;
use Stripe\Exception\ApiErrorException;

final readonly class DeleteUserAction
{
    /**
     * @throws ValidationException
     */
    public function handle(User $user, string $errorField = 'password'): void
    {
        foreach (Subscription::query()->where('user_id', $user->id)->whereNull('ends_at')->get() as $subscription) {
            try {
                $subscription->cancelNow();
            } catch (ApiErrorException $exception) {
                report($exception);

                throw ValidationException::withMessages([
                    $errorField => __('Your subscription could not be cancelled, so your account was kept. Please try again later.'),
                ]);
            }
        }

        $user->delete();
    }
}
