<?php

declare(strict_types=1);

namespace Pondol\IndexNow\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Pondol\IndexNow\Data\IndexingResult;
use Pondol\IndexNow\Enums\IndexingAction;

class IndexingSucceeded
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly IndexingResult $result,
        public readonly IndexingAction $action,
    ) {}
}
