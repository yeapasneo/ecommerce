<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class StaffController extends Controller
{
    public function index()
    {
        $staff = User::where('is_admin', true)->orderBy('name')->get(['id', 'name', 'email', 'phone', 'role', 'is_active', 'created_at']);
        $lastSeen = ActivityLog::whereIn('user_id', $staff->pluck('id'))->selectRaw('user_id, max(created_at) as at')->groupBy('user_id')->pluck('at', 'user_id');

        return Inertia::render('Admin/Staff', [
            'staff' => $staff->map(fn ($u) => $u->only('id', 'name', 'email', 'phone', 'is_active', 'created_at') + [
                'role' => $u->role ?? 'owner',
                'role_label' => $u->roleLabel(),
                'last_active' => $lastSeen[$u->id] ?? null,
            ]),
            'roles' => collect(User::ROLES)->map(fn ($r, $k) => ['value' => $k, 'label' => $r['label'], 'permissions' => $r['permissions'] === ['*'] ? array_keys(User::PERMISSIONS) : $r['permissions']])->values(),
            'permissions' => User::PERMISSIONS,
        ]);
    }

    /** Add a staff member: a new account, or an existing customer account by email. */
    public function store(Request $request)
    {
        $existing = User::where('email', $request->input('email'))->first();
        $data = $request->validate([
            'name' => [$existing ? 'nullable' : 'required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'role' => ['required', Rule::in(array_keys(User::ROLES))],
            'password' => [$existing ? 'nullable' : 'required', Password::min(8)],
        ]);
        if ($existing?->is_admin) {
            throw ValidationException::withMessages(['email' => 'This person is already on the staff list.']);
        }
        if ($existing) {
            $existing->update(['is_admin' => true, 'role' => $data['role'], 'is_active' => true]);
        } else {
            User::create(['name' => $data['name'], 'email' => $data['email'], 'password' => Hash::make($data['password']), 'is_admin' => true, 'role' => $data['role']]);
        }

        return back()->with('success', "{$data['email']} can now sign in to the admin panel.");
    }

    public function update(Request $request, User $user)
    {
        abort_unless($user->is_admin, 404);
        $data = $request->validate([
            'role' => ['required', Rule::in(array_keys(User::ROLES))],
            'is_active' => ['required', 'boolean'],
        ]);
        $this->guard($request, $user, $data['role'], (bool) $data['is_active']);
        $user->update($data);

        return back()->with('success', "Saved {$user->name}'s access.");
    }

    /** Remove admin access (the account stays as a customer). */
    public function destroy(Request $request, User $user)
    {
        abort_unless($user->is_admin, 404);
        $this->guard($request, $user, null, false);
        $user->update(['is_admin' => false, 'role' => null]);

        return back()->with('success', "{$user->name} no longer has admin access.");
    }

    /** Nobody can lock themselves out, and the shop always keeps an active owner. */
    protected function guard(Request $request, User $user, ?string $role, bool $active): void
    {
        if ($user->is($request->user()) && ($role !== ($user->role ?? 'owner') || ! $active)) {
            throw ValidationException::withMessages(['role' => 'You cannot change your own access. Ask another owner.']);
        }
        $wasOwner = ($user->role ?? 'owner') === 'owner' && $user->is_active;
        $staysOwner = $role === 'owner' && $active;
        if ($wasOwner && ! $staysOwner) {
            $owners = User::where('is_admin', true)->where('is_active', true)->where(fn ($q) => $q->where('role', 'owner')->orWhereNull('role'))->count();
            if ($owners <= 1) {
                throw ValidationException::withMessages(['role' => 'The shop needs at least one active owner.']);
            }
        }
    }

    public function activity(Request $request)
    {
        $logs = ActivityLog::with('user:id,name')
            ->when($request->user_id, fn ($q, $id) => $q->where('user_id', $id))
            ->when($request->type, fn ($q, $t) => $q->where('subject_type', $t))
            ->when($request->search, fn ($q, $s) => $q->where('description', 'ilike', "%{$s}%"))
            ->latest('id')->paginate($this->perPage())->withQueryString();

        return Inertia::render('Admin/Activity', [
            'logs' => $logs,
            'filters' => $request->only('user_id', 'type', 'search'),
            'staff' => User::where('is_admin', true)->orWhereIn('id', ActivityLog::select('user_id')->distinct())->orderBy('name')->get(['id', 'name']),
            'types' => ActivityLog::whereNotNull('subject_type')->distinct()->orderBy('subject_type')->pluck('subject_type'),
        ]);
    }
}
