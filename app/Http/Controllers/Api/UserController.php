<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Http\Resources\UserResource;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class UserController extends BaseController
{
    public function index(Request $request): JsonResource
    {
        $users = User::query()
            ->where('company_id', auth()->user()->company_id)
            ->paginate(15);

        return UserResource::collection($users);
    }

    public function store(Request $request): JsonResponse
    {
        // TODO: Replace with a dedicated FormRequest
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8',
            'username' => 'nullable|string|max:255|unique:users',
            'status' => 'nullable|string|in:active,inactive',
        ]);

        $user = DB::transaction(function () use ($validated) {
            $user = User::create([
                'company_id' => auth()->user()->company_id,
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => Hash::make($validated['password']),
                'username' => $validated['username'] ?? null,
                'status' => $validated['status'] ?? 'active',
            ]);
            return $user;
        });

        return (new UserResource($user))
            ->response()
            ->setStatusCode(201);
    }

    public function show(User $user): JsonResource
    {
        $this->authorize('view', $user);

        return new UserResource($user);
    }

    public function update(Request $request, User $user): JsonResource
    {
        $this->authorize('update', $user);

        // TODO: Replace with a dedicated FormRequest
        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'email' => 'sometimes|required|string|email|max:255|unique:users,email,' . $user->id,
            'password' => 'sometimes|nullable|string|min:8',
            'username' => 'sometimes|nullable|string|max:255|unique:users,username,' . $user->id,
            'status' => 'sometimes|required|string|in:active,inactive',
        ]);

        DB::transaction(function () use ($validated, $user) {
            if (isset($validated['password'])) {
                $validated['password'] = Hash::make($validated['password']);
            }
            $user->update($validated);
        });

        return new UserResource($user);
    }

    public function destroy(User $user): JsonResponse
    {
        $this->authorize('delete', $user);

        DB::transaction(function () use ($user) {
            $user->delete();
        });

        return response()->json(null, 204);
    }
}
