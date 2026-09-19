<?php

namespace Modules\Glitter\Workflow\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Glitter\Workflow\Support\WorkflowLimits;

final class UpdateWorkflowSettingsRequest extends FormRequest
{
    private const ALLOWED_FIELDS = [
        'max_actions_per_workflow',
        'max_condition_depth',
    ];

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'max_actions_per_workflow' => ['required', 'integer'],
            'max_condition_depth' => ['required', 'integer'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            foreach (array_diff(array_keys($this->all()), self::ALLOWED_FIELDS) as $key) {
                $validator->errors()->add($key, 'This setting cannot be updated.');
            }

            foreach (self::ALLOWED_FIELDS as $key) {
                $value = $this->input($key);
                if (! is_int($value) || ! WorkflowLimits::accepts($key, $value)) {
                    $validator->errors()->add($key, 'The setting value is outside the supported range.');
                }
            }
        });
    }

    /** @return array{max_actions_per_workflow: int, max_condition_depth: int} */
    public function scalarSettings(): array
    {
        $validated = $this->validated();

        return [
            'max_actions_per_workflow' => $validated['max_actions_per_workflow'],
            'max_condition_depth' => $validated['max_condition_depth'],
        ];
    }
}
