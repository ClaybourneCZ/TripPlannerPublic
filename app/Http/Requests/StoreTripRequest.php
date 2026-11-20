<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreTripRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:1', 'max:255'],
            'waypoints' => ['required', 'array', 'list', 'min:2', 'max:100'],
            'waypoints.*' => ['required', 'array:point_lat,point_lng,name'],
            'waypoints.*.name' => ['nullable', 'string', 'max:255'],
            'waypoints.*.point_lat' => ['required', 'numeric', 'between:-90,90'],
            'waypoints.*.point_lng' => ['required', 'numeric', 'between:-180,180'],
        ];
    }
}
