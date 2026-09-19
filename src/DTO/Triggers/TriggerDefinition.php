<?php

namespace Modules\Glitter\Workflow\DTO\Triggers;

use Modules\Glitter\Workflow\Contracts\TriggerContextAdapterInterface;
use Modules\Glitter\Workflow\Enums\TriggerKey;

/**
 * 안정 키와 내부 G7 훅을 연결하는 트리거 정의.
 */
final readonly class TriggerDefinition
{
    /**
     * @param  array<int, TriggerFieldDefinition>  $fields
     * @param  class-string<TriggerContextAdapterInterface>  $adapter
     */
    public function __construct(
        public TriggerKey $key,
        public int $schemaVersion,
        public string $sourceHook,
        public string $labelTranslationKey,
        public string $descriptionTranslationKey,
        public array $fields,
        public string $adapter,
    ) {}

    /** 스키마 식별자를 반환합니다. */
    public function schemaIdentifier(): string
    {
        return $this->key->value.'@'.$this->schemaVersion;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'key' => $this->key->value,
            'schema_version' => $this->schemaVersion,
            'schema' => $this->schemaIdentifier(),
            'source_hook' => $this->sourceHook,
            'label_translation_key' => $this->labelTranslationKey,
            'description_translation_key' => $this->descriptionTranslationKey,
            'fields' => array_map(
                static fn (TriggerFieldDefinition $field): array => $field->toArray(),
                $this->fields,
            ),
        ];
    }
}
