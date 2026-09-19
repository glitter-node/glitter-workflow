<?php

namespace Modules\Glitter\Workflow\Services;

use DateTimeImmutable;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Modules\Glitter\Workflow\Contracts\TriggerDefinitionRegistryInterface;
use Modules\Glitter\Workflow\DTO\Triggers\TriggerContext;
use Modules\Glitter\Workflow\Enums\AcceptanceStatus;
use Modules\Glitter\Workflow\Enums\TriggerFieldType;
use Modules\Glitter\Workflow\Enums\TriggerKey;
use Modules\Glitter\Workflow\Jobs\ProcessWorkflowAcceptanceJob;
use Modules\Glitter\Workflow\Models\WorkflowAcceptance;
use Modules\Glitter\Workflow\Repositories\WorkflowRepositoryInterface;

final class WorkflowAcceptanceService
{
    public function __construct(
        private readonly TriggerDefinitionRegistryInterface $definitions,
        private readonly WorkflowRepositoryInterface $workflows,
    ) {}

    public function accept(TriggerContext $context): ?WorkflowAcceptance
    {
        $this->assertSafeContext($context);

        // Best-effort interest snapshot only. Processing repeats the authoritative
        // enabled-workflow lookup and never trusts this capture-time observation.
        if (! $this->workflows->hasEnabledForTrigger($context->key)) {
            return null;
        }

        return DB::transaction(function () use ($context): WorkflowAcceptance {
            $acceptance = WorkflowAcceptance::query()->create([
                'source_key' => $context->key->value,
                'schema_version' => $context->schemaVersion,
                'source_event_id' => null,
                'source_subject_type' => $context->subjectType,
                'source_subject_id' => $context->subjectId,
                'trigger_context' => $context->toArray(),
                'status' => AcceptanceStatus::Pending,
                'attempts' => 0,
            ]);

            ProcessWorkflowAcceptanceJob::dispatch($acceptance->uuid)->afterCommit();

            return $acceptance;
        });
    }

    private function assertSafeContext(TriggerContext $context): void
    {
        // A future registry extension must not silently become an acceptance API.
        $schema = $this->schemaFor($context->key);

        $definition = $this->definitions->find($context->key);
        if ($definition === null || $context->schemaVersion !== 1 || $context->schemaVersion !== $definition->schemaVersion
            || $context->subjectType !== $schema['subject_type'] || $context->subjectId === '') {
            throw new InvalidArgumentException('Unsupported trigger context.');
        }

        $definitionFields = [];
        foreach ($definition->fields as $field) {
            $definitionFields[$field->key] = $field->type;
        }

        $fields = $schema['fields'];
        if (count($context->data) !== count($fields)
            || array_diff(array_keys($context->data), array_keys($fields)) !== []
            || array_diff(array_keys($fields), array_keys($context->data)) !== []
            || array_keys($definitionFields) !== array_keys($fields)) {
            throw new InvalidArgumentException('Unsafe trigger context fields.');
        }

        foreach ($fields as $key => $contract) {
            if ($definitionFields[$key] !== $contract['type']) {
                throw new InvalidArgumentException('Unsupported trigger context.');
            }

            if (! $this->isValidValue($context->data[$key], $contract)) {
                throw new InvalidArgumentException('Unsafe trigger context value.');
            }
        }

        $identity = $context->data[$schema['identity_field']];
        if ($identity === null || (string) $identity !== $context->subjectId) {
            throw new InvalidArgumentException('Invalid trigger subject.');
        }
    }

    /**
     * @return array{
     *     subject_type: string,
     *     identity_field: string,
     *     fields: array<string, array{type: TriggerFieldType, nullable: bool, non_empty?: bool, minimum?: int}>
     * }
     */
    private function schemaFor(TriggerKey $key): array
    {
        $nullableString = ['type' => TriggerFieldType::String, 'nullable' => true];
        $nullableDateTime = ['type' => TriggerFieldType::DateTime, 'nullable' => true];
        $nullablePositiveInteger = ['type' => TriggerFieldType::Integer, 'nullable' => true, 'minimum' => 1];
        $requiredPositiveInteger = ['type' => TriggerFieldType::Integer, 'nullable' => false, 'minimum' => 1];
        $requiredNonNegativeInteger = ['type' => TriggerFieldType::Integer, 'nullable' => false, 'minimum' => 0];
        $requiredBoolean = ['type' => TriggerFieldType::Boolean, 'nullable' => false];

        return match ($key) {
            TriggerKey::UserRegistered => [
                'subject_type' => 'core.user',
                'identity_field' => 'user.uuid',
                'fields' => [
                    'user.uuid' => ['type' => TriggerFieldType::String, 'nullable' => false, 'non_empty' => true],
                    'user.status' => $nullableString,
                    'user.language' => $nullableString,
                    'user.country' => $nullableString,
                    'user.created_at' => $nullableDateTime,
                ],
            ],
            TriggerKey::UserUpdated => [
                'subject_type' => 'core.user',
                'identity_field' => 'user.uuid',
                'fields' => [
                    'user.uuid' => ['type' => TriggerFieldType::String, 'nullable' => false, 'non_empty' => true],
                    'user.status' => $nullableString,
                    'user.language' => $nullableString,
                    'user.country' => $nullableString,
                    'user.updated_at' => $nullableDateTime,
                ],
            ],
            TriggerKey::BoardPostCreated => [
                'subject_type' => 'sirsoft-board.post',
                'identity_field' => 'post.id',
                'fields' => [
                    'post.id' => $requiredPositiveInteger,
                    'post.board_id' => $requiredPositiveInteger,
                    'post.board_slug' => ['type' => TriggerFieldType::String, 'nullable' => false, 'non_empty' => true],
                    'post.actor_user_id' => $nullablePositiveInteger,
                    'post.category' => $nullableString,
                    'post.is_notice' => $requiredBoolean,
                    'post.is_secret' => $requiredBoolean,
                    'post.status' => $nullableString,
                    'post.parent_id' => $nullablePositiveInteger,
                    'post.depth' => $requiredNonNegativeInteger,
                    'post.created_at' => $nullableDateTime,
                ],
            ],
            TriggerKey::BoardCommentCreated => [
                'subject_type' => 'sirsoft-board.comment',
                'identity_field' => 'comment.id',
                'fields' => [
                    'comment.id' => $requiredPositiveInteger,
                    'comment.board_id' => $requiredPositiveInteger,
                    'comment.board_slug' => ['type' => TriggerFieldType::String, 'nullable' => false, 'non_empty' => true],
                    'comment.post_id' => $requiredPositiveInteger,
                    'comment.actor_user_id' => $nullablePositiveInteger,
                    'comment.parent_id' => $nullablePositiveInteger,
                    'comment.is_secret' => $requiredBoolean,
                    'comment.status' => $nullableString,
                    'comment.depth' => $requiredNonNegativeInteger,
                    'comment.created_at' => $nullableDateTime,
                ],
            ],
        };
    }

    /** @param array{type: TriggerFieldType, nullable: bool, non_empty?: bool, minimum?: int} $contract */
    private function isValidValue(mixed $value, array $contract): bool
    {
        if ($value === null) {
            return $contract['nullable'];
        }

        return match ($contract['type']) {
            TriggerFieldType::String => is_string($value)
                && (! ($contract['non_empty'] ?? false) || $value !== ''),
            TriggerFieldType::DateTime => is_string($value) && $this->isDateAtom($value),
            TriggerFieldType::Integer => is_int($value)
                && (! isset($contract['minimum']) || $value >= $contract['minimum']),
            TriggerFieldType::Boolean => is_bool($value),
        };
    }

    private function isDateAtom(string $value): bool
    {
        $date = DateTimeImmutable::createFromFormat(DATE_ATOM, $value);
        $errors = DateTimeImmutable::getLastErrors();

        return $date !== false
            && ($errors === false || ($errors['warning_count'] === 0 && $errors['error_count'] === 0))
            && $date->format(DATE_ATOM) === $value;
    }
}
