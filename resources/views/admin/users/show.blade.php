@extends('layouts.admin')

@section('title', 'Personnel Dossier: ' . $user->name)

@section('content')
<div class="max-w-6xl mx-auto space-y-6">

    <!-- TOP HEADER -->
    <div class="flex items-center justify-between">
        <div class="flex items-center gap-3">
            <a href="{{ route('admin.users.index') }}" class="p-2 rounded-xl bg-white border border-gray-200 text-gray-600 hover:text-navy hover:border-navy transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                </svg>
            </a>
            <div>
                <h1 class="text-2xl font-extrabold text-navy">Flight Personnel Dossier</h1>
                <p class="text-xs text-gray-500">Security clearance, assigned roles, and activity records for {{ $user->name }}.</p>
            </div>
        </div>

        <div class="flex items-center gap-2">
            <a href="{{ route('admin.users.edit', $user) }}" class="px-4 py-2 rounded-xl bg-navy hover:bg-[#062663] text-white font-bold text-xs shadow-md shadow-navy/20 hover:scale-105 transition-all flex items-center gap-2">
                <svg class="w-4 h-4 text-accent" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                </svg>
                <span>Edit Account</span>
            </a>
        </div>
    </div>

    <!-- PROFILE CARD -->
    <div class="naoero-card p-6 sm:p-8">
        <div class="flex flex-col sm:flex-row items-start sm:items-center gap-6">
            <img src="{{ $user->avatar_url }}" alt="{{ $user->name }}" class="w-24 h-24 rounded-3xl object-cover border-4 border-navy shadow-lg shrink-0">
            
            <div class="space-y-2 flex-1">
                <div class="flex flex-wrap items-center gap-3">
                    <h2 class="text-2xl font-extrabold text-navy">{{ $user->name }}</h2>
                    @if($user->status === 'active')
                        <span class="px-3 py-1 rounded-full text-xs font-bold bg-emerald-50 text-emerald-700 border border-emerald-200">🟢 Active</span>
                    @elseif($user->status === 'inactive')
                        <span class="px-3 py-1 rounded-full text-xs font-bold bg-gray-100 text-gray-700 border border-gray-200">⚪ Inactive</span>
                    @else
                        <span class="px-3 py-1 rounded-full text-xs font-bold bg-rose-50 text-rose-700 border border-rose-200">🔴 Suspended</span>
                    @endif
                </div>

                <p class="text-sm font-semibold text-gray-700">
                    {{ $user->job_title ?? 'Flight Operations Staff' }} &bull; <span class="text-navy font-bold">{{ $user->department ?? 'General Operations' }}</span>
                </p>

                <div class="flex flex-wrap gap-4 text-xs text-gray-500 pt-1">
                    <span class="flex items-center gap-1.5"><span class="text-navy font-bold">📧</span> {{ $user->email }}</span>
                    @if($user->phone)
                        <span class="flex items-center gap-1.5"><span class="text-navy font-bold">📞</span> {{ $user->phone }}</span>
                    @endif
                    <span class="flex items-center gap-1.5"><span class="text-navy font-bold">🕒 Last login:</span> {{ $user->last_login_at ? $user->last_login_at->format('M d, Y H:i') : 'Never' }}</span>
                    <span class="flex items-center gap-1.5"><span class="text-navy font-bold">📅 Joined:</span> {{ $user->created_at->format('M d, Y') }}</span>
                </div>
            </div>
        </div>

        @if($user->bio)
            <div class="mt-6 pt-6 border-t border-gray-100 text-xs text-gray-600 leading-relaxed bg-gray-50/50 p-4 rounded-2xl">
                <strong class="text-navy block mb-1">Remarks & Bio:</strong>
                {{ $user->bio }}
            </div>
        @endif
    </div>

    <!-- ROLES & EFFECTIVE PERMISSIONS GRID -->
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">

        <!-- Assigned Roles -->
        <div class="lg:col-span-4 space-y-6">
            <div class="naoero-card p-6 space-y-4">
                <h3 class="text-base font-extrabold text-navy border-b border-gray-100 pb-3 flex items-center justify-between">
                    <span>🛡️ Assigned Roles</span>
                    <span class="text-xs bg-navy-50 text-navy font-bold px-2 py-0.5 rounded-full">{{ $user->roles->count() }}</span>
                </h3>

                <div class="space-y-2.5">
                    @forelse($user->roles as $role)
                        <div class="p-3.5 rounded-2xl bg-gray-50 border border-gray-100 flex items-center justify-between">
                            <div>
                                <p class="text-sm font-extrabold text-navy">{{ $role->name }}</p>
                                <p class="text-[11px] text-gray-500">{{ $role->permissions->count() }} permissions granted</p>
                            </div>
                            <span class="text-xs px-2 py-1 rounded-lg bg-navy text-white font-bold">Active</span>
                        </div>
                    @empty
                        <p class="text-xs text-gray-400 italic">No administrative roles assigned.</p>
                    @endforelse
                </div>
            </div>

            <!-- Direct Permissions -->
            <div class="naoero-card p-6 space-y-4">
                <h3 class="text-base font-extrabold text-navy border-b border-gray-100 pb-3 flex items-center justify-between">
                    <span>⚡ Direct Permissions</span>
                    <span class="text-xs bg-amber-50 text-accent-hover font-bold px-2 py-0.5 rounded-full">{{ $user->permissions->count() }}</span>
                </h3>

                <div class="flex flex-wrap gap-1.5">
                    @forelse($user->permissions as $perm)
                        <span class="px-2.5 py-1 rounded-lg text-xs font-mono font-bold bg-amber-50 text-amber-900 border border-amber-200">
                            {{ $perm->name }}
                        </span>
                    @empty
                        <p class="text-xs text-gray-400 italic">No direct permissions override.</p>
                    @endforelse
                </div>
            </div>

            <!-- Blocked for this account only -->
            <div class="naoero-card p-6 space-y-4">
                <h3 class="text-base font-extrabold text-navy border-b border-gray-100 pb-3 flex items-center justify-between">
                    <span>⛔ Blocked for this account</span>
                    <span class="text-xs bg-rose-50 text-rose-700 font-bold px-2 py-0.5 rounded-full">{{ $user->blockedPermissions->count() }}</span>
                </h3>

                <div class="flex flex-wrap gap-1.5">
                    @forelse($user->blockedPermissions as $perm)
                        <span class="px-2.5 py-1 rounded-lg text-xs font-mono font-bold bg-rose-50 text-rose-800 border border-rose-200">
                            {{ $perm->name }}
                        </span>
                    @empty
                        <p class="text-xs text-gray-400 italic">Nothing blocked: the roles apply as they are.</p>
                    @endforelse
                </div>
                @if($user->blockedPermissions->isNotEmpty() && $user->hasRole(\App\Services\Auth\AdminAccess::SUPER_ADMIN))
                    <p class="text-[11px] text-amber-800 bg-amber-50 border border-amber-200 rounded-lg p-2">Ignored while this account is a Super Admin.</p>
                @endif
            </div>
        </div>

        <!-- All Effective Permissions List -->
        <div class="lg:col-span-8 space-y-6">
            <div class="naoero-card p-6 space-y-4">
                <div class="flex items-center justify-between border-b border-gray-100 pb-3">
                    <h3 class="text-base font-extrabold text-navy flex items-center gap-2">
                        <span>🔑 All Effective Permissions</span>
                    </h3>
                    <span class="text-xs font-bold text-navy bg-navy-50 px-2.5 py-1 rounded-full">
                        {{ $allPermissions->count() }} Total Authorizations
                    </span>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-2.5 max-h-96 overflow-y-auto pr-1">
                    @forelse($allPermissions as $perm)
                        <div class="p-2.5 rounded-xl bg-gray-50 border border-gray-100 flex items-center justify-between text-xs">
                            <span class="font-mono text-navy font-semibold">{{ $perm->name }}</span>
                            <span class="text-[10px] px-2 py-0.5 rounded-md font-bold bg-emerald-100 text-emerald-800">
                                Granted
                            </span>
                        </div>
                    @empty
                        <p class="col-span-2 text-center py-6 text-gray-400 text-xs italic">No permissions available for this account.</p>
                    @endforelse
                </div>
            </div>

            <!-- User Recent Activity Logs -->
            <div class="naoero-card p-6 space-y-4">
                <h3 class="text-base font-extrabold text-navy border-b border-gray-100 pb-3">
                    📜 Recent Activity History
                </h3>

                <div class="space-y-3">
                    @forelse($user->activityLogs as $log)
                        <div class="flex items-start gap-3 text-xs">
                            <span class="w-6 h-6 rounded-lg bg-navy-50 text-navy flex items-center justify-center shrink-0 mt-0.5 font-bold text-[11px]">
                                ⚡
                            </span>
                            <div class="flex-1">
                                <p class="text-gray-800 font-semibold">{{ $log->description }}</p>
                                <p class="text-[11px] text-gray-400 font-mono mt-0.5">{{ $log->created_at->format('Y-m-d H:i') }} &bull; IP: {{ $log->ip_address }}</p>
                            </div>
                        </div>
                    @empty
                        <p class="text-xs text-gray-400 italic">No activity recorded for this user yet.</p>
                    @endforelse
                </div>
            </div>

        </div>

    </div>

</div>
@endsection
