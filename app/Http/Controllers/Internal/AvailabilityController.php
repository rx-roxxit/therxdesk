<?php

declare(strict_types=1);

namespace App\Http\Controllers\Internal;

use App\Actions\Audit\RecordAuditEntry;
use App\Enums\SocialAccount\Platform;
use App\Http\Middleware\Internal\AuthenticateInternalApi;
use App\Http\Requests\Internal\UpdateAvailabilityRequest;
use App\Support\Availability\Availability;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

/**
 * The System admin's Availability switches: every network but X, with its
 * installed and effective state, and the flip that is audited on every call.
 */
class AvailabilityController
{
    public function __construct(private readonly Availability $availability) {}

    public function index(): JsonResponse
    {
        return response()->json([
            'switches' => array_map(
                fn (Platform $platform): array => $this->row($platform),
                $this->switchablePlatforms(),
            ),
        ]);
    }

    public function update(UpdateAvailabilityRequest $request, string $key): JsonResponse
    {
        $platform = collect($this->switchablePlatforms())
            ->first(fn (Platform $platform): bool => $platform->availabilityKey() === $key);

        if ($platform === null) {
            abort(Response::HTTP_NOT_FOUND);
        }

        $enabled = $request->boolean('enabled');

        if ($enabled && ! $platform->isInstalled()) {
            throw ValidationException::withMessages([
                'enabled' => 'This network is not installed on the server.',
            ]);
        }

        $before = ['enabled' => $this->availability->isOn($platform->availabilityKey())];
        $switch = $this->availability->set($platform->availabilityKey(), $enabled);

        RecordAuditEntry::execute(
            AuthenticateInternalApi::actor($request),
            $enabled ? 'availability.enable' : 'availability.disable',
            $switch,
            $before,
            ['enabled' => $enabled],
        );

        return response()->json($this->row($platform));
    }

    /**
     * @return list<Platform>
     */
    private function switchablePlatforms(): array
    {
        return array_values(array_filter(
            Platform::cases(),
            fn (Platform $platform): bool => $platform->hasAvailabilitySwitch(),
        ));
    }

    /**
     * @return array<string, mixed>
     */
    private function row(Platform $platform): array
    {
        return [
            'key' => $platform->availabilityKey(),
            'kind' => 'platform',
            'value' => $platform->value,
            'label' => $platform->label(),
            'installed' => $platform->isInstalled(),
            'switched_on' => $this->availability->isOn($platform->availabilityKey()),
            'effective' => $platform->isAvailable(),
        ];
    }
}
