<?php

declare(strict_types=1);

namespace App\Http\Resources\App;

use App\Enums\Billing\Interval;
use App\Models\Offer;
use App\Support\Billing\OfferCopy;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * An Offer as the plan step shows it: the campaign name (never translated),
 * its discount badge, and its line per interval from the templates in
 * `rxdesk.php`.
 *
 * @mixin Offer
 */
class OfferResource extends JsonResource
{
    /**
     * @return array{slug: string, name: string, reach: string, show_banner: bool, discount_label: string|null, lines: array{monthly: string, yearly: string}, trial_days: int|null}
     */
    public function toArray(Request $request): array
    {
        return [
            'slug' => $this->slug,
            'name' => $this->name,
            'reach' => $this->reach->value,
            'show_banner' => $this->show_banner,
            'discount_label' => OfferCopy::discountLabel($this->resource),
            'lines' => [
                Interval::Monthly->value => OfferCopy::line($this->resource, Interval::Monthly),
                Interval::Yearly->value => OfferCopy::line($this->resource, Interval::Yearly),
            ],
            'trial_days' => $this->trial_days,
        ];
    }
}
