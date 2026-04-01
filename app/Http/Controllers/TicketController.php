<?php

namespace App\Http\Controllers;

use App\Models\Ticket;
use App\Http\Requests\StoreTicketRequest;
use App\Http\Requests\UpdateTicketRequest;
use App\Http\Requests\UpdateTicketStatusRequest;
use App\Services\NextcloudService;
use Illuminate\Http\Request;

class TicketController extends Controller{

    public function index()
    {
        return Ticket::with(['creator', 'assignee'])->paginate(15);
    }

    public function store(StoreTicketRequest $request)
    {
        $data = $request->validated();
        $data['created_by'] = $request->user()->id;

        $attachments = [];
        \Log::debug('Request files:', ['files' => $request->file('attachments')]);
        if ($request->hasFile('attachments')) {
            $nextcloud = new NextcloudService();
            foreach ($request->file('attachments') as $file) {
                \Log::debug('Processing file', ['originalName' => $file->getClientOriginalName()]);
                $result = $nextcloud->upload($file);
                \Log::debug('Upload result', ['result' => $result]);
                if ($result['success']) {
                    $attachments[] = $result['url'];
                } else {
                    \Log::error('File upload failed', ['file' => $file->getClientOriginalName(), 'error' => $result['message']]);
                }
            }
        } else {
            \Log::debug('No attachments found in request.');
        }
        \Log::debug('Final attachments array', ['attachments' => $attachments]);
        $data['attachments'] = $attachments;

        $ticket = Ticket::create($data);
        $ticket->load(['creator', 'assignee']);
        return response()->json($ticket, 201);
    }

    public function show(Ticket $ticket)
    {
        $ticket->load(['creator', 'assignee']);
        return response()->json($ticket);
    }

    public function update(UpdateTicketRequest $request, Ticket $ticket)
    {
        $data = $request->validated();

        if ($request->hasFile('attachments')) {
            $nextcloud = new NextcloudService();
            $attachments = $ticket->attachments ?? [];
            foreach ($request->file('attachments') as $file) {
                $result = $nextcloud->upload($file);
                if ($result['success']) {
                    $attachments[] = $result['url'];
                } else {
                    // Optionally handle/log upload failure
                }
            }
            $data['attachments'] = $attachments;
        }

        $ticket->update($data);
        $ticket->load(['creator', 'assignee']);
        return response()->json($ticket);
    }

    public function destroy(Ticket $ticket)
    {
        $ticket->delete();
        return response()->json(['message' => 'Ticket deleted successfully']);
    }

     public function updateStatus(UpdateTicketStatusRequest $request, Ticket $ticket)
    {
        $ticket->status = $request->validated('status');
        $ticket->save();
        $ticket->load(['creator', 'assignee']);
        return response()->json($ticket);
    }
}
