<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Ticket extends Model
{
    use HasFactory;

    protected $fillable = [
        'subject',
        'description',
        'category_id',
        'priority',
        'attachments',
        'created_by',
        'assigned_to',
        'status',
    ];
    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    protected $casts = [
        'attachments' => 'array',
        'status' => \App\Enums\TicketStatus::class,
    ];

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function assignee()
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }
}
