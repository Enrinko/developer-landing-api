<?php

declare(strict_types=1);

namespace App\Service\Ai;

use App\Model\ContactSubmission;

/**
 * Single source of truth for the analysis prompt, shared by every provider
 * (the prompts are also documented in the README, as required by the spec).
 */
final class AiPrompt
{
    public const SYSTEM = <<<'PROMPT'
        You analyze messages submitted through the contact form of a personal developer landing page.
        Respond with ONLY a valid JSON object (no markdown fences, no commentary) in exactly this shape:
        {"sentiment":"positive|neutral|negative","category":"job_offer|project_inquiry|question|spam|other","spam_score":0.0,"reply_draft":"..."}
        Rules:
        - "sentiment": overall tone of the message.
        - "category": job_offer = hiring/vacancy proposals, project_inquiry = requests to build or discuss a project,
          question = general questions, spam = advertising/scam/irrelevant, other = everything else.
        - "spam_score": number from 0.0 (definitely legitimate) to 1.0 (definitely spam).
        - "reply_draft": a short (2-3 sentences) polite reply the site owner could send, written in the language of the message.
        PROMPT;

    private function __construct()
    {
    }

    public static function user(ContactSubmission $submission): string
    {
        return \sprintf(
            "Name: %s\nEmail: %s\nPhone: %s\nMessage:\n%s",
            $submission->name,
            $submission->email,
            $submission->phone,
            $submission->comment,
        );
    }
}
