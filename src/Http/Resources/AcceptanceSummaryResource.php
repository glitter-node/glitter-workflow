<?php

namespace Modules\Glitter\Workflow\Http\Resources;

use App\Http\Resources\BaseApiResource;
use BackedEnum;
use Illuminate\Http\Request;

final class AcceptanceSummaryResource extends BaseApiResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'source_key' => $this->source_key,
            'status' => $this->enumValue($this->status),
            'attempts' => (int) $this->attempts,
            'available_at' => $this->formatDateTimeStringForUser($this->available_at),
            'claimed_at' => $this->formatDateTimeStringForUser($this->claimed_at),
            'processed_at' => $this->formatDateTimeStringForUser($this->processed_at),
            'error_code' => $this->last_error_code,
        ];
    }

    private function enumValue(mixed $value): mixed
    {
        return $value instanceof BackedEnum ? $value->value : $value;
    }
}
