@extends('layouts.portal')
@section('title', __('Users'))
@section('content')
<h1 class="font-serif text-2xl font-bold">{{ __('Users & Roles') }}</h1>
<div class="mt-6 grid gap-6 lg:grid-cols-3">
    <div class="card lg:col-span-2 overflow-x-auto !p-0">
        <table class="min-w-full divide-y divide-navy-100">
            <thead><tr><th class="table-th">{{ __('Name') }}</th><th class="table-th">{{ __('Email') }}</th><th class="table-th">{{ __('Type') }}</th><th class="table-th">{{ __('Roles') }}</th><th class="table-th">{{ __('MFA') }}</th><th class="table-th">{{ __('Last login') }}</th></tr></thead>
            <tbody class="divide-y divide-navy-50">
                @foreach ($users as $user)
                    <tr>
                        <td class="table-td font-medium">{{ $user->name }}{{ $user->deactivated_at ? ' ('.__('deactivated').')' : '' }}</td>
                        <td class="table-td">{{ $user->email }}</td>
                        <td class="table-td">{{ $user->user_type }}</td>
                        <td class="table-td">{{ $user->roles->pluck('name')->implode(', ') }}</td>
                        <td class="table-td">{{ $user->mfaEnabled() ? '✓' : '—' }}</td>
                        <td class="table-td text-xs">{{ $user->last_login_at?->diffForHumans() ?? '—' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    <div class="card">
        <h2 class="font-serif text-lg font-bold">{{ __('Invite User') }}</h2>
        <form method="post" action="{{ route('portal.admin.users.store') }}" class="mt-3 space-y-3">
            @csrf
            <div><label class="form-label">{{ __('Name') }}</label><input class="form-input" name="name" required></div>
            <div><label class="form-label">{{ __('Email') }}</label><input class="form-input" type="email" name="email" required>
                @error('email')<p class="form-error">{{ $message }}</p>@enderror</div>
            <div><label class="form-label">{{ __('Title') }}</label><input class="form-input" name="title"></div>
            <div><label class="form-label">{{ __('Role') }}</label>
                <select class="form-input" name="role">
                    @foreach ($roles as $role)<option value="{{ $role->name }}">{{ $role->name }}</option>@endforeach
                </select></div>
            <button class="btn-primary w-full">{{ __('Create & Send Setup Link') }}</button>
        </form>
    </div>
</div>
<div class="mt-4">{{ $users->links() }}</div>
@endsection
