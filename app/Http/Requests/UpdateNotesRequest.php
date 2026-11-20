<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateNotesRequest extends FormRequest
{
    public function authorize(): bool
    {
        $this->user()->trips()->findOrFail($this->route('trip'));

        return true;
    }

    public function rules(): array
    {
        return [
            'notes' => ['present', 'nullable', 'string', 'max:10000'],
            'tags' => ['present', 'array', 'list', 'max:20'],
            'tags.*' => ['required', 'string', 'max:50', 'distinct:strict'],
            'waypoints' => ['present', 'array', 'list', 'max:100'],
            'waypoints.*' => ['required', 'array:id,name,description,tags'],
            'waypoints.*.id' => ['required', 'integer', 'distinct', Rule::exists('waypoints', 'id')->where('trip_id', $this->route('trip'))],
            'waypoints.*.name' => ['present', 'nullable', 'string', 'max:255'],
            'waypoints.*.description' => ['present', 'nullable', 'string', 'max:10000'],
            'waypoints.*.tags' => ['present', 'array', 'list', 'max:20'],
            'waypoints.*.tags.*' => ['required', 'string', 'max:50'],
        ];
    }
}
