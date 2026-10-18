<?php

namespace Modules\Glitter\Workflow\Http\Resources;

use App\Http\Resources\BaseApiResource;
use BackedEnum;
use Illuminate\Http\Request;

final class ActionExecutionResource extends BaseApiResource
{
    public function toArray(Request $request): array
    {
        $snapshot = is_array($this->action_snapshot) ? $this->action_snapshot : [];
        $metadata = is_array($this->result_metadata) ? $this->result_metadata : [];

        $result = [
            'uuid' => $this->uuid,
            'action_index' => (int) $this->action_index,
            'action_type' => $this->enumValue($this->action_type),
            'status' => $this->enumValue($this->status),
            'attempts' => (int) $this->attempt_count,
            'scheduled_at' => $this->formatDateTimeStringForUser($this->scheduled_at),
            'started_at' => $this->formatDateTimeStringForUser($this->started_at),
            'finished_at' => $this->formatDateTimeStringForUser($this->finished_at),
            'error_code' => $this->error_code,
        ];

        if (($result['action_type'] ?? null) === 'delay') {
            $result['delay_seconds'] = (int) (($snapshot['config']['delay_seconds'] ?? 0));
        }
        if (($result['action_type'] ?? null) === 'webhook') {
            $result['status_code'] = $this->safeInteger($metadata['status_code'] ?? null, 100, 599);
            $result['response_bytes'] = $this->safeInteger($metadata['response_bytes'] ?? null, 0);
        }

        return $result;
    }

    private function enumValue(mixed $value): mixed
    {
        return $value instanceof BackedEnum ? $value->value : $value;
    }

    private function safeInteger(mixed $value, int $minimum, ?int $maximum = null): ?int
    {
        if (! is_int($value) || $value < $minimum || ($maximum !== null && $value > $maximum)) {
            return null;
        }

        return $value;
    }
}
