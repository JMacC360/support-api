<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\PermissionController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\UserRoleController;
use Illuminate\Support\Facades\Route;

// Public routes for authentication
Route::post('/login', [AuthController::class, 'login']);
Route::post('/register', [AuthController::class, 'register']);

Route::middleware(['auth:sanctum'])->group(function () {

    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);

    // Roles
    Route::get('/roles', [RoleController::class, 'index']);
    Route::post('/roles', [RoleController::class, 'store']);
    Route::get('/roles/{id}', [RoleController::class, 'show']);
    Route::put('/roles/{id}', [RoleController::class, 'update']);
    Route::delete('/roles/{id}', [RoleController::class, 'destroy']);
    Route::get('/roles/{id}/permissions', [RoleController::class, 'permissions']);

    // Permissions
    Route::get('/permissions', [PermissionController::class, 'index']);
    Route::post('/permissions', [PermissionController::class, 'store']);
    Route::get('/permissions/{id}', [PermissionController::class, 'show']);
    Route::put('/permissions/{id}', [PermissionController::class, 'update']);
    Route::delete('/permissions/{id}', [PermissionController::class, 'destroy']);

    // User role & permission assignment
    Route::get('/users/{user}/roles', [UserRoleController::class, 'roles']);
    Route::put('/users/{user}/roles', [UserRoleController::class, 'syncRoles']);
    Route::get('/users/{user}/permissions', [UserRoleController::class, 'permissions']);
    Route::put('/users/{user}/permissions', [UserRoleController::class, 'syncPermissions']);
});
