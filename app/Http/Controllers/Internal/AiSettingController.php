<?php

declare(strict_types=1);

namespace App\Http\Controllers\Internal;

use App\Actions\Ai\UpdateAiSettings;
use App\Http\Middleware\Internal\AuthenticateInternalApi;
use App\Http\Requests\Internal\UpdateAiSettingsRequest;
use App\Http\Resources\Internal\AiSettingResource;
use App\Models\CatalogSetting;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class AiSettingController
{
    public function __construct(private readonly UpdateAiSettings $update) {}

    public function show(): AiSettingResource
    {
        return new AiSettingResource(CatalogSetting::current());
    }

    public function update(UpdateAiSettingsRequest $request): JsonResponse
    {
        $setting = $this->update->execute($request->validated(), AuthenticateInternalApi::actor($request));

        return (new AiSettingResource($setting))
            ->response()
            ->setStatusCode(Response::HTTP_OK);
    }
}
