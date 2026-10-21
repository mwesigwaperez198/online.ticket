@extends('layouts.app')

@section('title', 'Users — Pulse Tickets')

@section('content')
<section class="mx-auto max-w-7xl px-4 py-12 sm:px-6 lg:px-8">
    <h1 class="font-display text-3xl font-black">User management</h1>
    <p class="mt-1 text-sm text-slate-500">{{ $users->total() }} accounts across buyers, organizers and administrators.</p>

    <div class="panel mt-8 overflow-x-auto">
        <table class="data-table w-full min-w-[760px]">
            <thead><tr><th>User</th><th>Contact</th><th>Role</th><th>Status</th><th>Joined</th><th>Update</th></tr></thead>
            <tbody>
                @foreach($users as $user)
                    @php
                        $pill = $user->status === 'active' ? 'pill-paid' : 'pill-closed';
                    @endphp
                    <tr>
                        <td class="text-slate-200">{{ $user->name }}</td>
                        <td class="text-slate-400">{{ $user->email }}<br><span class="text-xs text-slate-500">{{ $user->phone }}</span></td>
                        <td><span class="pill {{ $user->role === 'admin' ? 'pill-pending' : ($user->role === 'organizer' ? 'pill-info' : 'pill-paid') }}">{{ $user->role }}</span></td>
                        <td><span class="pill {{ $pill }}">{{ $user->status }}</span></td>
                        <td class="text-xs text-slate-500">{{ $user->created_at->format('d M Y') }}</td>
                        <td>
                            <form method="POST" action="{{ route('admin.users.status', $user) }}" class="flex flex-wrap items-center gap-2">
                                @csrf
                                <select name="status" class="input-cyber !w-28 !py-1 text-xs">
                                    <option value="active" @selected($user->status === 'active')>active</option>
                                    <option value="suspended" @selected($user->status === 'suspended')>suspended</option>
                                </select>
                                <select name="role" class="input-cyber !w-28 !py-1 text-xs">
                                    <option value="buyer" @selected($user->role === 'buyer')>buyer</option>
                                    <option value="organizer" @selected($user->role === 'organizer')>organizer</option>
                                    <option value="admin" @selected($user->role === 'admin')>admin</option>
                                </select>
                                <button class="btn-ghost rounded-lg px-2.5 py-1 text-xs">Save</button>
                            </form>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <div class="mt-10">{{ $users->links() }}</div>
</section>
@endsection
