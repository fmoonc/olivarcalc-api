<?php

namespace App\Enums;

enum ParameterSetStatus: string
{
    case Draft = 'draft';
    case Published = 'published';
    case Archived = 'archived';

    public function isPublished(): bool
    {
        return $this === self::Published;
    }
}
