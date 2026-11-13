<?php

declare(strict_types=1);

namespace Pondol\IndexNow\Tests\Feature;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Pondol\IndexNow\Enums\IndexingAction;
use Pondol\IndexNow\Tests\TestCase;
use Pondol\IndexNow\Traits\Indexable;

class IndexableTraitTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('test_posts', function (Blueprint $table): void {
            $table->id();
            $table->string('title');
            $table->unsignedInteger('views')->default(0);
            $table->boolean('is_published')->default(true);
            $table->timestamps();
        });
    }

    public function test_it_automatically_indexes_on_create_update_and_delete(): void
    {
        Http::fake(['*' => Http::response('', 200)]);

        $post = TestPost::create([
            'title' => 'First Post',
            'is_published' => true,
        ]);

        Http::assertSentCount(2); // bing + naver on create

        // views 컬럼은 watch 대상이 아니므로 요청이 발생하지 않아야 함
        $post->update(['views' => 10]);
        Http::assertSentCount(2);

        // title 컬럼은 watch 대상이므로 요청 발생
        $post->update(['title' => 'Updated Title']);
        Http::assertSentCount(4);

        // 삭제 시에도 전송
        $post->delete();
        Http::assertSentCount(6);
    }

    public function test_it_skips_indexing_when_should_index_now_returns_false(): void
    {
        Http::fake();

        TestPost::create([
            'title' => 'Draft Post',
            'is_published' => false,
        ]);

        Http::assertNothingSent();
    }
}

class TestPost extends Model
{
    use Indexable;

    protected $table = 'test_posts';

    protected $guarded = [];

    protected $casts = [
        'is_published' => 'boolean',
    ];

    public function getIndexNowUrl(): string
    {
        return "https://example.com/posts/{$this->id}";
    }

    public function shouldIndexNow(IndexingAction $action): bool
    {
        return (bool) $this->is_published;
    }

    public function indexNowWatchColumns(): array
    {
        return ['title', 'is_published'];
    }
}
