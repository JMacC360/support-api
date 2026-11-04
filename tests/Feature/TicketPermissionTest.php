<?php

use App\Models\Category;
use App\Models\Ticket;
use App\Models\TicketThread;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

function grantPermissions(User $user, array $permissions): void
{
    foreach ($permissions as $permission) {
        Permission::firstOrCreate([
            'name' => $permission,
            'guard_name' => 'api',
        ]);
    }

    $user->givePermissionTo($permissions);
}

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);

    $this->category = Category::create([
        'name' => 'General',
        'description' => null,
    ]);

    $this->ticket = Ticket::create([
        'subject' => 'Test ticket',
        'description' => 'Test description',
        'category_id' => $this->category->id,
        'priority' => 'Medium',
        'attachments' => [],
        'created_by' => $this->user->id,
        'assigned_to' => null,
        'status' => 'Open',
    ]);
});

it('forbids listing tickets without ticket.view', function () {
    $this->getJson('/api/tickets')->assertForbidden();
});

it('allows listing tickets with ticket.view', function () {
    grantPermissions($this->user, ['ticket.view']);

    $this->getJson('/api/tickets')
        ->assertSuccessful()
        ->assertJsonStructure([
            'data' => [[
                'id',
                'subject',
                'description',
                'category_id',
                'priority',
                'attachments',
                'created_by',
                'assigned_to',
                'status',
                'created_at',
                'updated_at',
                'creator',
                'assignee',
            ]],
        ]);
});

it('forbids creating tickets without ticket.create', function () {
    grantPermissions($this->user, ['ticket.view']);

    $this->postJson('/api/tickets', [
        'subject' => 'New ticket',
        'description' => 'Details',
        'category_id' => $this->category->id,
        'priority' => 'Low',
    ])->assertForbidden();
});

it('allows creating tickets with ticket.create', function () {
    grantPermissions($this->user, ['ticket.create']);

    $this->postJson('/api/tickets', [
        'subject' => 'New ticket',
        'description' => 'Details',
        'category_id' => $this->category->id,
        'priority' => 'Low',
    ])
        ->assertCreated()
        ->assertJsonPath('subject', 'New ticket');
});

it('forbids creating internal notes without ticket.thread.internal.create', function () {
    grantPermissions($this->user, ['ticket.thread.create']);

    $this->postJson("/api/tickets/{$this->ticket->id}/threads", [
        'message' => 'Internal note',
        'internal_notes' => 'Internal note',
    ])
        ->assertForbidden()
        ->assertJsonPath('message', 'You do not have permission to create internal notes.');
});

it('allows creating internal notes with only ticket.thread.internal.create', function () {
    grantPermissions($this->user, ['ticket.thread.internal.create']);

    $this->postJson("/api/tickets/{$this->ticket->id}/threads", [
        'message' => 'Internal note',
        'internal_notes' => 'Internal note',
    ])
        ->assertCreated()
        ->assertJsonPath('internal_notes', 'Internal note');
});

it('forbids replying in thread under an internal note without ticket.thread.internal.create', function () {
    grantPermissions($this->user, ['ticket.thread.create']);

    $internalParent = TicketThread::create([
        'ticket_id' => $this->ticket->id,
        'user_id' => $this->user->id,
        'parent_id' => null,
        'message' => 'Internal parent',
        'attachments' => [],
        'internal_notes' => 'Internal parent',
    ]);

    $this->postJson("/api/tickets/{$this->ticket->id}/threads", [
        'message' => 'Child reply',
        'parent_id' => $internalParent->id,
    ])
        ->assertForbidden()
        ->assertJsonPath('message', 'You do not have permission to create internal notes.');
});

it('allows replying in thread under an internal note with ticket.thread.internal.create', function () {
    grantPermissions($this->user, ['ticket.thread.internal.create']);

    $internalParent = TicketThread::create([
        'ticket_id' => $this->ticket->id,
        'user_id' => $this->user->id,
        'parent_id' => null,
        'message' => 'Internal parent',
        'attachments' => [],
        'internal_notes' => 'Internal parent',
    ]);

    $this->postJson("/api/tickets/{$this->ticket->id}/threads", [
        'message' => 'Child internal reply',
        'parent_id' => $internalParent->id,
    ])
        ->assertCreated()
        ->assertJsonPath('parent_id', $internalParent->id)
        ->assertJsonPath('internal_notes', 'Child internal reply');
});

it('allows replying in thread under a public reply with ticket.thread.create', function () {
    grantPermissions($this->user, ['ticket.thread.create']);

    $publicParent = TicketThread::create([
        'ticket_id' => $this->ticket->id,
        'user_id' => $this->user->id,
        'parent_id' => null,
        'message' => 'Public parent',
        'attachments' => [],
        'internal_notes' => null,
    ]);

    $this->postJson("/api/tickets/{$this->ticket->id}/threads", [
        'message' => 'Child public reply',
        'parent_id' => $publicParent->id,
    ])
        ->assertCreated()
        ->assertJsonPath('parent_id', $publicParent->id)
        ->assertJsonPath('internal_notes', null);
});

it('forbids creating public replies when only ticket.thread.internal.create is granted', function () {
    grantPermissions($this->user, ['ticket.thread.internal.create']);

    $this->postJson("/api/tickets/{$this->ticket->id}/threads", [
        'message' => 'Public reply',
    ])
        ->assertForbidden()
        ->assertJsonPath('message', 'You do not have permission to create ticket replies.');
});

it('hides internal notes from thread index without ticket.thread.internal.view', function () {
    grantPermissions($this->user, ['ticket.view']);

    TicketThread::create([
        'ticket_id' => $this->ticket->id,
        'user_id' => $this->user->id,
        'message' => 'Public reply',
        'attachments' => [],
        'internal_notes' => null,
    ]);

    TicketThread::create([
        'ticket_id' => $this->ticket->id,
        'user_id' => $this->user->id,
        'message' => 'Internal reply',
        'attachments' => [],
        'internal_notes' => 'Internal reply',
    ]);

    $this->getJson("/api/tickets/{$this->ticket->id}/threads")
        ->assertSuccessful()
        ->assertJsonCount(1);
});

it('shows internal notes on thread index with ticket.thread.internal.view', function () {
    grantPermissions($this->user, ['ticket.view', 'ticket.thread.internal.view']);

    TicketThread::create([
        'ticket_id' => $this->ticket->id,
        'user_id' => $this->user->id,
        'message' => 'Public reply',
        'attachments' => [],
        'internal_notes' => null,
    ]);

    TicketThread::create([
        'ticket_id' => $this->ticket->id,
        'user_id' => $this->user->id,
        'message' => 'Internal reply',
        'attachments' => [],
        'internal_notes' => 'Internal reply',
    ]);

    $this->getJson("/api/tickets/{$this->ticket->id}/threads")
        ->assertSuccessful()
        ->assertJsonCount(2);
});
