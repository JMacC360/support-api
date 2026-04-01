<?php

namespace App\Http\Controllers;

use App\Models\Ticket;
use App\Models\TicketThread;
use App\Http\Requests\StoreTicketThreadRequest;
use App\Http\Requests\UpdateTicketThreadRequest;
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
            $nextcloud = new \App\Services\NextcloudService();
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
            'internal_notes' => $request->input('internal_notes'),
        ]);
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
        $data = $validated;
        if ($request->hasFile('attachments')) {
            $nextcloud = new \App\Services\NextcloudService();
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
        $thread->load('user');
        return response()->json($thread);
    }

    public function destroy(Ticket $ticket, TicketThread $thread)
    {
        if ($thread->ticket_id !== $ticket->id) {
            abort(404);
        }
        $thread->delete();
        return response()->json(['message' => 'Thread deleted successfully']);
    }
}