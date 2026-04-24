<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreTicketRequest;
use App\Http\Requests\UpdateTicketRequest;
use App\Http\Requests\UpdateTicketStatusRequest;
use App\Models\ActivityLog;
use App\Models\Ticket;
use App\Services\NextcloudService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class TicketController extends Controller
{
    public function index()
    {
        return Ticket::with(['creator', 'assignee'])->paginate(15);
    }

    public function store(StoreTicketRequest $request)
    {
        $data = $request->validated();
        $data['created_by'] = $request->user()->id;

        $attachments = [];
        Log::debug('Request files:', ['files' => $request->file('attachments')]);
        if ($request->hasFile('attachments')) {
            $nextcloud = new NextcloudService;
            foreach ($request->file('attachments') as $file) {
                Log::debug('Processing file', ['originalName' => $file->getClientOriginalName()]);
                $result = $nextcloud->upload($file);
                Log::debug('Upload result', ['result' => $result]);
                if ($result['success']) {
                    $attachments[] = $result['url'];
                } else {
                    Log::error('File upload failed', ['file' => $file->getClientOriginalName(), 'error' => $result['message']]);
                }
            }
        } else {
            Log::debug('No attachments found in request.');
        }
        Log::debug('Final attachments array', ['attachments' => $attachments]);
        $data['attachments'] = $attachments;

        $ticket = Ticket::create($data);
        $this->logTicketActivity(
            $request,
            $ticket,
            'ticket.created',
            "Ticket #{$ticket->id} was created",
            [
                'status' => $this->normalizeStatusValue($ticket->status),
                'priority' => $ticket->priority,
                'assigned_to' => $ticket->assigned_to,
            ]
        );
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
        $ticket->load(['assignee', 'category']);
        $beforeState = $this->ticketStateSnapshot($ticket);
        $before = $ticket->only([
            'subject',
            'description',
            'category_id',
            'priority',
            'assigned_to',
            'status',
        ]);
        $before['status'] = $this->normalizeStatusValue($before['status'] ?? null);
        $attachments = $data['existing_attachments'] ?? ($ticket->attachments ?? []);

        if ($request->hasFile('attachments')) {
            $nextcloud = new NextcloudService;
            foreach ($request->file('attachments') as $file) {
                $result = $nextcloud->upload($file);
                if ($result['success']) {
                    $attachments[] = $result['url'];
                } else {
                    // Optionally handle/log upload failure
                }
            }
        }

        if (array_key_exists('existing_attachments', $data) || $request->hasFile('attachments')) {
            $data['attachments'] = $attachments;
        }
        unset($data['existing_attachments']);

        $ticket->update($data);
        $ticket->load(['assignee', 'category']);
        $afterState = $this->ticketStateSnapshot($ticket);
        $after = $ticket->only(array_keys($before));
        $after['status'] = $this->normalizeStatusValue($after['status'] ?? null);
        $changedItems = $this->buildChangedItems($beforeState, $afterState, $data);
        $this->logTicketActivity(
            $request,
            $ticket,
            'ticket.updated',
            "Ticket #{$ticket->id} was updated",
            [
                'before' => $before,
                'after' => $after,
                'updated_fields' => array_keys($data),
                'changed_items' => $changedItems,
            ]
        );
        $ticket->load(['creator', 'assignee']);

        return response()->json($ticket);
    }

    public function destroy(Request $request, Ticket $ticket)
    {
        $ticketId = $ticket->id;
        $this->logTicketActivity(
            $request,
            $ticket,
            'ticket.deleted',
            "Ticket #{$ticketId} was deleted"
        );
        $ticket->delete();

        return response()->json(['message' => 'Ticket deleted successfully']);
    }

    public function updateStatus(UpdateTicketStatusRequest $request, Ticket $ticket)
    {
        $previousStatus = $this->normalizeStatusValue($ticket->status);
        $ticket->status = $request->validated('status');
        $ticket->save();
        $currentStatus = $this->normalizeStatusValue($ticket->status);
        $this->logTicketActivity(
            $request,
            $ticket,
            'ticket.status_updated',
            "Ticket #{$ticket->id} status was updated",
            [
                'from' => $previousStatus,
                'to' => $currentStatus,
                'changed_items' => [[
                    'field' => 'status',
                    'label' => 'Status',
                    'from' => $previousStatus,
                    'to' => $currentStatus,
                ]],
            ]
        );
        $ticket->load(['creator', 'assignee']);

        return response()->json($ticket);
    }

    public function getThreads(Ticket $ticket)
    {
        return response()->json($ticket->threads()->with('user')->latest()->get());
    }

    private function logTicketActivity(
        Request $request,
        Ticket $ticket,
        string $action,
        string $description,
        array $properties = []
    ): void {
        ActivityLog::create([
            'ticket_id' => $ticket->id,
            'user_id' => $request->user()?->id,
            'action' => $action,
            'subject_type' => 'ticket',
            'subject_id' => $ticket->id,
            'description' => $description,
            'properties' => $properties,
        ]);
    }

    private function ticketStateSnapshot(Ticket $ticket): array
    {
        $assigneeLabel = 'Unassigned';
        if (! is_null($ticket->assigned_to)) {
            $assigneeLabel = $ticket->assignee?->name ?? "User #{$ticket->assigned_to}";
        }

        return [
            'subject' => $ticket->subject,
            'category_id' => (int) $ticket->category_id,
            'category_label' => $ticket->category?->name ?? 'Uncategorized',
            'priority' => (string) $ticket->priority,
            'assigned_to' => $ticket->assigned_to,
            'assigned_to_label' => $assigneeLabel,
            'status' => $this->normalizeStatusValue($ticket->status),
        ];
    }

    private function buildChangedItems(array $beforeState, array $afterState, array $updatedData): array
    {
        $fieldLabels = [
            'subject' => 'Subject',
            'category_id' => 'Issue type',
            'priority' => 'Priority',
            'assigned_to' => 'Assignee',
            'status' => 'Status',
        ];

        $changes = [];

        foreach ($fieldLabels as $field => $label) {
            if (! array_key_exists($field, $updatedData)) {
                continue;
            }

            $beforeValue = $this->formatFieldValueForLog($field, $beforeState);
            $afterValue = $this->formatFieldValueForLog($field, $afterState);

            if ($beforeValue === $afterValue) {
                continue;
            }

            $changes[] = [
                'field' => $field,
                'label' => $label,
                'from' => $beforeValue,
                'to' => $afterValue,
            ];
        }

        return $changes;
    }

    private function formatFieldValueForLog(string $field, array $state): string
    {
        return match ($field) {
            'category_id' => (string) ($state['category_label'] ?? 'Uncategorized'),
            'assigned_to' => (string) ($state['assigned_to_label'] ?? 'Unassigned'),
            default => (string) ($state[$field] ?? ''),
        };
    }

    private function normalizeStatusValue(mixed $status): string
    {
        if ($status instanceof \BackedEnum) {
            return (string) $status->value;
        }

        if ($status instanceof \UnitEnum) {
            return $status->name;
        }

        return (string) ($status ?? '');
    }
}
