<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Record;
use App\Models\User;
use App\Services\SettingsService;
use App\Services\ShopService;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Stripe\Exception\ApiErrorException;

final readonly class StartSubscriptionCheckoutAction
{
    public function __construct(private ShopService $shop) {}

    public static function typeFor(Record $record): string
    {
        return 'record-'.$record->id;
    }

    /**
     * @throws ApiErrorException
     * @throws ValidationException
     */
    public function handle(User $user, Record $record): string
    {
        throw_if(
            ! $this->shop->isPurchasable($record) || ! $this->shop->isSubscription($record),
            ValidationException::withMessages(['subscribe' => __('This subscription is no longer available.')]),
        );

        throw_if(
            SettingsService::current()->mailConfigured() && ! $user->hasVerifiedEmail(),
            ValidationException::withMessages(['subscribe' => __('Confirm your email address from your account page before subscribing.')]),
        );

        throw_if(
            $user->subscribed(self::typeFor($record)),
            ValidationException::withMessages(['subscribe' => __('You already subscribe to this.')]),
        );

        $calculatesTax = $this->shop->calculatesTax();

        $checkout = $user->newSubscription(self::typeFor($record))->price([
            'price_data' => array_filter([
                'currency' => Str::lower(SettingsService::current()->currency()),
                'unit_amount' => $this->shop->toMinor((string) $this->shop->price($record)),
                'recurring' => ['interval' => $this->shop->billing($record) === ShopService::MONTHLY ? 'month' : 'year'],
                'product_data' => ['name' => $record->displayHeading(), 'metadata' => ['record_id' => (string) $record->id]],
                'tax_behavior' => $calculatesTax ? ($this->shop->pricesIncludeTax() ? 'inclusive' : 'exclusive') : null,
            ]),
        ])
            ->withMetadata(['record_id' => (string) $record->id])
            ->checkout(array_filter([
                'success_url' => route('account').'?subscription_session={CHECKOUT_SESSION_ID}',
                'cancel_url' => $record->getUrl(),
                'locale' => 'auto',
                'automatic_tax' => $calculatesTax ? ['enabled' => true] : null,
                'customer_update' => $calculatesTax ? ['address' => 'auto'] : null,
            ]));

        return (string) $checkout->asStripeCheckoutSession()->url;
    }
}
