<?php

namespace Modules\Glitter\Workflow\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Glitter\Workflow\Enums\TriggerKey;
use Modules\Glitter\Workflow\Enums\WorkflowExecutionStatus;

final class ExecutionListRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => ['nullable', 'string', Rule::enum(WorkflowExecutionStatus::class)],
            'trigger_key' => ['nullable', 'string', Rule::enum(TriggerKey::class)],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }

    /** @return array{status?: string, trigger_key?: string} */
    public function filters(): array
    {
        $validated = $this->validated();

        return array_filter([
            'status' => $validated['status'] ?? null,
            'trigger_key' => $validated['trigger_key'] ?? null,
        ], static fn (mixed $value): bool => $value !== null && $value !== '');
    }
}
