<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SourceRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'spreadsheet_id' => [
                'required',
                'string',
                'max:255',
                Rule::unique('sheet_sources')->ignore($this->route('source')),
            ],
            'is_active' => ['boolean'],
        ];
    }
}
