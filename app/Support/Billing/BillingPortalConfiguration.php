<?php

declare(strict_types=1);

namespace App\Support\Billing;

use App\Models\CatalogSetting;
use Laravel\Cashier\Cashier;
use Stripe\Exception\InvalidRequestException;

/**
 * The one Stripe billing portal configuration the app uses: its features and
 * the way it captures a cancellation reason. Kept on the Catalog singleton so
 * a restart does not create another one.
 */
final class BillingPortalConfiguration
{
    /**
     * The configuration id, created on first use and reused afterwards. A
     * configuration deleted in Stripe (another key, a cleared test account) is
     * replaced instead of failing the portal.
     */
    public static function ensure(): string
    {
        $configurationId = CatalogSetting::current()->stripe_billing_portal_configuration_id;

        if (filled($configurationId)) {
            try {
                Cashier::stripe()->billingPortal->configurations->retrieve($configurationId);
                Cashier::stripe()->billingPortal->configurations->update($configurationId, [
                    'features' => self::features(),
                ]);

                return $configurationId;
            } catch (InvalidRequestException $e) {
                if (data_get($e->getJsonBody(), 'error.code') !== 'resource_missing') {
                    throw $e;
                }
            }
        }

        $configuration = Cashier::stripe()->billingPortal->configurations->create([
            'features' => self::features(),
        ]);

        $createdId = (string) $configuration->id;

        CatalogSetting::query()->updateOrCreate(
            ['id' => CatalogSetting::SINGLETON_ID],
            ['stripe_billing_portal_configuration_id' => $createdId],
        );

        return $createdId;
    }

    /**
     * @return array<string, mixed>
     */
    private static function features(): array
    {
        return [
            'invoice_history' => ['enabled' => true],
            'payment_method_update' => ['enabled' => true],
            'subscription_cancel' => [
                'enabled' => true,
                'mode' => 'at_period_end',
                'cancellation_reason' => [
                    'enabled' => true,
                    'options' => [
                        'too_expensive',
                        'missing_features',
                        'switched_service',
                        'unused',
                        'customer_service',
                        'low_quality',
                        'too_complex',
                        'other',
                    ],
                ],
            ],
            'subscription_update' => ['enabled' => false],
        ];
    }
}
