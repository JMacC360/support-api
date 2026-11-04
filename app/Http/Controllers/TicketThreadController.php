<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreTicketThreadRequest;
use App\Http\Requests\UpdateTicketThreadRequest;
use App\Models\ActivityLog;
use App\Models\Ticket;
use App\Models\TicketThread;
use App\Services\NextcloudService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class TicketThreadController extends Controller
{
    public function index(Request $request, Ticket $ticket)
    {
        $query = $ticket->threads()->with('user')->latest();

        if (! $this->canViewInternalNotes($request)) {
            $query->whereNull('internal_notes');
        }

        return response()->json($query->get());
    }

    public function store(StoreTicketThreadRequest $request, Ticket $ticket)
    {
        $validated = $request->validated();
        $parentId = $validated['parent_id'] ?? null;
        $parentThread = null;
        if ($parentId !== null) {
            $parentThread = TicketThread::query()
                ->where('ticket_id', $ticket->id)
                ->whereKey($parentId)
                ->first();
            if (! $parentThread) {
                return response()->json([
                    'message' => 'The selected parent thread is invalid for this ticket.',
                ], 422);
            }
        }

        $internalNotes = $validated['internal_notes'] ?? null;
        $hasInternalNotes = is_string($internalNotes) && trim($internalNotes) !== '';

        if ($parentThread?->internal_notes !== null) {
            // Prevent permission bypass: replying under an internal note must be an internal note too.
            if (! $this->canCreateInternalNotes($request)) {
                return response()->json(['message' => 'You do not have permission to create internal notes.'], 403);
            }
            $internalNotes = $hasInternalNotes ? $internalNotes : $validated['message'];
            $hasInternalNotes = true;
        }

        if ($hasInternalNotes && ! $this->canCreateInternalNotes($request)) {
            return response()->json(['message' => 'You do not have permission to create internal notes.'], 403);
        }
        if (! $hasInternalNotes && ! $request->user()?->can('ticket.thread.create')) {
            return response()->json(['message' => 'You do not have permission to create ticket replies.'], 403);
        }

        $attachments = [];
        if ($request->hasFile('attachments')) {
            $nextcloud = new NextcloudService;
            foreach ($request->file('attachments') as $file) {
                $result = $nextcloud->upload($file);
                if ($result['success']) {
                    $attachments[] = $result['url'];
                }
            }
        }
        $thread = $ticket->threads()->create([
            'user_id' => Auth::id() ?? $request->user()->id,
            'parent_id' => $parentThread?->id,
            'message' => $validated['message'],
            'attachments' => $attachments,
            'internal_notes' => $hasInternalNotes ? $internalNotes : null,
        ]);
        $this->logTicketThreadActivity(
            $request,
            $ticket,
            $thread,
            'ticket.thread_created',
            "Reply #{$thread->id} was added to ticket #{$ticket->id}",
            [
                'has_internal_notes' => ! empty($thread->internal_notes),
                'attachments_count' => count($attachments),
            ]
        );
        $thread->load('user');

        return response()->json($thread, 201);
    }

    public function show(Request $request, Ticket $ticket, TicketThread $thread)
    {
        if ($thread->ticket_id !== $ticket->id) {
            abort(404);
        }

        if ($thread->internal_notes !== null && ! $this->canViewInternalNotes($request)) {
            abort(404);
        }

        return response()->json($thread->load('user'));
    }

    public function update(UpdateTicketThreadRequest $request, Ticket $ticket, TicketThread $thread)
    {
        if ($thread->ticket_id !== $ticket->id) {
            abort(404);
        }

        if ($thread->internal_notes !== null && ! $this->canViewInternalNotes($request)) {
            abort(404);
        }

        $validated = $request->validated();
        $internalNotes = $validated['internal_notes'] ?? null;
        $hasInternalNotes = is_string($internalNotes) && trim($internalNotes) !== '';
        $touchesInternalNotes = array_key_exists('internal_notes', $validated);
        if (($hasInternalNotes || ($thread->internal_notes !== null && $touchesInternalNotes)) && ! $this->canCreateInternalNotes($request)) {
            return response()->json(['message' => 'You do not have permission to update internal notes.'], 403);
        }

        $before = $thread->only(['message', 'internal_notes']);
        $data = $validated;
        if ($touchesInternalNotes) {
            $data['internal_notes'] = $hasInternalNotes ? $internalNotes : null;
        }
        if ($request->hasFile('attachments')) {
            $nextcloud = new NextcloudService;
            $attachments = $thread->attachments ?? [];
            foreach ($request->file('attachments') as $file) {
                $result = $nextcloud->upload($file);
                if ($result['success']) {
                    $attachments[] = $result['url'];
                }
            }
            $data['attachments'] = $attachments;
        }
        $thread->update($data);
        $this->logTicketThreadActivity(
            $request,
            $ticket,
            $thread,
            'ticket.thread_updated',
            "Reply #{$thread->id} was updated on ticket #{$ticket->id}",
            [
                'before' => $before,
                'after' => $thread->only(['message', 'internal_notes']),
                'updated_fields' => array_keys($data),
            ]
        );
        $thread->load('user');

        return response()->json($thread);
    }

    public function destroy(Request $request, Ticket $ticket, TicketThread $thread)
    {
        if ($thread->ticket_id !== $ticket->id) {
            abort(404);
        }

        if ($thread->internal_notes !== null && ! $this->canViewInternalNotes($request)) {
            abort(404);
        }

        $threadId = $thread->id;
        $this->logTicketThreadActivity(
            $request,
            $ticket,
            $thread,
            'ticket.thread_deleted',
            "Reply #{$threadId} was deleted from ticket #{$ticket->id}"
        );
        $thread->delete();

        return response()->json(['message' => 'Thread deleted successfully']);
    }

    private function logTicketThreadActivity(
        Request $request,
        Ticket $ticket,
        TicketThread $thread,
        string $action,
        string $description,
        array $properties = []
    ): void {
        ActivityLog::create([
            'ticket_id' => $ticket->id,
            'user_id' => $request->user()?->id,
            'action' => $action,
            'subject_type' => 'ticket_thread',
            'subject_id' => $thread->id,
            'description' => $description,
            'properties' => $properties,
        ]);
    }

    private function canViewInternalNotes(Request $request): bool
    {
        // If a user can create internal notes, they should also be able to view them.
        return (bool) $request->user()?->can('ticket.thread.internal.view')
            || (bool) $request->user()?->can('ticket.thread.internal.create');
    }

    private function canCreateInternalNotes(Request $request): bool
    {
        return (bool) $request->user()?->can('ticket.thread.internal.create');
    }
}
