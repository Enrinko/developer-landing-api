<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Service\MetricsCalculator;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

final class MetricsController extends AbstractController
{
    #[Route('/api/metrics', name: 'api_metrics', methods: ['GET'])]
    #[OA\Get(
        summary: 'Статистика обращений',
        description: 'Агрегаты, вычисленные из файлового хранилища: количество, разбивка по тональности/категории/источнику анализа.',
        tags: ['Ops'],
    )]
    #[OA\Response(response: 200, description: 'Текущая статистика')]
    public function __invoke(MetricsCalculator $metrics): JsonResponse
    {
        return $this->json($metrics->collect());
    }
}
