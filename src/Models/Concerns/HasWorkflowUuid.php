<?php

namespace Modules\Glitter\Workflow\Models\Concerns;

use App\Contracts\UniqueIdServiceInterface;
use Illuminate\Database\Eloquent\Model;

/**
 * 코어 UUID 생성 계약으로 공개 UUID를 채웁니다.
 */
trait HasWorkflowUuid
{
    /** 모델 생성 이벤트를 등록합니다. */
    protected static function bootHasWorkflowUuid(): void
    {
        static::creating(function (Model $model): void {
            if (empty($model->getAttribute('uuid'))) {
                $model->setAttribute(
                    'uuid',
                    app(UniqueIdServiceInterface::class)->generateUuid(),
                );
            }
        });
    }

    /** 라우트 모델 바인딩 키를 반환합니다. */
    public function getRouteKeyName(): string
    {
        return 'uuid';
    }
}
