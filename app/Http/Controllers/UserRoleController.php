<?php

namespace App\Http\Controllers;

use App\Http\Requests\SyncPermissionsRequest;
use App\Http\Requests\SyncRolesRequest;
use App\Http\Resources\PermissionResource;
use App\Http\Resources\RoleResource;
use App\Models\User;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class UserRoleController extends Controller
{
    public function roles(User $user): AnonymousResourceCollection
    {
        return RoleResource::collection($user->load('roles.permissions')->roles);
    }

    public function syncRoles(SyncRolesRequest $request, User $user): AnonymousResourceCollection
    {
        $user->syncRoles($request->validated('roles'));

        return RoleResource::collection($user->load('roles.permissions')->roles);
    }

    public function permissions(User $user): AnonymousResourceCollection
    {
        $permissions = $user->getAllPermissions();

        return PermissionResource::collection($permissions);
    }

    public function syncPermissions(SyncPermissionsRequest $request, User $user): AnonymousResourceCollection
    {
        $user->syncPermissions($request->validated('permissions'));

        return PermissionResource::collection($user->getAllPermissions());
    }
}
