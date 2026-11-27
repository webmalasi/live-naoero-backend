@extends('layouts.admin')

@section('title', 'Role Dossier: ' . $role->name)

@section('content')
<div class="max-w-6xl mx-auto space-y-6">

    <!-- HEADER -->
    <div class="flex items-center justify-between">
        <div class="flex items-center gap-3">
            <a href="{{ route('admin.roles.index') }}" class="p-2 rounded-xl bg-white border border-gray-200 text-gray-600 hover:text-navy hover:border-navy transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                </svg>
            </a>
            <div>
                <h1 class="text-2xl font-extrabold text-navy">Role Dossier: {{ $role->name }}</h1>
                <p class="text-xs text-gray-500">Security authorization breakdown and list of assigned flight personnel.</p>
            </div>
        </div>

        <div class="flex items-center gap-2">
            <a href="{{ route('admin.roles.edit', $role) }}" class="px-4 py-2 rounded-xl bg-navy hover:bg-[#062663] text-white font-bold text-xs shadow-md shadow-navy/20 hover:scale-105 transition-all flex items-center gap-2">
                <svg class="w-4 h-4 text-accent" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                </svg>
                <span>Edit Role Permissions</span>
            </a>
        </div>
    </div>

    <!-- ROLE DETAILS BANNER -->
    <div class="naoero-card p-6 sm:p-8">
        <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
            <div class="flex items-center gap-4">
                <div class="w-16 h-16 rounded-2xl bg-navy text-accent flex items-center justify-center font-bold text-2xl shadow-lg shadow-navy/20">
                    @if($role->name === 'Super Admin') 👑 @else 🛡️ @endif
                </div>
                <div>
                    <div class="flex items-center gap-2">
                        <h2 class="text-2xl font-extrabold text-navy">{{ $role->name }}</h2>
                        <span class="text-xs font-mono px-2 py-0.5 rounded bg-gray-100 text-gray-600">guard: {{ $role->guard_name }}</span>
                    </div>
                    <p class="text-xs text-gray-500 mt-1">Configured for Naoero Airlines executive and administrative control.</p>
                </div>
            </div>

            <div class="flex items-center gap-3">
                <div class="p-3 px-4 rounded-2xl bg-navy-50 border border-navy-100 text-center">
                    <span class="block text-xl font-extrabold text-navy">{{ $role->permissions->count() }}</span>
                    <span class="text-[10px] font-bold uppercase tracking-wider text-gray-400">Permissions</span>
                </div>

                <div class="p-3 px-4 rounded-2xl bg-amber-50 border border-amber-200 text-center">
                    <span class="block text-xl font-extrabold text-accent-hover">{{ $role->users->count() }}</span>
                    <span class="text-[10px] font-bold uppercase tracking-wider text-gray-400">Assigned Users</span>
                </div>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">

        <!-- ASSIGNED USERS -->
        <div class="lg:col-span-4 space-y-6">
            <div class="naoero-card p-6 space-y-4">
                <h3 class="text-base font-extrabold text-navy border-b border-gray-100 pb-3 flex items-center justify-between">
                    <span>👥 Assigned Personnel</span>
                    <span class="text-xs bg-navy-50 text-navy font-bold px-2 py-0.5 rounded-full">{{ $role->users->count() }}</span>
                </h3>

                <div class="space-y-3 max-h-96 overflow-y-auto pr-1">
                    @forelse($role->users as $user)
                        <a href="{{ route('admin.users.show', $user) }}" class="flex items-center gap-3 p-2.5 rounded-xl hover:bg-navy-50/50 transition-colors">
                            <img src="{{ $user->avatar_url }}" alt="{{ $user->name }}" class="w-9 h-9 rounded-xl object-cover border border-navy/20">
                            <div class="min-w-0 flex-1">
                                <p class="text-xs font-bold text-navy truncate">{{ $user->name }}</p>
                                <p class="text-[11px] text-gray-400 font-mono truncate">{{ $user->email }}</p>
                            </div>
                        </a>
                    @empty
                        <p class="text-xs text-gray-400 italic">No users currently assigned to this role.</p>
                    @endforelse
                </div>
            </div>
        </div>

        <!-- GRANTED PERMISSIONS ACCORDION/GRID -->
        <div class="lg:col-span-8 space-y-6">
            <div class="naoero-card p-6 space-y-4">
                <h3 class="text-base font-extrabold text-navy border-b border-gray-100 pb-3">
                    🔑 Granted Privileges & Authorizations
                </h3>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    @php
                        $rolePermNames = $role->permissions->pluck('name')->toArray();
                    @endphp

                    @foreach($allPermissions as $module => $modPermissions)
                        <div class="p-4 rounded-2xl bg-gray-50 border border-gray-100 space-y-2">
                            <span class="text-xs font-extrabold text-navy uppercase tracking-wider block border-b border-gray-200 pb-1">{{ $module }}</span>
                            
                            <div class="space-y-1.5">
                                @foreach($modPermissions as $perm)
                                    @php $hasPerm = in_array($perm->name, $rolePermNames); @endphp
                                    <div class="flex items-center justify-between text-xs py-0.5">
                                        <span class="font-mono {{ $hasPerm ? 'text-navy font-bold' : 'text-gray-400 line-through' }}">{{ $perm->name }}</span>
                                        @if($hasPerm)
                                            <span class="text-[10px] px-1.5 py-0.2 rounded bg-emerald-100 text-emerald-800 font-bold">Enabled</span>
                                        @else
                                            <span class="text-[10px] px-1.5 py-0.2 rounded bg-gray-200 text-gray-500">Disabled</span>
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

    </div>

</div>
@endsection
