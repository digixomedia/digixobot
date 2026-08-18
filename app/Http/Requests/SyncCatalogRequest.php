<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class SyncCatalogRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'categories' => ['required', 'array', 'min:1'],
            'categories.*.slug' => ['required', 'string', 'max:255', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/'],
            'categories.*.name' => ['required', 'string', 'max:255'],
            'categories.*.description' => ['nullable', 'string'],
            'categories.*.display_order' => ['required', 'integer', 'min:0'],
            'categories.*.is_active' => ['required', 'boolean'],
            'categories.*.products' => ['required', 'array', 'min:1'],
            'categories.*.products.*.slug' => ['required', 'string', 'max:255', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/'],
            'categories.*.products.*.name' => ['required', 'string', 'max:255'],
            'categories.*.products.*.description' => ['nullable', 'string'],
            'categories.*.products.*.display_order' => ['required', 'integer', 'min:0'],
            'categories.*.products.*.is_active' => ['required', 'boolean'],
            'categories.*.products.*.is_featured' => ['sometimes', 'boolean'],
            'categories.*.products.*.is_deal' => ['sometimes', 'boolean'],
            'categories.*.products.*.plans' => ['required', 'array', 'min:1'],
            'categories.*.products.*.plans.*.slug' => ['nullable', 'string', 'max:255', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/'],
            'categories.*.products.*.plans.*.name' => ['required', 'string', 'max:255'],
            'categories.*.products.*.plans.*.validity' => ['required', 'string', 'max:255'],
            'categories.*.products.*.plans.*.price_paise' => ['required', 'integer', 'min:1'],
            'categories.*.products.*.plans.*.compare_at_price_paise' => ['nullable', 'integer', 'min:1'],
            'categories.*.products.*.plans.*.stock' => ['nullable', 'integer', 'min:0'],
            'categories.*.products.*.plans.*.delivery_method' => ['nullable', 'string', 'max:255'],
            'categories.*.products.*.plans.*.delivery_estimate' => ['nullable', 'string', 'max:255'],
            'categories.*.products.*.plans.*.activation_method' => ['nullable', 'string', 'max:255'],
            'categories.*.products.*.plans.*.warranty' => ['nullable', 'string', 'max:255'],
            'categories.*.products.*.plans.*.conditions' => ['nullable', 'string'],
            'categories.*.products.*.plans.*.is_active' => ['required', 'boolean'],
            'categories.*.products.*.plans.*.display_order' => ['required', 'integer', 'min:0'],
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(response()->json([
            'message' => 'The catalog payload is invalid.',
            'created' => 0,
            'updated' => 0,
            'skipped' => 0,
            'failed' => count($validator->errors()->all()),
            'errors' => $validator->errors(),
        ], 422));
    }
}
