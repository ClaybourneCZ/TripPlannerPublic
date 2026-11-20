<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateTripRequest extends FormRequest
{
    public function authorize(): bool
    {
        $this->user()->trips()->findOrFail($this->route('trip'));

        return true;
    }

    /** @return array<string, list<string>> */
    protected function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            $ids = collect($this->input('waypoints', []))->filter(fn ($point) => is_array($point) && isset($point['id']))
                ->pluck('id')->filter(fn ($id) => filter_var($id, FILTER_VALIDATE_INT) !== false)->all();
            if ($ids === []) {
                return;
            }

            $trip = $this->user()->trips()->findOrFail($this->route('trip'));
            if ($trip->waypoints()->whereIn('id', $ids)->count() !== count($ids)) {
                $validator->errors()->add('waypoints', 'One or more waypoints do not belong to this trip.');
            }
        });
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:1', 'max:255'],
            'notes' => ['present', 'nullable', 'string', 'max:10000'],
            'tags' => ['present', 'array', 'list', 'max:20'],
            'tags.*' => ['required', 'string', 'min:1', 'max:50', 'distinct:strict'],
            'waypoints' => ['sometimes', 'required', 'array', 'list', 'min:2', 'max:100'],
            'waypoints.*' => ['required', 'array:id,point_lat,point_lng,name'],
            'waypoints.*.id' => ['sometimes', 'integer', 'distinct', 'min:1'],
            'waypoints.*.point_lat' => ['required', 'numeric', 'between:-90,90'],
            'waypoints.*.point_lng' => ['required', 'numeric', 'between:-180,180'],
            'waypoints.*.name' => ['nullable', 'string', 'max:255'],
        ];
    }
}
