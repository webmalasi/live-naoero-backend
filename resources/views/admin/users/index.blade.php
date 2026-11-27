@extends('layouts.admin')

@section('title', 'Admin Users & Staff')

@section('content')
<div class="space-y-6" x-data="{ resetPasswordModal: false, activeUser: null, resetActionUrl: '' }">

    <!-- PAGE HEADER & ACTION BAR -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-extrabold text-navy">Admin Users &amp; Staff</h1>
            <p class="text-xs text-gray-500 mt-0.5">People who sign in to this admin panel: their roles, status and activity. Public website and app accounts are in the <a href="{{ route('admin.customers.index') }}" class="font-bold text-navy underline">Users Directory</a>.</p>
        </div>

        <div class="flex items-center gap-3">
            <a href="{{ route('admin.users.export') }}" 
               class="px-3.5 py-2 rounded-xl bg-white border border-gray-200 hover:border-navy text-navy font-bold text-xs shadow-sm hover:shadow transition-all flex items-center gap-2">
                <svg class="w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                </svg>
                <span>Export CSV</span>
            </a>

            <a href="{{ route('admin.users.create') }}" 
               class="px-4 py-2 rounded-xl bg-navy hover:bg-[#062663] text-white font-bold text-xs shadow-md shadow-navy/20 hover:scale-105 transition-all flex items-center gap-2">
                <svg class="w-4 h-4 text-accent" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
                </svg>
                <span>+ Add New User</span>
            </a>
        </div>
    </div>

    <!-- QUICK STATS ROW -->
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
        <div class="p-4 rounded-2xl bg-white border border-gray-100 shadow-sm flex items-center justify-between">
            <div>
                <p class="text-[11px] font-bold uppercase tracking-wider text-gray-400">Total Users</p>
                <p class="text-xl font-extrabold text-navy mt-0.5">{{ $metrics['total'] }}</p>
            </div>
            <span class="w-8 h-8 rounded-xl bg-navy-50 text-navy flex items-center justify-center font-bold text-xs">👥</span>
        </div>

        <div class="p-4 rounded-2xl bg-white border border-gray-100 shadow-sm flex items-center justify-between">
            <div>
                <p class="text-[11px] font-bold uppercase tracking-wider text-gray-400">Active Accounts</p>
                <p class="text-xl font-extrabold text-emerald-600 mt-0.5">{{ $metrics['active'] }}</p>
            </div>
            <span class="w-8 h-8 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center font-bold text-xs">🟢</span>
        </div>

        <div class="p-4 rounded-2xl bg-white border border-gray-100 shadow-sm flex items-center justify-between">
            <div>
                <p class="text-[11px] font-bold uppercase tracking-wider text-gray-400">Inactive Accounts</p>
                <p class="text-xl font-extrabold text-rose-600 mt-0.5">{{ $metrics['inactive'] }}</p>
            </div>
            <span class="w-8 h-8 rounded-xl bg-rose-50 text-rose-600 flex items-center justify-center font-bold text-xs">🔴</span>
        </div>

        <div class="p-4 rounded-2xl bg-white border border-gray-100 shadow-sm flex items-center justify-between">
            <div>
                <p class="text-[11px] font-bold uppercase tracking-wider text-gray-400">Executive Admins</p>
                <p class="text-xl font-extrabold text-accent-hover mt-0.5">{{ $metrics['admins'] }}</p>
            </div>
            <span class="w-8 h-8 rounded-xl bg-amber-50 text-accent-hover flex items-center justify-center font-bold text-xs">👑</span>
        </div>
    </div>

    <!-- FILTER & SEARCH BAR -->
    <div class="p-4 rounded-2xl bg-white border border-gray-100 shadow-sm">
        <form method="GET" action="{{ route('admin.users.index') }}" class="grid grid-cols-1 sm:grid-cols-12 gap-3 items-center">
            
            <!-- Search -->
            <div class="sm:col-span-4 relative">
                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-gray-400">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                    </svg>
                </div>
                <input type="text" 
                       name="search" 
                       value="{{ request('search') }}" 
                       placeholder="Search by name, email, job..." 
                       class="w-full pl-9 pr-3 py-2 bg-gray-50 border border-gray-200 rounded-xl text-xs font-medium focus:outline-none focus:border-navy focus:ring-1 focus:ring-navy">
            </div>

            <!-- Role Filter -->
            <div class="sm:col-span-3">
                <select name="role" class="w-full px-3 py-2 bg-gray-50 border border-gray-200 rounded-xl text-xs font-medium text-gray-700 focus:outline-none focus:border-navy">
                    <option value="">All Roles</option>
                    @foreach($roles as $r)
                        <option value="{{ $r->name }}" {{ request('role') === $r->name ? 'selected' : '' }}>{{ $r->name }}</option>
                    @endforeach
                </select>
            </div>

            <!-- Status Filter -->
            <div class="sm:col-span-2">
                <select name="status" class="w-full px-3 py-2 bg-gray-50 border border-gray-200 rounded-xl text-xs font-medium text-gray-700 focus:outline-none focus:border-navy">
                    <option value="">All Statuses</option>
                    <option value="active" {{ request('status') === 'active' ? 'selected' : '' }}>Active</option>
                    <option value="inactive" {{ request('status') === 'inactive' ? 'selected' : '' }}>Inactive</option>
                    <option value="suspended" {{ request('status') === 'suspended' ? 'selected' : '' }}>Suspended</option>
                </select>
            </div>

            <!-- Submit & Reset Buttons -->
            <div class="sm:col-span-3 flex items-center gap-2">
                <button type="submit" class="flex-1 py-2 px-3 rounded-xl bg-navy hover:bg-[#062663] text-white font-bold text-xs transition-colors">
                    Filter
                </button>

                @if(request()->hasAny(['search', 'role', 'status', 'department']))
                    <a href="{{ route('admin.users.index') }}" class="py-2 px-3 rounded-xl bg-gray-100 hover:bg-gray-200 text-gray-700 font-bold text-xs transition-colors">
                        Reset
                    </a>
                @endif
            </div>

        </form>
    </div>

    <!-- USERS TABLE CARD -->
    <div class="naoero-card overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="bg-gray-50 border-b border-gray-100 text-xs font-extrabold uppercase tracking-wider text-gray-500">
                    <tr>
                        <th class="py-3.5 px-6">User Details</th>
                        <th class="py-3.5 px-4">Role & Access</th>
                        <th class="py-3.5 px-4">Department / Title</th>
                        <th class="py-3.5 px-4">Account Status</th>
                        <th class="py-3.5 px-4">Last Active</th>
                        <th class="py-3.5 px-6 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($users as $user)
                        <tr class="hover:bg-navy-50/30 transition-colors">
                            
                            <!-- User Name & Email -->
                            <td class="py-4 px-6">
                                <div class="flex items-center gap-3">
                                    <img src="{{ $user->avatar_url }}" alt="{{ $user->name }}" class="w-10 h-10 rounded-xl object-cover border border-navy/20 shadow-sm shrink-0">
                                    <div class="min-w-0">
                                        <a href="{{ route('admin.users.show', $user) }}" class="font-bold text-navy hover:text-accent-hover truncate block">
                                            {{ $user->name }}
                                        </a>
                                        <p class="text-xs text-gray-400 font-mono truncate">{{ $user->email }}</p>
                                        @if($user->phone)
                                            <p class="text-[11px] text-gray-500">{{ $user->phone }}</p>
                                        @endif
                                    </div>
                                </div>
                            </td>

                            <!-- Role Badges -->
                            <td class="py-4 px-4">
                                <div class="flex flex-wrap gap-1">
                                    @forelse($user->roles as $role)
                                        <span class="px-2.5 py-0.5 rounded-lg text-xs font-bold {{ $role->name === 'Super Admin' ? 'bg-amber-50 text-accent-hover border border-amber-200' : 'bg-navy-50 text-navy border border-navy-100' }}">
                                            {{ $role->name }}
                                        </span>
                                    @empty
                                        <span class="text-xs text-gray-400 italic">No assigned role</span>
                                    @endforelse
                                </div>
                                @if($user->permissions->count() > 0)
                                    <span class="inline-block mt-1 text-[10px] text-gray-500 font-semibold">+{{ $user->permissions->count() }} direct permissions</span>
                                @endif
                                @if($user->blockedPermissions->count() > 0)
                                    <span class="inline-block mt-1 text-[10px] text-rose-600 font-semibold">{{ $user->blockedPermissions->count() }} blocked</span>
                                @endif
                            </td>

                            <!-- Department & Job Title -->
                            <td class="py-4 px-4">
                                <p class="font-semibold text-gray-800 text-xs">{{ $user->job_title ?? 'Flight Personnel' }}</p>
                                <p class="text-[11px] text-gray-400">{{ $user->department ?? 'General Operations' }}</p>
                            </td>

                            <!-- Status & Quick Toggle -->
                            <td class="py-4 px-4">
                                <div class="flex items-center gap-2">
                                    @if($user->status === 'active')
                                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-bold bg-emerald-50 text-emerald-700 border border-emerald-200">
                                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span> Active
                                        </span>
                                    @elseif($user->status === 'inactive')
                                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-bold bg-gray-100 text-gray-600 border border-gray-200">
                                            <span class="w-1.5 h-1.5 rounded-full bg-gray-400"></span> Inactive
                                        </span>
                                    @else
                                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-bold bg-rose-50 text-rose-700 border border-rose-200">
                                            <span class="w-1.5 h-1.5 rounded-full bg-rose-500"></span> Suspended
                                        </span>
                                    @endif

                                    @if(auth()->id() !== $user->id)
                                        <form method="POST" action="{{ route('admin.users.toggle-status', $user) }}">
                                            @csrf
                                            @method('PATCH')
                                            <button type="submit" 
                                                    title="Toggle Status (Active/Inactive)"
                                                    class="p-1 rounded-lg text-gray-400 hover:text-navy hover:bg-navy-50 transition-colors">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                                                </svg>
                                            </button>
                                        </form>
                                    @endif
                                </div>
                            </td>

                            <!-- Last Login -->
                            <td class="py-4 px-4 text-xs font-mono text-gray-500">
                                {{ $user->last_login_at ? $user->last_login_at->format('M d, H:i') : 'Never' }}
                            </td>

                            <!-- Actions -->
                            <td class="py-4 px-6 text-right">
                                <div class="flex items-center justify-end gap-1.5">
                                    <!-- View Details -->
                                    <a href="{{ route('admin.users.show', $user) }}" 
                                       class="p-2 rounded-xl text-gray-600 hover:text-navy hover:bg-navy-50 transition-colors" title="View Dossier">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                        </svg>
                                    </a>

                                    <!-- Edit User -->
                                    <a href="{{ route('admin.users.edit', $user) }}" 
                                       class="p-2 rounded-xl text-gray-600 hover:text-navy hover:bg-navy-50 transition-colors" title="Edit Profile">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                        </svg>
                                    </a>

                                    <!-- Reset Password Modal Trigger -->
                                    <button type="button"
                                            @click="resetPasswordModal = true; activeUser = '{{ $user->name }}'; resetActionUrl = '{{ route('admin.users.reset-password', $user) }}'"
                                            class="p-2 rounded-xl text-gray-600 hover:text-accent-hover hover:bg-amber-50 transition-colors" title="Reset Password">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"/>
                                        </svg>
                                    </button>

                                    <!-- Delete User -->
                                    @if(auth()->id() !== $user->id)
                                        <form method="POST" action="{{ route('admin.users.destroy', $user) }}" onsubmit="return confirm('Are you sure you want to permanently delete user {{ $user->name }}?')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="p-2 rounded-xl text-gray-400 hover:text-red-600 hover:bg-red-50 transition-colors" title="Delete User">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                                </svg>
                                            </button>
                                        </form>
                                    @endif
                                </div>
                            </td>

                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center py-12 text-gray-400">
                                <p class="text-sm font-semibold">No flight personnel match your search criteria.</p>
                                <a href="{{ route('admin.users.index') }}" class="mt-2 inline-block text-xs font-bold text-navy hover:underline">Clear all filters</a>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        @if($users->hasPages())
            <div class="p-4 border-t border-gray-100 bg-gray-50/50">
                {{ $users->links() }}
            </div>
        @endif
    </div>

    <!-- RESET PASSWORD MODAL -->
    <div x-show="resetPasswordModal" 
         x-cloak
         class="fixed inset-0 z-50 overflow-y-auto flex items-center justify-center p-4 bg-navy-950/70 backdrop-blur-sm"
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-150"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0">
        
        <div class="bg-white rounded-3xl p-6 sm:p-8 max-w-md w-full shadow-2xl border border-gray-100" @click.outside="resetPasswordModal = false">
            <div class="flex items-center justify-between mb-4">
                <div class="flex items-center gap-2.5">
                    <div class="w-9 h-9 rounded-xl bg-amber-50 text-accent-hover flex items-center justify-center font-bold">
                        🔐
                    </div>
                    <div>
                        <h3 class="font-extrabold text-navy text-lg">Reset Password</h3>
                        <p class="text-xs text-gray-500" x-text="'For: ' + activeUser"></p>
                    </div>
                </div>
                <button @click="resetPasswordModal = false" class="text-gray-400 hover:text-gray-600 text-lg">&times;</button>
            </div>

            <form :action="resetActionUrl" method="POST" class="space-y-4">
                @csrf
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-navy mb-1">New Password</label>
                    <input type="password" name="new_password" required minlength="8" placeholder="Minimum 8 characters" class="w-full px-3.5 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-sm focus:outline-none focus:border-navy focus:ring-1 focus:ring-navy">
                </div>

                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-navy mb-1">Confirm Password</label>
                    <input type="password" name="new_password_confirmation" required minlength="8" placeholder="Re-enter password" class="w-full px-3.5 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-sm focus:outline-none focus:border-navy focus:ring-1 focus:ring-navy">
                </div>

                <div class="pt-2 flex items-center justify-end gap-2">
                    <button type="button" @click="resetPasswordModal = false" class="px-4 py-2 rounded-xl text-gray-600 hover:bg-gray-100 font-bold text-xs">
                        Cancel
                    </button>
                    <button type="submit" class="px-4 py-2 rounded-xl bg-navy text-white font-bold text-xs hover:bg-[#062663] shadow-md shadow-navy/20">
                        Confirm Reset
                    </button>
                </div>
            </form>
        </div>
    </div>

</div>
@endsection
