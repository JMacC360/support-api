<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreTicketThreadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'message' => ['required', 'string'],
            'internal_notes' => ['nullable', 'string'],
            'parent_id' => ['nullable', 'integer', 'exists:ticket_threads,id'],
            'attachments' => ['nullable', 'array'],
            'attachments.*' => ['file', 'mimes:jpeg,jpg,png,gif,bmp,svg,webp,avif,heic,heif,pdf', 'max:51200'],
        ];
    }
}
