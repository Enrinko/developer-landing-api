<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Dto\ContactRequest;
use App\Service\ContactService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

final class ContactController extends AbstractController
{
    #[Route('/api/contact', name: 'api_contact_create', methods: ['POST'])]
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
