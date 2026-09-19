<?php

namespace Modules\Glitter\Workflow\Services\Triggers;

use Modules\Glitter\Workflow\Contracts\TriggerDefinitionRegistryInterface;
use Modules\Glitter\Workflow\DTO\Triggers\TriggerDefinition;
use Modules\Glitter\Workflow\DTO\Triggers\TriggerFieldDefinition;
use Modules\Glitter\Workflow\Enums\ConditionOperator;
use Modules\Glitter\Workflow\Enums\TriggerFieldType;
use Modules\Glitter\Workflow\Enums\TriggerKey;
use Modules\Glitter\Workflow\Services\Triggers\Adapters\BoardCommentCreatedTriggerAdapter;
use Modules\Glitter\Workflow\Services\Triggers\Adapters\BoardPostCreatedTriggerAdapter;
use Modules\Glitter\Workflow\Services\Triggers\Adapters\UserRegisteredTriggerAdapter;
use Modules\Glitter\Workflow\Services\Triggers\Adapters\UserUpdatedTriggerAdapter;

/**
 * v0.1 내장 트리거 정의 레지스트리.
 *
 * 외부 확장의 동적 등록은 아직 공개 계약이 아니며 이 레지스트리는 내장 정의만 제공합니다.
 */
final class BuiltInTriggerDefinitionRegistry implements TriggerDefinitionRegistryInterface
{
    /** @var array<string, TriggerDefinition>|null */
    private ?array $definitions = null;

    public function all(): array
    {
        return $this->definitions ??= $this->buildDefinitions();
    }

    public function find(TriggerKey|string $key): ?TriggerDefinition
    {
        $value = $key instanceof TriggerKey ? $key->value : $key;

        return $this->all()[$value] ?? null;
    }

    public function has(TriggerKey|string $key): bool
    {
        return $this->find($key) !== null;
    }

    /** @return array<string, TriggerDefinition> */
    private function buildDefinitions(): array
    {
        $definitions = [
            new TriggerDefinition(
                TriggerKey::UserRegistered,
                1,
                'core.auth.after_register',
                'glitter-workflow.triggers.user_registered.label',
                'glitter-workflow.triggers.user_registered.description',
                [
                    $this->stringField('user.uuid'),
                    $this->stringField('user.status'),
                    $this->stringField('user.language'),
                    $this->stringField('user.country'),
                    $this->dateTimeField('user.created_at'),
                ],
                UserRegisteredTriggerAdapter::class,
            ),
            new TriggerDefinition(
                TriggerKey::UserUpdated,
                1,
                'core.user.after_update',
                'glitter-workflow.triggers.user_updated.label',
                'glitter-workflow.triggers.user_updated.description',
                [
                    $this->stringField('user.uuid'),
                    $this->stringField('user.status'),
                    $this->stringField('user.language'),
                    $this->stringField('user.country'),
                    $this->dateTimeField('user.updated_at'),
                ],
                UserUpdatedTriggerAdapter::class,
            ),
            new TriggerDefinition(
                TriggerKey::BoardPostCreated,
                1,
                'sirsoft-board.post.after_create',
                'glitter-workflow.triggers.board_post_created.label',
                'glitter-workflow.triggers.board_post_created.description',
                [
                    $this->integerField('post.id'),
                    $this->integerField('post.board_id'),
                    $this->stringField('post.board_slug'),
                    $this->integerField('post.actor_user_id'),
                    $this->stringField('post.category'),
                    $this->booleanField('post.is_notice'),
                    $this->booleanField('post.is_secret'),
                    $this->stringField('post.status'),
                    $this->integerField('post.parent_id'),
                    $this->integerField('post.depth'),
                    $this->dateTimeField('post.created_at'),
                ],
                BoardPostCreatedTriggerAdapter::class,
            ),
            new TriggerDefinition(
                TriggerKey::BoardCommentCreated,
                1,
                'sirsoft-board.comment.after_create',
                'glitter-workflow.triggers.board_comment_created.label',
                'glitter-workflow.triggers.board_comment_created.description',
                [
                    $this->integerField('comment.id'),
                    $this->integerField('comment.board_id'),
                    $this->stringField('comment.board_slug'),
                    $this->integerField('comment.post_id'),
                    $this->integerField('comment.actor_user_id'),
                    $this->integerField('comment.parent_id'),
                    $this->booleanField('comment.is_secret'),
                    $this->stringField('comment.status'),
                    $this->integerField('comment.depth'),
                    $this->dateTimeField('comment.created_at'),
                ],
                BoardCommentCreatedTriggerAdapter::class,
            ),
        ];

        $indexed = [];
        foreach ($definitions as $definition) {
            $indexed[$definition->key->value] = $definition;
        }

        return $indexed;
    }

    private function stringField(string $key): TriggerFieldDefinition
    {
        return new TriggerFieldDefinition($key, TriggerFieldType::String, [
            ConditionOperator::Equals,
            ConditionOperator::NotEquals,
            ConditionOperator::In,
            ConditionOperator::NotIn,
            ConditionOperator::Exists,
            ConditionOperator::NotExists,
        ]);
    }

    private function integerField(string $key): TriggerFieldDefinition
    {
        return new TriggerFieldDefinition($key, TriggerFieldType::Integer, [
            ConditionOperator::Equals,
            ConditionOperator::NotEquals,
            ConditionOperator::In,
            ConditionOperator::NotIn,
            ConditionOperator::Exists,
            ConditionOperator::NotExists,
            ConditionOperator::GreaterThan,
            ConditionOperator::GreaterThanOrEqual,
            ConditionOperator::LessThan,
            ConditionOperator::LessThanOrEqual,
        ]);
    }

    private function booleanField(string $key): TriggerFieldDefinition
    {
        return new TriggerFieldDefinition($key, TriggerFieldType::Boolean, [
            ConditionOperator::Equals,
            ConditionOperator::NotEquals,
            ConditionOperator::Exists,
            ConditionOperator::NotExists,
        ]);
    }

    private function dateTimeField(string $key): TriggerFieldDefinition
    {
        return new TriggerFieldDefinition($key, TriggerFieldType::DateTime, [
            ConditionOperator::Equals,
            ConditionOperator::NotEquals,
            ConditionOperator::Exists,
            ConditionOperator::NotExists,
            ConditionOperator::Before,
            ConditionOperator::After,
        ]);
    }
}
