<?php

namespace App\Http\Requests;

use App\Services\Schema\SchemaSyncService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MappingRequest extends FormRequest
{
    public function rules(): array
    {
        $mapping = $this->route('mapping');
        $tabId = $mapping?->sheet_tab_id ?? $this->route('tab')->id;

        return [
            'target_table' => [
                'required',
                'string',
                'max:64',
                'regex:/^[a-z_][a-z0-9_]*$/i',
                Rule::notIn(SchemaSyncService::RESERVED_TABLES),
                Rule::unique('sheet_mappings')->where('sheet_tab_id', $tabId)->ignore($mapping),
            ],
            'write_mode' => ['required', Rule::in(['insert', 'upsert'])],
            'is_active' => ['boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'target_table.regex' => 'The table name may only contain letters, numbers and underscores.',
            'target_table.not_in' => 'This table belongs to the application and cannot be written to.',
        ];
    }
}
