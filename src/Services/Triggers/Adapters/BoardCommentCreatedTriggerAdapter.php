<?php

namespace Modules\Glitter\Workflow\Services\Triggers\Adapters;

use InvalidArgumentException;
use Modules\Glitter\Workflow\Contracts\TriggerContextAdapterInterface;
use Modules\Glitter\Workflow\DTO\Triggers\TriggerContext;
use Modules\Glitter\Workflow\Enums\TriggerKey;
use Modules\Sirsoft\Board\Models\Comment;

/** sirsoft-board.comment.after_create 인자 어댑터. */
final class BoardCommentCreatedTriggerAdapter extends AbstractTriggerContextAdapter implements TriggerContextAdapterInterface
{
    public function map(mixed ...$arguments): TriggerContext
    {
        $comment = $arguments[0] ?? null;
        $slug = $arguments[1] ?? null;
        $postId = $comment instanceof Comment ? $this->integerValue($comment->post_id) : null;

        if (! $comment instanceof Comment || ! is_string($slug) || $slug === '' || $postId === null) {
            throw new InvalidArgumentException(__('glitter-workflow::messages.invalid_trigger_arguments'));
        }

        return new TriggerContext(
            key: TriggerKey::BoardCommentCreated,
            schemaVersion: 1,
            subjectType: 'sirsoft-board.comment',
            subjectId: (string) $comment->getKey(),
            data: [
                'comment.id' => (int) $comment->getKey(),
                'comment.board_id' => (int) $comment->board_id,
                'comment.board_slug' => $slug,
                'comment.post_id' => $postId,
                'comment.actor_user_id' => $this->integerValue($comment->user_id),
                'comment.parent_id' => $this->integerValue($comment->parent_id),
                'comment.is_secret' => (bool) $comment->is_secret,
                'comment.status' => $this->stringValue($comment->status),
                'comment.depth' => (int) $comment->depth,
                'comment.created_at' => $this->dateTimeValue($comment->created_at),
            ],
        );
    }
}
