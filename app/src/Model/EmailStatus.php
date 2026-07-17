<?php

declare(strict_types=1);

namespace App\Model;

enum EmailStatus: string
{
    case Sent = 'sent';
    case Failed = 'failed';
}
