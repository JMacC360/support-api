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
    public function index(Ticket $ticket)
    {
        return response()->json($ticket->threads()->with('user')->latest()->get());
    }

    public function store(StoreTicketThreadRequest $request, Ticket $ticket)
    {
        $validated = $request->validated();
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
            'message' => $validated['message'],
            'attachments' => $attachments,
            'internal_notes' => $validated['internal_notes'] ?? null,
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

    public function show(Ticket $ticket, TicketThread $thread)
    {
        if ($thread->ticket_id !== $ticket->id) {
            abort(404);
        }

        return response()->json($thread->load('user'));
    }

    public function update(UpdateTicketThreadRequest $request, Ticket $ticket, TicketThread $thread)
    {
        if ($thread->ticket_id !== $ticket->id) {
            abort(404);
        }
        $validated = $request->validated();
        $before = $thread->only(['message', 'internal_notes']);
        $data = $validated;
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
}
