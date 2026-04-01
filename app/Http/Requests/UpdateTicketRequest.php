<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateTicketRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'subject' => ['sometimes', 'string', 'max:255'],
            'description' => ['sometimes', 'string'],
            'category_id' => ['sometimes', 'exists:categories,id'],
            'priority' => ['sometimes', 'string', 'max:50'],
            'attachments' => ['nullable', 'array'],
            'attachments.*' => ['file', 'mimes:jpeg,jpg,png,gif,bmp,svg,webp,pdf', 'max:51200'],
            'created_by' => ['sometimes', 'exists:users,id'],
            'assigned_to' => ['nullable', 'exists:users,id'],
        ];
    }
}
