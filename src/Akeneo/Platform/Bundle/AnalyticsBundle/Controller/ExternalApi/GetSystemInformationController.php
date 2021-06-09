<?php

declare(strict_types=1);

namespace Akeneo\Platform\Bundle\AnalyticsBundle\Controller\ExternalApi;

use Akeneo\Platform\VersionProviderInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

class GetSystemInformationController
{
    private VersionProviderInterface $versionProvider;

    public function __construct(VersionProviderInterface $versionProvider)
    {
        $this->versionProvider = $versionProvider;
    }

    public function __invoke(Request $request): JsonResponse
    {
        $response = [
            'version' => strtolower($this->versionProvider->getVersion()),
            'edition' => strtolower($this->versionProvider->getEdition()),
        ];

        return new JsonResponse($response);
    }
}
