<?php

declare(strict_types=1);

namespace Pondol\IndexNow\Services;

use Illuminate\Support\Facades\Log;
use Pondol\IndexNow\Data\IndexingResult;
use Pondol\IndexNow\Enums\IndexingAction;
use Pondol\IndexNow\Events\IndexingFailed;
use Pondol\IndexNow\Events\IndexingSucceeded;
use Pondol\IndexNow\Jobs\IndexNow as IndexNowJob;
use Pondol\IndexNow\Support\UrlNormalizer;
use Psr\Log\LoggerInterface;

class IndexNowService
{
    protected bool $forceSync = false;

    protected bool $ignoreEnvironment = false;

    public function __construct(
        protected readonly IndexNowClient $indexNowClient,
        protected readonly GoogleIndexingClient $googleClient,
    ) {}

    /**
     * 큐를 거치지 않고 즉시 동기 실행합니다.
     */
    public function now(): self
    {
        $clone = clone $this;
        $clone->forceSync = true;

        return $clone;
    }

    /**
     * environments 제한을 무시하고 강제 실행합니다 (로컬/테스트 확인용).
     */
    public function force(): self
    {
        $clone = clone $this;
        $clone->ignoreEnvironment = true;

        return $clone;
    }

    /**
     * URL 생성 또는 업데이트 알림을 전송합니다.
     *
     * @param  string|array<int, string>  $urls
     * @return array<int, IndexingResult>
     */
    public function update(string|array $urls): array
    {
        return $this->submit($urls, IndexingAction::Updated);
    }

    /**
     * URL 삭제 알림을 전송합니다 (Google Indexing API에 URL_DELETED 전달).
     *
     * @param  string|array<int, string>  $urls
     * @return array<int, IndexingResult>
     */
    public function delete(string|array $urls): array
    {
        return $this->submit($urls, IndexingAction::Deleted);
    }

    /**
     * 설정(Queue 또는 Sync)에 따라 색인 요청을 처리합니다.
     *
     * @param  string|array<int, string>  $urls
     * @return array<int, IndexingResult>
     */
    public function submit(
        string|array $urls,
        IndexingAction|string|null $action = null,
    ): array {
        $normalized = UrlNormalizer::normalize($urls);
        if ($normalized === []) {
            return [];
        }

        $resolvedAction = IndexingAction::resolve($action ?? config('pondol-indexnow.google.action'));

        if (! $this->shouldRun($this->ignoreEnvironment)) {
            $this->logInfo('Skipped indexing due to disabled status or environment restriction.', [
                'env' => app()->environment(),
                'urls' => $normalized,
            ]);

            return [];
        }

        $useQueue = ! $this->forceSync && (bool) config('pondol-indexnow.queue.enabled', true);

        if ($useQueue) {
            $pending = IndexNowJob::dispatch($normalized, $resolvedAction, $this->ignoreEnvironment);

            $connection = config('pondol-indexnow.queue.connection');
            if (is_string($connection) && $connection !== '') {
                $pending->onConnection($connection);
            }

            $queueName = config('pondol-indexnow.queue.name');
            if (is_string($queueName) && $queueName !== '') {
                $pending->onQueue($queueName);
            }

            return [];
        }

        return $this->execute($normalized, $resolvedAction, $this->ignoreEnvironment);
    }

    /**
     * 실제 API 요청을 수행하고 로깅 및 이벤트를 발행합니다.
     *
     * @param  array<int, string>  $urls
     * @return array<int, IndexingResult>
     */
    public function execute(
        array $urls,
        IndexingAction $action = IndexingAction::Updated,
        bool $force = false,
    ): array {
        if ($urls === [] || ! $this->shouldRun($force)) {
            return [];
        }

        $this->logInfo('Indexing started.', [
            'action' => $action->value,
            'count' => count($urls),
            'urls' => $urls,
        ]);

        $results = [
            ...$this->indexNowClient->send($urls),
            ...$this->googleClient->send($urls, $action),
        ];

        foreach ($results as $result) {
            if ($result->successful) {
                $this->logInfo("[{$result->engine}] {$result->message}", [
                    'status' => $result->statusCode,
                    'urls' => $result->urls,
                ]);
                IndexingSucceeded::dispatch($result, $action);
            } else {
                $this->logError("[{$result->engine}] {$result->message}", [
                    'status' => $result->statusCode,
                    'urls' => $result->urls,
                ]);
                IndexingFailed::dispatch($result, $action);
            }
        }

        return $results;
    }

    public function shouldRun(bool $force = false): bool
    {
        if (! (bool) config('pondol-indexnow.enabled', true)) {
            return false;
        }

        if ($force) {
            return true;
        }

        $environments = (array) config('pondol-indexnow.environments', ['production']);

        if (in_array('*', $environments, true)) {
            return true;
        }

        return app()->environment($environments);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    protected function logInfo(string $message, array $context = []): void
    {
        if (! (bool) config('pondol-indexnow.log', true)) {
            return;
        }

        $this->logger()->info("[Pondol/IndexNow] {$message}", $context);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    protected function logError(string $message, array $context = []): void
    {
        if (! (bool) config('pondol-indexnow.log', true)) {
            return;
        }

        $this->logger()->error("[Pondol/IndexNow] {$message}", $context);
    }

    protected function logger(): LoggerInterface
    {
        $channel = config('pondol-indexnow.log_channel');

        return is_string($channel) && $channel !== ''
            ? Log::channel($channel)
            : Log::channel(config('logging.default'));
    }
}
