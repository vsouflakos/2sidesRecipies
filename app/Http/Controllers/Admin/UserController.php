<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AccountStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AssignRoleRequest;
use App\Http\Requests\Admin\DeactivateUserRequest;
use App\Http\Requests\Admin\DeleteUserRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    /**
     * Display a paginated, searchable list of all users.
     */
    public function index(Request $request): Response
    {
        $search = $request->string('search')->toString();

        $adminCount = User::role('Admin')->count();

        $users = User::query()
            ->with('roles')
            ->when($search, fn ($q) => $q->where(fn ($w) => $w
                ->where('name', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%")
            ))
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (User $u) => [
                'id' => $u->id,
                'name' => $u->name,
                'email' => $u->email,
                'role' => $u->getRoleNames()->first(),
                'account_status' => $u->account_status->value,
                'created_at' => $u->created_at->toISOString(),
                'is_self' => $u->id === auth()->id(),
                'is_last_admin' => $u->hasRole('Admin') && $adminCount <= 1,
            ]);

        return Inertia::render('admin/users', [
            'users' => $users,
            'filters' => ['search' => $search],
        ]);
    }

    /**
     * Assign a new role to a user.
     */
    public function assignRole(AssignRoleRequest $request, User $user): JsonResponse
    {
        DB::transaction(function () use ($request, $user) {
            User::where('id', $user->id)->lockForUpdate()->first();
            $user->syncRoles([$request->validated('role')]);
        });

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('app.admin.toast_role_changed', [
                'name' => $user->name,
                'role' => $request->validated('role'),
            ]),
        ]);

        return response()->json(['message' => __('app.admin.toast_role_changed', [
            'name' => $user->name,
            'role' => $request->validated('role'),
        ])]);
    }

    /**
     * Toggle a user's account status between Active and Deactivated.
     */
    public function toggleStatus(DeactivateUserRequest $request, User $user): JsonResponse
    {
        $newStatus = $user->account_status === AccountStatus::Active
            ? AccountStatus::Deactivated
            : AccountStatus::Active;

        DB::transaction(function () use ($user, $newStatus) {
            User::where('id', $user->id)->lockForUpdate()->first();
            $user->account_status = $newStatus;
            $user->save();
        });

        $message = $newStatus === AccountStatus::Deactivated
            ? __('app.admin.toast_deactivated', ['name' => $user->name])
            : __('app.admin.toast_role_changed', ['name' => $user->name, 'role' => 'Active']);

        Inertia::flash('toast', ['type' => 'success', 'message' => $message]);

        return response()->json(['message' => $message]);
    }

    /**
     * Soft-delete a user account.
     */
    public function destroy(DeleteUserRequest $request, User $user): JsonResponse
    {
        DB::transaction(fn () => $user->delete());

        $message = __('app.admin.toast_deleted', ['name' => $user->name]);

        Inertia::flash('toast', ['type' => 'success', 'message' => $message]);

        return response()->json(['message' => $message]);
    }
}
