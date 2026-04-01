<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePermissionRequest;
use App\Http\Requests\UpdatePermissionRequest;
use App\Http\Resources\PermissionResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Spatie\Permission\Models\Permission;

class PermissionController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        $permissions = Permission::all();

        return PermissionResource::collection($permissions);
    }

    public function store(StorePermissionRequest $request): PermissionResource
    {
        $validated = $request->validated();

        $permission = Permission::create([
            'name' => $validated['name'],
            'guard_name' => $validated['guard_name'] ?? 'api',
        ]);

        return new PermissionResource($permission);
    }

    public function show(int $id): PermissionResource
    {
        $permission = Permission::findOrFail($id);

        return new PermissionResource($permission);
    }

    public function update(UpdatePermissionRequest $request, int $id): PermissionResource
    {
        $validated = $request->validated();

        $permission = Permission::findOrFail($id);

        $permission->update([
            'name' => $validated['name'],
            'guard_name' => $validated['guard_name'] ?? $permission->guard_name,
        ]);

        return new PermissionResource($permission);
    }

    public function destroy(int $id): JsonResponse
    {
        $permission = Permission::findOrFail($id);
        $permission->delete();

        return response()->json(['message' => 'Permission deleted successfully']);
    }
}
