<?php

declare(strict_types=1);

namespace Pondol\IndexNow\Enums;

enum IndexingAction: string
{
    case Updated = 'URL_UPDATED';
    case Deleted = 'URL_DELETED';

    public static function resolve(self|string|null $action): self
    {
        if ($action instanceof self) {
            return $action;
        }

        if (is_string($action)) {
            return match (strtoupper(trim($action))) {
                'URL_DELETED', 'DELETED', 'DELETE' => self::Deleted,
                default => self::Updated,
            };
        }

        return self::Updated;
    }
}
