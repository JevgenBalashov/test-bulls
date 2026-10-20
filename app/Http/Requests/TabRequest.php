<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TabRequest extends FormRequest
{
    public function rules(): array
    {
        $tab = $this->route('tab');
        $sourceId = $tab?->sheet_source_id ?? $this->route('source')->id;

        return [
            'tab_name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('sheet_tabs')->where('sheet_source_id', $sourceId)->ignore($tab),
            ],
            'header_row' => ['required', 'integer', 'min:1', 'max:1000'],
            'data_start_row' => ['required', 'integer', 'gt:header_row', 'max:1000'],
            'is_active' => ['boolean'],
        ];
    }
}
