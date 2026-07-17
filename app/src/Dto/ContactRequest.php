<?php

declare(strict_types=1);

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * Incoming payload of POST /api/contact. Validated at the edge;
 * everything past this DTO can trust the data.
 */
final readonly class ContactRequest
{
    public function __construct(
        #[Assert\NotBlank(message: 'Name is required.', normalizer: 'trim')]
        #[Assert\Length(min: 2, max: 100, normalizer: 'trim')]
        public string $name = '',

        #[Assert\NotBlank(message: 'Email is required.', normalizer: 'trim')]
        #[Assert\Email(message: 'Email address is not valid.')]
        #[Assert\Length(max: 180)]
        public string $email = '',

        #[Assert\NotBlank(message: 'Phone is required.', normalizer: 'trim')]
        #[Assert\Regex(
            pattern: '/^\+?[0-9\s\-()]{6,20}$/',
            message: 'Phone number may contain digits, spaces, dashes and parentheses (6-20 characters).',
        )]
        public string $phone = '',

        #[Assert\NotBlank(message: 'Comment is required.', normalizer: 'trim')]
        #[Assert\Length(min: 5, max: 5000, normalizer: 'trim')]
        public string $comment = '',
    ) {
    }
}
