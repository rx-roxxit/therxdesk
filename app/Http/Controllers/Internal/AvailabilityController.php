<?php

declare(strict_types=1);

namespace App\Http\Controllers\Internal;

use App\Actions\Audit\RecordAuditEntry;
use App\Enums\Availability\ServiceSwitch;
use App\Enums\SocialAccount\Platform;
use App\Http\Middleware\Internal\AuthenticateInternalApi;
use App\Http\Requests\Internal\UpdateAvailabilityRequest;
use App\Support\Availability\Availability;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

/**
 * The System admin's Availability switches: every network but X, then the
 * services and the signup door, each with its installed, switched and
 * effective state. A flip is audited on every call.
 */
class AvailabilityController
{
    public function __construct(private readonly Availability $availability) {}

    public function index(): JsonResponse
    {
        return response()->json([
            'switches' => [
                ...array_map(fn (Platform $platform): array => $this->platformRow($platform), $this->switchablePlatforms()),
                ...array_map(fn (ServiceSwitch $service): array => $this->serviceRow($service), ServiceSwitch::cases()),
            ],
        ]);
    }

    public function update(UpdateAvailabilityRequest $request, string $key): JsonResponse
    {
        $enabled = $request->boolean('enabled');
        $platform = $this->platform($key);

        if ($platform !== null) {
            if ($enabled && ! $platform->isInstalled()) {
                $this->refuse('This network is not installed on the server.');
            }

            $this->record($request, $platform->availabilityKey(), $this->availability->isOn($platform->availabilityKey()), $enabled);

            return response()->json($this->platformRow($platform));
        }

        $service = ServiceSwitch::tryFrom($key);

        if ($service !== null) {
            if ($enabled && ! $service->isInstalled()) {
                $this->refuse("{$service->label()} is not installed on the server.");
            }

            $this->record($request, $service->value, $service->switchedOn(), $enabled);

            return response()->json($this->serviceRow($service));
        }

        abort(Response::HTTP_NOT_FOUND);
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

    private function platform(string $key): ?Platform
    {
        return collect($this->switchablePlatforms())
            ->first(fn (Platform $platform): bool => $platform->availabilityKey() === $key);
    }

    private function refuse(string $message): never
    {
        throw ValidationException::withMessages(['enabled' => $message]);
    }

    private function record(UpdateAvailabilityRequest $request, string $key, bool $before, bool $enabled): void
    {
        $switch = $this->availability->set($key, $enabled);

        RecordAuditEntry::execute(
            AuthenticateInternalApi::actor($request),
            $enabled ? 'availability.enable' : 'availability.disable',
            $switch,
            ['enabled' => $before],
            ['enabled' => $enabled],
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function platformRow(Platform $platform): array
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

    /**
     * @return array<string, mixed>
     */
    private function serviceRow(ServiceSwitch $service): array
    {
        return [
            'key' => $service->value,
            'kind' => $service->kind(),
            'value' => $service->item(),
            'label' => $service->label(),
            'installed' => $service->isInstalled(),
            'switched_on' => $service->switchedOn(),
            'effective' => $service->effective(),
        ];
    }
}
