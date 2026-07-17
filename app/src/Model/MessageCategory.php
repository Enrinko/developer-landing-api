<?php

declare(strict_types=1);

namespace App\Model;

enum MessageCategory: string
{
    case JobOffer = 'job_offer';
    case ProjectInquiry = 'project_inquiry';
    case Question = 'question';
    case Spam = 'spam';
    case Other = 'other';
}
