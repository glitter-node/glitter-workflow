<?php

namespace Modules\Glitter\Workflow\Enums;

/**
 * 저장 가능한 안정 트리거 키.
 */
enum TriggerKey: string
{
    case UserRegistered = 'user.registered';
    case UserUpdated = 'user.updated';
    case BoardPostCreated = 'board.post.created';
    case BoardCommentCreated = 'board.comment.created';
}
