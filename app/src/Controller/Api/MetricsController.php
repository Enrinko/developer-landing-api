<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Service\MetricsCalculator;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

final class MetricsController extends AbstractController
{
    #[Route('/api/metrics', name: 'api_metrics', methods: ['GET'])]
    public function __invoke(MetricsCalculator $metrics): JsonResponse
    {
        return $this->json($metrics->collect());
    }
}
