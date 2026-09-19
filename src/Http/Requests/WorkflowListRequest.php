<?php

namespace Modules\Glitter\Workflow\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Glitter\Workflow\Enums\TriggerKey;

final class WorkflowListRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if ($this->has('search') && is_string($this->input('search'))) {
            $this->merge(['search' => trim($this->input('search'))]);
        }
    }

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => ['nullable', 'string', Rule::in(['active', 'inactive'])],
            'trigger_key' => ['nullable', 'string', Rule::enum(TriggerKey::class)],
            'search' => ['nullable', 'string', 'max:255'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }

    /** @return array{status?: string, trigger_key?: string, search?: string} */
    public function filters(): array
    {
        $validated = $this->validated();

        return array_filter([
            'status' => $validated['status'] ?? null,
            'trigger_key' => $validated['trigger_key'] ?? null,
            'search' => $validated['search'] ?? null,
        ], static fn (mixed $value): bool => $value !== null && $value !== '');
    }
}
