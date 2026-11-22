<?php

declare(strict_types=1);

namespace App\Http\Controllers\Internal;

use App\Http\Middleware\Internal\AuthenticateInternalApi;
use App\Http\Requests\Internal\UpdateCatalogSettingsRequest;
use App\Http\Resources\Internal\CatalogSettingResource;
use App\Models\CatalogSetting;
use App\Services\Catalog\Catalog;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class CatalogSettingController
{
    public function __construct(private readonly Catalog $catalog) {}

    public function show(): CatalogSettingResource
    {
        return new CatalogSettingResource(CatalogSetting::current());
    }

    public function update(UpdateCatalogSettingsRequest $request): JsonResponse
    {
        $setting = $this->catalog->updateSettings($request->validated(), AuthenticateInternalApi::actor($request));

        // The first update creates the row; a PATCH still answers 200.
        return (new CatalogSettingResource($setting))
            ->response()
            ->setStatusCode(Response::HTTP_OK);
    }
}
