<?php

namespace App\Http\Requests;

use App\Models\SheetMappingColumn;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MappingColumnRequest extends FormRequest
{
    public function rules(): array
    {
        $column = $this->route('column');
        $mappingId = $column?->sheet_mapping_id ?? $this->route('mapping')->id;

        return [
            'source_column' => ['required', 'string', 'max:255'],
            'target_column' => [
                'required',
                'string',
                'max:64',
                'regex:/^[a-z_][a-z0-9_]*$/i',
                Rule::notIn(['id', 'imported_at', 'created_at', 'updated_at']),
                Rule::unique('sheet_mapping_columns', 'target_column')
                    ->where('sheet_mapping_id', $mappingId)
                    ->ignore($column),
            ],
            'data_type' => ['required', Rule::in(SheetMappingColumn::DATA_TYPES)],
            'is_nullable' => ['boolean'],
            'is_unique_key' => ['boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'target_column.regex' => 'The column name may only contain letters, numbers and underscores.',
            'target_column.not_in' => 'This column name is reserved by the importer.',
        ];
    }
}
