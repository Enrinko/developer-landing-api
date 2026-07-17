<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Service\HealthReporter;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class HealthController extends AbstractController
{
    #[Route('/api/health', name: 'api_health', methods: ['GET'])]
    #[OA\Get(
        summary: 'Проверка состояния сервиса',
        description: 'Хранилище, конфигурация почты и список настроенных AI-провайдеров. 503 — если файловое хранилище недоступно.',
        tags: ['Ops'],
    )]
    #[OA\Response(response: 200, description: 'Сервис работает')]
    #[OA\Response(response: 503, description: 'Хранилище недоступно')]
    public function __invoke(HealthReporter $reporter): JsonResponse
    {
        $report = $reporter->report();

        return $this->json(
            $report,
            'ok' === $report['status'] ? Response::HTTP_OK : Response::HTTP_SERVICE_UNAVAILABLE,
        );
    }
}
