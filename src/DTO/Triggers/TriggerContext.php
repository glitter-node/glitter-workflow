<?php

namespace Modules\Glitter\Workflow\DTO\Triggers;

use Modules\Glitter\Workflow\Enums\TriggerKey;

/**
 * 영속화 가능한 버전된 트리거 컨텍스트.
 *
 * data에는 어댑터가 허용한 null 또는 스칼라 값만 들어갑니다.
 */
final readonly class TriggerContext
{
    /**
     * @param  array<string, scalar|null>  $data
     */
    public function __construct(
        public TriggerKey $key,
        public int $schemaVersion,
        public string $subjectType,
        public string $subjectId,
        public array $data,
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
            'schema' => $this->schemaIdentifier(),
            'subject' => [
                'type' => $this->subjectType,
                'id' => $this->subjectId,
            ],
            'data' => $this->data,
        ];
    }
}
