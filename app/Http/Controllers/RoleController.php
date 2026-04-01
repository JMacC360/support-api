<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreRoleRequest;
use App\Http\Requests\UpdateRoleRequest;
use App\Http\Resources\PermissionResource;
use App\Http\Resources\RoleResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Spatie\Permission\Models\Role;

class RoleController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        $roles = Role::with('permissions')->get();

        return RoleResource::collection($roles);
    }

    public function store(StoreRoleRequest $request): RoleResource
    {
        $validated = $request->validated();

        $role = Role::create([
            'name' => $validated['name'],
            'guard_name' => $validated['guard_name'] ?? 'api',
        ]);

        if (! empty($validated['permissions'])) {
            $role->syncPermissions($validated['permissions']);
        }

        return new RoleResource($role->load('permissions'));
    }

    public function show(int $id): RoleResource
    {
        $role = Role::with('permissions')->findOrFail($id);

        return new RoleResource($role);
    }

    public function update(UpdateRoleRequest $request, int $id): RoleResource
    {
        $validated = $request->validated();

        $role = Role::findOrFail($id);

        $role->update([
            'name' => $validated['name'],
            'guard_name' => $validated['guard_name'] ?? $role->guard_name,
        ]);

        if (array_key_exists('permissions', $validated)) {
            $role->syncPermissions($validated['permissions'] ?? []);
        }

        return new RoleResource($role->load('permissions'));
    }

    public function destroy(int $id): JsonResponse
    {
        $role = Role::findOrFail($id);
        $role->delete();

        return response()->json(['message' => 'Role deleted successfully']);
    }

    public function permissions(int $id): AnonymousResourceCollection
    {
        $role = Role::with('permissions')->findOrFail($id);

        return PermissionResource::collection($role->permissions);
    }
}