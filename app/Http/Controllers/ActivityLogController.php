<?php

namespace App\Http\Controllers;

use App\Http\Resources\ActivityLogResource;
use App\Models\ActivityLog;
use App\Models\Ticket;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ActivityLogController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = ActivityLog::query()->with('user')->latest();

        if ($request->filled('ticket_id')) {
            $query->where('ticket_id', (int) $request->query('ticket_id'));
        }

        if ($request->filled('user_id')) {
            $query->where('user_id', (int) $request->query('user_id'));
        }

        if ($request->filled('action')) {
            $query->where('action', (string) $request->query('action'));
        }

        $perPage = max(1, min(100, (int) $request->query('per_page', 30)));

        return ActivityLogResource::collection($query->paginate($perPage));
    }

    public function ticketIndex(Request $request, Ticket $ticket): AnonymousResourceCollection
    {
        $query = $ticket->activityLogs()->with('user')->latest();

        if ($request->filled('action')) {
            $query->where('action', (string) $request->query('action'));
        }

        $perPage = max(1, min(100, (int) $request->query('per_page', 30)));

        return ActivityLogResource::collection($query->paginate($perPage));
    }
}
