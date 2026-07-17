<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Service\HealthReporter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class HealthController extends AbstractController
{
    #[Route('/api/health', name: 'api_health', methods: ['GET'])]
    public function __invoke(HealthReporter $reporter): JsonResponse
    {
        $report = $reporter->report();

        return $this->json(
            $report,
            'ok' === $report['status'] ? Response::HTTP_OK : Response::HTTP_SERVICE_UNAVAILABLE,
        );
    }
}
