<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;
use App\Enums\TicketStatus;

class UpdateTicketStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Adjust authorization logic as needed
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => ['required', new Enum(TicketStatus::class)],
        ];
    }
}
