<?php

declare(strict_types=1);

namespace Pondol\IndexNow\Traits;

use Illuminate\Database\Eloquent\Model;
use Pondol\IndexNow\Enums\IndexingAction;
use Pondol\IndexNow\Facades\IndexNow;

trait Indexable
{
    public static function bootIndexable(): void
    {
        static::created(function (Model $model): void {
            /** @var self $model */
            $model->dispatchIndexNow(IndexingAction::Updated);
        });

        static::updated(function (Model $model): void {
            /** @var self $model */
            $watchColumns = $model->indexNowWatchColumns();

            if ($watchColumns !== [] && ! $model->wasChanged($watchColumns)) {
                return;
            }

            $model->dispatchIndexNow(IndexingAction::Updated);
        });

        static::deleted(function (Model $model): void {
            /** @var self $model */
            $model->dispatchIndexNow(IndexingAction::Deleted);
        });

        if (method_exists(static::class, 'restored')) {
            static::restored(function (Model $model): void {
                /** @var self $model */
                $model->dispatchIndexNow(IndexingAction::Updated);
            });
        }
    }

    /**
     * 색인 요청을 보낼 URL(또는 상대 경로 / URL 배열)을 반환합니다.
     * 모델에서 오버라이드하여 사용하세요. (예: return route('posts.show', $this);)
     *
     * @return string|array<int, string>|null
     */
    public function getIndexNowUrl(): string|array|null
    {
        $url = $this->getAttribute('url');

        return is_string($url) && $url !== '' ? $url : null;
    }

    /**
     * 현재 모델 상태에서 색인 요청을 보낼지 여부를 결정합니다.
     * (예: 발행 상태인 경우에만 true 반환)
     */
    public function shouldIndexNow(IndexingAction $action): bool
    {
        return true;
    }

    /**
     * updated 이벤트 시 변경 감지를 적용할 컬럼 목록입니다.
     * 빈 배열이면 모든 업데이트에 대해 색인을 요청합니다.
     *
     * @return array<int, string>
     */
    public function indexNowWatchColumns(): array
    {
        return [];
    }

    protected function dispatchIndexNow(IndexingAction $action): void
    {
        if (! $this->shouldIndexNow($action)) {
            return;
        }

        $urls = $this->getIndexNowUrl();
        if ($urls === null || $urls === '' || $urls === []) {
            return;
        }

        IndexNow::submit($urls, $action);
    }
}
