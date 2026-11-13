<?php

declare(strict_types=1);

namespace Pondol\IndexNow\Facades;

use Illuminate\Support\Facades\Facade;
use Pondol\IndexNow\Data\IndexingResult;
use Pondol\IndexNow\Enums\IndexingAction;
use Pondol\IndexNow\Services\IndexNowService;

/**
 * @method static IndexNowService now()
 * @method static IndexNowService force()
 * @method static array<int, IndexingResult> update(string|array<int, string> $urls)
 * @method static array<int, IndexingResult> delete(string|array<int, string> $urls)
 * @method static array<int, IndexingResult> submit(string|array<int, string> $urls, IndexingAction|string|null $action = null)
 * @method static array<int, IndexingResult> execute(array<int, string> $urls, IndexingAction $action = IndexingAction::Updated, bool $force = false)
 * @method static bool shouldRun(bool $force = false)
 *
 * @see IndexNowService
 */
class IndexNow extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return IndexNowService::class;
    }
}
