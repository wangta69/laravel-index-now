<?php

declare(strict_types=1);

namespace Pondol\IndexNow\Jobs;

use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Pondol\IndexNow\Enums\IndexingAction;
use Pondol\IndexNow\Services\IndexNowService;
use Pondol\IndexNow\Support\UrlNormalizer;

class IndexNow implements ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * @param  string|array<int, string>  $path
     */
    public function __construct(
        protected string|array $path,
        protected IndexingAction|string|null $action = null,
        protected bool $force = false,
    ) {}

    public function tries(): int
    {
        return max(1, (int) config('pondol-indexnow.queue.tries', 3));
    }

    /**
     * @return array<int, int>
     */
    public function backoff(): array
    {
        $backoff = config('pondol-indexnow.queue.backoff', [60, 300, 900]);

        return is_array($backoff) && $backoff !== [] ? $backoff : [60, 300, 900];
    }

    public function handle(IndexNowService $service): void
    {
        if ($this->batch()?->cancelled()) {
            return;
        }

        $urls = UrlNormalizer::normalize($this->path);
        if ($urls === []) {
            return;
        }

        $resolvedAction = IndexingAction::resolve($this->action ?? config('pondol-indexnow.google.action'));
        $results = $service->execute($urls, $resolvedAction, $this->force);

        // 일시적 서버 오류(5xx) 또는 Rate Limit(429)만 재시도 대상으로 처리
        $hasRetryableFailure = false;
        foreach ($results as $result) {
            if (! $result->successful && ($result->statusCode === 429 || ($result->statusCode !== null && $result->statusCode >= 500))) {
                $hasRetryableFailure = true;
                break;
            }
        }

        if ($hasRetryableFailure && $this->attempts() < $this->tries()) {
            $backoff = $this->backoff();
            $delay = $backoff[$this->attempts() - 1] ?? end($backoff) ?: 60;
            $this->release((int) $delay);
        }
    }
}
