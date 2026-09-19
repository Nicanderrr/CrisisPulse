<x-layouts.app>
    <div class="cp-page-heading">
        <div><div class="cp-eyebrow">Administration</div><h2 class="cp-page-title">User Accounts</h2><p class="cp-page-copy">Manage who can access the CrisisPulse command desk.</p></div>
    </div>
    <div class="row g-4">
        <div class="col-12 col-xl-7">
            <section class="cp-panel">
                <div class="cp-panel-heading"><div><h3>Access roster</h3><p>System admins can manage staff access.</p></div><span class="badge text-bg-light border">{{ $users->count() }} accounts</span></div>
                <div class="table-responsive"><table class="table cp-table align-middle mb-0"><thead><tr><th>Name</th><th>Email</th><th>Role</th><th></th></tr></thead><tbody>
                    @foreach ($users as $user)
                        <tr><td class="fw-semibold">{{ $user->name }}</td><td>{{ $user->email }}</td><td><span class="badge {{ $user->isAdmin() ? 'text-bg-dark' : 'text-bg-light border' }}">{{ $user->roleLabel() }}</span></td><td class="text-end">@if (! auth()->user()->is($user))<form method="POST" action="{{ route('admin.users.destroy', $user) }}">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger" type="submit" onclick="return confirm('Remove this account?')"><i class="bi bi-trash"></i></button></form>@endif</td></tr>
                    @endforeach
                </tbody></table></div>
            </section>
        </div>
        <div class="col-12 col-xl-5">
            <section class="cp-panel"><div class="cp-panel-heading"><div><h3>Add account</h3><p>Create a staff or administrator login.</p></div></div>
                <form method="POST" action="{{ route('admin.users.store') }}" class="d-grid gap-3">@csrf
                    <div><label class="form-label" for="name">Full name</label><input class="form-control" id="name" name="name" value="{{ old('name') }}" required></div>
                    <div><label class="form-label" for="new-email">Email address</label><input class="form-control" id="new-email" name="email" type="email" value="{{ old('email') }}" required></div>
                    <div><label class="form-label" for="role">Role</label><select class="form-select" id="role" name="role" required><option value="staff">Staff</option><option value="system_admin">System Admin</option></select></div>
                    <div><label class="form-label" for="new-password">Temporary password</label><input class="form-control" id="new-password" name="password" type="password" minlength="8" required></div>
                    <div><label class="form-label" for="password-confirmation">Confirm password</label><input class="form-control" id="password-confirmation" name="password_confirmation" type="password" minlength="8" required></div>
                    <button class="btn btn-dark" type="submit"><i class="bi bi-person-plus me-1"></i> Create account</button>
                </form>
            </section>
        </div>
    </div>
</x-layouts.app>
