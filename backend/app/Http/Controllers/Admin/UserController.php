<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Platform\Models\Council;
use App\Modules\Platform\Models\Membership;
use App\Modules\Platform\Models\Role;
use App\Modules\Platform\Services\TenantManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    public function index(Request $request, TenantManager $tenants): Response
    {
        $currentCouncil = $tenants->council();

        $users = User::query()
            ->with(['memberships.council', 'memberships.role'])
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'status' => $user->status,
                'is_platform_admin' => (bool) $user->is_platform_admin,
                'mfa_enabled' => $user->hasMfaEnabled(),
                'last_login_at' => $user->last_login_at?->toIso8601String(),
                'created_at' => $user->created_at?->toIso8601String(),
                'memberships' => $user->memberships->map(fn (Membership $m) => [
                    'id' => $m->id,
                    'council_id' => $m->council_id,
                    'council_code' => $m->council?->code,
                    'council_name' => $m->council?->name,
                    'role_id' => $m->role_id,
                    'role_code' => $m->role?->code,
                    'role_name' => $m->role?->name,
                    'member_type' => $m->member_type,
                    'job_title' => $m->job_title,
                    'status' => $m->status,
                ]),
            ]);

        $councils = Council::query()
            ->where('status', 'active')
            ->orderBy('name')
            ->get(['id', 'code', 'name', 'currency_code']);

        $roles = Role::query()
            ->orderBy('name')
            ->get(['id', 'council_id', 'code', 'name', 'description']);

        return Inertia::render('Admin/Users', [
            'users' => $users,
            'councils' => $councils,
            'roles' => $roles,
            'currentCouncilId' => $currentCouncil?->id,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:32'],
            'password' => ['required', 'string', 'min:8'],
            'council_id' => ['required', 'string', 'exists:councils,id'],
            'role_id' => ['required', 'string', 'exists:roles,id'],
            'member_type' => ['required', 'string', 'in:staff,citizen'],
            'job_title' => ['nullable', 'string', 'max:120'],
            'is_platform_admin' => ['nullable', 'boolean'],
        ]);

        DB::transaction(function () use ($data) {
            $user = User::create([
                'name' => $data['name'],
                'email' => strtolower($data['email']),
                'phone' => $data['phone'] ?? null,
                'password' => Hash::make($data['password']),
                'is_platform_admin' => (bool) ($data['is_platform_admin'] ?? false),
                'status' => 'active',
                'email_verified_at' => now(),
            ]);

            Membership::create([
                'council_id' => $data['council_id'],
                'user_id' => $user->id,
                'role_id' => $data['role_id'],
                'member_type' => $data['member_type'],
                'job_title' => $data['job_title'] ?? ($data['member_type'] === 'citizen' ? 'Ratepayer' : 'Staff Member'),
                'status' => 'active',
                'started_at' => now(),
            ]);
        });

        return back()->with('success', "User '{$data['name']}' ({$data['email']}) created successfully.");
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:32'],
            'status' => ['required', 'string', 'in:active,suspended'],
            'is_platform_admin' => ['nullable', 'boolean'],
            'role_id' => ['nullable', 'string', 'exists:roles,id'],
            'council_id' => ['nullable', 'string', 'exists:councils,id'],
            'job_title' => ['nullable', 'string', 'max:120'],
        ]);

        $user->update([
            'name' => $data['name'],
            'phone' => $data['phone'] ?? null,
            'status' => $data['status'],
            'is_platform_admin' => (bool) ($data['is_platform_admin'] ?? false),
        ]);

        if (! empty($data['council_id']) && ! empty($data['role_id'])) {
            $membership = Membership::where('user_id', $user->id)
                ->where('council_id', $data['council_id'])
                ->first();

            if ($membership) {
                $membership->update([
                    'role_id' => $data['role_id'],
                    'job_title' => $data['job_title'] ?? $membership->job_title,
                    'status' => $data['status'],
                ]);
            } else {
                Membership::create([
                    'user_id' => $user->id,
                    'council_id' => $data['council_id'],
                    'role_id' => $data['role_id'],
                    'member_type' => 'staff',
                    'job_title' => $data['job_title'] ?? 'Staff Member',
                    'status' => 'active',
                    'started_at' => now(),
                ]);
            }
        }

        return back()->with('success', "User '{$user->name}' updated successfully.");
    }

    public function resetPassword(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate([
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $user->update([
            'password' => Hash::make($data['password']),
        ]);

        return back()->with('success', "Password for user '{$user->email}' has been reset successfully.");
    }
}
