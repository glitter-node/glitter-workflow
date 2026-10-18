<?php

namespace Modules\Glitter\Workflow\Services\Triggers\Adapters;

use InvalidArgumentException;
use Modules\Glitter\Workflow\Contracts\TriggerContextAdapterInterface;
use Modules\Glitter\Workflow\DTO\Triggers\TriggerContext;
use Modules\Glitter\Workflow\Enums\TriggerKey;
use Modules\Sirsoft\Board\Models\Post;

/** sirsoft-board.post.after_create 인자 어댑터. */
final class BoardPostCreatedTriggerAdapter extends AbstractTriggerContextAdapter implements TriggerContextAdapterInterface
{
    public function map(mixed ...$arguments): TriggerContext
    {
        $post = $arguments[0] ?? null;
        $slug = $arguments[1] ?? null;

        if (! $post instanceof Post || ! is_string($slug) || $slug === '') {
            throw new InvalidArgumentException(__('glitter-workflow::messages.invalid_trigger_arguments'));
        }

        // 세 번째 훅 인자인 options는 호출 경로별 임시값을 포함할 수 있어 폐기합니다.
        return new TriggerContext(
            key: TriggerKey::BoardPostCreated,
            schemaVersion: 1,
            subjectType: 'sirsoft-board.post',
            subjectId: (string) $post->getKey(),
            data: [
                'post.id' => (int) $post->getKey(),
                'post.board_id' => (int) $post->board_id,
                'post.board_slug' => $slug,
                'post.actor_user_id' => $this->integerValue($post->user_id),
                'post.category' => $this->stringValue($post->category),
                'post.is_notice' => (bool) $post->is_notice,
                'post.is_secret' => (bool) $post->is_secret,
                'post.status' => $this->stringValue($post->status),
                'post.parent_id' => $this->integerValue($post->parent_id),
                'post.depth' => (int) $post->depth,
                'post.created_at' => $this->dateTimeValue($post->created_at),
            ],
        );
    }
}
