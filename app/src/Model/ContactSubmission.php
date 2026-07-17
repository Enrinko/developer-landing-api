<?php

declare(strict_types=1);

namespace App\Model;

use App\Dto\ContactRequest;
use Symfony\Component\Uid\Uuid;

final readonly class ContactSubmission
{
    private function __construct(
        public string $id,
        public string $name,
        public string $email,
        public string $phone,
        public string $comment,
        public string $ip,
        public \DateTimeImmutable $createdAt,
    ) {
    }

    public static function fromRequest(ContactRequest $request, string $ip, ?\DateTimeImmutable $createdAt = null): self
    {
        return new self(
            id: Uuid::v7()->toRfc4122(),
            name: trim($request->name),
            email: mb_strtolower(trim($request->email)),
            phone: trim($request->phone),
            comment: trim($request->comment),
            ip: $ip,
            createdAt: $createdAt ?? new \DateTimeImmutable(),
        );
    }

    /**
     * @return array<string, string>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'comment' => $this->comment,
            'ip' => $this->ip,
            'createdAt' => $this->createdAt->format(\DateTimeInterface::ATOM),
        ];
    }
}
