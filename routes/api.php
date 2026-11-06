<?php

use App\Http\Controllers\ActivityLogController;
use App\Http\Controllers\AttachmentController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\PermissionController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\TicketController;
use App\Http\Controllers\TicketThreadController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\UserRoleController;
use Illuminate\Support\Facades\Route;

// Public routes for authentication
Route::post('/login', [AuthController::class, 'login']);
Route::post('/register', [AuthController::class, 'register']);

Route::middleware(['auth:sanctum'])->group(function () {

    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);

    // Users
    Route::get('/users', [UserController::class, 'index'])->middleware('permission:user.view');
    Route::post('/users', [UserController::class, 'store'])->middleware('permission:user.create');
    Route::get('/users/{user}', [UserController::class, 'show'])->middleware('permission:user.view');
    Route::put('/users/{user}', [UserController::class, 'update'])->middleware('permission:user.update');
    Route::patch('/users/{user}/status', [UserController::class, 'updateStatus'])->middleware('permission:user.update');
    Route::delete('/users/{user}', [UserController::class, 'destroy'])->middleware('permission:user.delete');

    // Roles
    Route::get('/roles', [RoleController::class, 'index'])->middleware('permission:role.view');
    Route::post('/roles', [RoleController::class, 'store'])->middleware('permission:role.create');
    Route::get('/roles/{id}', [RoleController::class, 'show'])->middleware('permission:role.view');
    Route::put('/roles/{id}', [RoleController::class, 'update'])->middleware('permission:role.update');
    Route::delete('/roles/{id}', [RoleController::class, 'destroy'])->middleware('permission:role.delete');
    Route::get('/roles/{id}/permissions', [RoleController::class, 'permissions'])->middleware('permission:role.view');

    // Permissions
    Route::get('/permissions', [PermissionController::class, 'index'])->middleware('permission:permission.view');
    Route::post('/permissions', [PermissionController::class, 'store'])->middleware('permission:permission.create');
    Route::get('/permissions/{id}', [PermissionController::class, 'show'])->middleware('permission:permission.view');
    Route::put('/permissions/{id}', [PermissionController::class, 'update'])->middleware('permission:permission.update');
    Route::delete('/permissions/{id}', [PermissionController::class, 'destroy'])->middleware('permission:permission.delete');

    // User role & permission assignment
    Route::get('/users/{user}/roles', [UserRoleController::class, 'roles'])->middleware('permission:user.view');
    Route::put('/users/{user}/roles', [UserRoleController::class, 'syncRoles'])->middleware('permission:user.assign-role');
    Route::get('/users/{user}/permissions', [UserRoleController::class, 'permissions'])->middleware('permission:user.view');
    Route::put('/users/{user}/permissions', [UserRoleController::class, 'syncPermissions'])->middleware('permission:user.assign-role');

    // Tickets
    Route::get('/tickets', [TicketController::class, 'index'])->middleware('permission:ticket.view');
    Route::post('/tickets', [TicketController::class, 'store'])->middleware('permission:ticket.create');
    Route::get('/tickets/{ticket}', [TicketController::class, 'show'])->middleware('permission:ticket.view');
    Route::put('/tickets/{ticket}', [TicketController::class, 'update'])->middleware('permission:ticket.update');
    Route::patch('/tickets/{ticket}/status', [TicketController::class, 'updateStatus'])->middleware('permission:ticket.status.update');
    Route::delete('/tickets/{ticket}', [TicketController::class, 'destroy'])->middleware('permission:ticket.delete');
    Route::get('/tickets/{ticket}/all-threads', [TicketController::class, 'getThreads'])->middleware('permission:ticket.view');
    Route::get('/tickets/{ticket}/activity-logs', [ActivityLogController::class, 'ticketIndex'])->middleware('permission:ticket.view');
    Route::get('/activity-logs', [ActivityLogController::class, 'index'])->middleware('permission:ticket.view');

    // Categories
    Route::get('/categories', [CategoryController::class, 'index'])->middleware('permission:category.view');
    Route::post('/categories', [CategoryController::class, 'store'])->middleware('permission:category.create');
    Route::get('/categories/{category}', [CategoryController::class, 'show'])->middleware('permission:category.view');
    Route::put('/categories/{category}', [CategoryController::class, 'update'])->middleware('permission:category.update');
    Route::delete('/categories/{category}', [CategoryController::class, 'destroy'])->middleware('permission:category.delete');

    // Ticket Threads (comments/replies)
    Route::get('/tickets/{ticket}/threads', [TicketThreadController::class, 'index'])->middleware('permission:ticket.view');
    Route::post('/tickets/{ticket}/threads', [TicketThreadController::class, 'store'])->middleware('permission:ticket.thread.create|ticket.thread.internal.create');
    Route::get('/tickets/{ticket}/threads/{thread}', [TicketThreadController::class, 'show'])->middleware('permission:ticket.view');
    Route::put('/tickets/{ticket}/threads/{thread}', [TicketThreadController::class, 'update'])->middleware('permission:ticket.thread.update');
    Route::delete('/tickets/{ticket}/threads/{thread}', [TicketThreadController::class, 'destroy'])->middleware('permission:ticket.thread.delete');

    // Attachment proxy (for protected Nextcloud files)
    Route::get('/attachments/proxy', [AttachmentController::class, 'proxy'])->middleware('permission:ticket.view');
});
