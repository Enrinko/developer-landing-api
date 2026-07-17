<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Dto\ContactRequest;
use App\Service\ContactService;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

final class ContactController extends AbstractController
{
    #[Route('/api/contact', name: 'api_contact_create', methods: ['POST'])]
    #[OA\Post(
        summary: 'Отправить обращение через форму обратной связи',
        description: 'Полный цикл: валидация → AI-анализ (с graceful fallback) → сохранение → email владельцу и копия пользователю → ответ.',
        tags: ['Contact'],
    )]
    #[OA\Response(
        response: 201,
        description: 'Обращение принято; письма отправлены (emails=sent) или сбой SMTP залогирован (emails=failed).',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'id', type: 'string', format: 'uuid'),
                new OA\Property(property: 'status', type: 'string', example: 'accepted'),
                new OA\Property(property: 'emails', type: 'string', enum: ['sent', 'failed']),
                new OA\Property(
                    property: 'ai',
                    properties: [
                        new OA\Property(property: 'sentiment', type: 'string', enum: ['positive', 'neutral', 'negative']),
                        new OA\Property(property: 'category', type: 'string', enum: ['job_offer', 'project_inquiry', 'question', 'spam', 'other']),
                        new OA\Property(property: 'spamScore', type: 'number', format: 'float', minimum: 0, maximum: 1),
                        new OA\Property(property: 'replyDraft', type: 'string', nullable: true),
                        new OA\Property(property: 'provider', type: 'string', example: 'gemini'),
                        new OA\Property(property: 'source', type: 'string', enum: ['ai', 'heuristic']),
                    ],
                    type: 'object',
                ),
                new OA\Property(property: 'receivedAt', type: 'string', format: 'date-time'),
            ],
        ),
    )]
    #[OA\Response(
        response: 422,
        description: 'Ошибки валидации в едином envelope.',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'message', type: 'string', example: 'Validation failed'),
                new OA\Property(
                    property: 'errors',
                    type: 'object',
                    example: ['email' => ['Email is required.']],
                    additionalProperties: new OA\AdditionalProperties(type: 'array', items: new OA\Items(type: 'string')),
                ),
            ],
        ),
    )]
    #[OA\Response(
        response: 429,
        description: 'Превышен лимит запросов (заголовок Retry-After содержит секунды до следующей попытки).',
        content: new OA\JsonContent(
            properties: [new OA\Property(property: 'message', type: 'string', example: 'Too many requests. Please try again later.')],
        ),
    )]
    public function create(
        #[MapRequestPayload] ContactRequest $payload,
        Request $request,
        ContactService $contactService,
    ): JsonResponse {
        $result = $contactService->submit($payload, $request->getClientIp() ?? 'unknown');

        return $this->json([
            'id' => $result->submission->id,
            'status' => 'accepted',
            'emails' => $result->emailStatus->value,
            'ai' => $result->submission->analysis?->toArray(),
            'receivedAt' => $result->submission->createdAt->format(\DateTimeInterface::ATOM),
        ], Response::HTTP_CREATED);
    }
}
