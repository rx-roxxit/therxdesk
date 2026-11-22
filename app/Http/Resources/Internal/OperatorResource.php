<?php

declare(strict_types=1);

namespace App\Http\Resources\Internal;

use App\Models\Operator;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Operator
 */
class OperatorResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'legal_name_th' => $this->legal_name_th,
            'legal_name_latin' => $this->legal_name_latin,
            'address_th' => $this->address_th,
            'address_latin' => $this->address_latin,
            'tax_id' => $this->tax_id,
            'vat_id' => $this->vat_id,
            'contact_email' => $this->contact_email,
            'phone' => $this->phone,
            'effective_from' => $this->effective_from,
            'ended_at' => $this->ended_at,
            'is_current' => $this->ended_at === null,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
