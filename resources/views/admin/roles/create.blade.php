@extends('layouts.admin')

@section('title', 'Create Security Role')

@section('content')
<div class="max-w-5xl mx-auto space-y-6" x-data="{
    toggleModule(moduleId) {
        const checkboxes = document.querySelectorAll('.' + moduleId);
        const allChecked = Array.from(checkboxes).every(c => c.checked);
        checkboxes.forEach(c => c.checked = !allChecked);
    },
    selectAll() {
        document.querySelectorAll('.perm-checkbox').forEach(c => c.checked = true);
    },
    deselectAll() {
        document.querySelectorAll('.perm-checkbox').forEach(c => c.checked = false);
    }
}">

    <!-- Top Header & Action Bar -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div class="flex items-center gap-3">
            <a href="{{ route('admin.roles.index') }}" 
               class="p-2.5 rounded-xl bg-white border border-slate-200 text-slate-600 hover:text-navy hover:bg-slate-50 hover:border-slate-300 transition-all shadow-sm">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                </svg>
            </a>
            <div>
                <div class="flex items-center gap-2 text-xs font-semibold text-slate-500 mb-0.5">
                    <a href="{{ route('admin.roles.index') }}" class="hover:text-navy transition-colors">Roles & Permissions</a>
                    <span>/</span>
                    <span class="text-navy font-bold">New Role</span>
                </div>
                <h1 class="text-2xl font-extrabold text-navy tracking-tight">Create Access Role</h1>
                <p class="text-xs text-ink-muted">Define a new security role and configure authorization privileges across airline portal subsystems.</p>
            </div>
        </div>

        <div class="flex items-center gap-2.5">
            <a href="{{ route('admin.roles.index') }}" 
               class="px-4 py-2.5 rounded-xl border border-slate-200 bg-white hover:bg-slate-50 text-xs font-bold text-slate-700 transition-all shadow-sm">
                Cancel
            </a>
            <button type="button" 
                    @click="document.getElementById('role-create-form').submit()" 
                    class="px-5 py-2.5 rounded-xl bg-accent hover:bg-accent-hover text-navy font-extrabold text-xs shadow-md shadow-accent/20 hover:shadow-lg transition-all flex items-center gap-2">
                <svg class="w-4 h-4 text-navy" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
                </svg>
                <span>Save Role</span>
            </button>
        </div>
    </div>

    <!-- Error Alert Box -->
    @if ($errors->any())
        <div class="p-4 rounded-xl bg-rose-50 border border-rose-200 text-rose-800 text-xs shadow-sm">
            <div class="flex items-center gap-2 font-bold mb-1 text-sm text-rose-900">
                <svg class="w-4 h-4 text-rose-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                <span>Please correct the form errors:</span>
            </div>
            <ul class="list-disc list-inside space-y-0.5 text-rose-700">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form id="role-create-form" method="POST" action="{{ route('admin.roles.store') }}" class="space-y-6">
        @csrf

        <!-- CARD 1: Role Definition -->
        <div class="naoero-card p-6 bg-white border border-slate-200 space-y-5">
            <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                <div class="flex items-center gap-2.5">
                    <div class="w-8 h-8 rounded-xl bg-navy-50 text-navy font-black text-xs flex items-center justify-center">1</div>
                    <div>
                        <h2 class="text-sm font-extrabold text-navy uppercase tracking-wider">Role Definition & Guard</h2>
                        <p class="text-xs text-ink-muted">Set unique role title and authentication scope.</p>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">
                        Role Name <span class="text-rose-500">*</span>
                    </label>
                    <input type="text" 
                           name="name" 
                           value="{{ old('name') }}" 
                           required 
                           placeholder="e.g. Flight Dispatch Specialist, Content Manager" 
                           class="w-full px-3.5 py-2.5 text-sm rounded-xl border border-slate-200 focus:outline-none focus:ring-2 focus:ring-accent/40 focus:border-accent font-semibold">
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">
                        Authentication Guard
                    </label>
                    <input type="text" 
                           value="web" 
                           disabled 
                           class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm font-mono text-slate-500 cursor-not-allowed">
                </div>
            </div>
        </div>

        <!-- CARD 2: Permission Modules Matrix -->
        <div class="naoero-card p-6 bg-white border border-slate-200 space-y-5">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 border-b border-slate-100 pb-3">
                <div class="flex items-center gap-2.5">
                    <div class="w-8 h-8 rounded-xl bg-navy-50 text-navy font-black text-xs flex items-center justify-center">2</div>
                    <div>
                        <h2 class="text-sm font-extrabold text-navy uppercase tracking-wider">Module Permissions Matrix</h2>
                        <p class="text-xs text-ink-muted">Assign granular capabilities module by module.</p>
                    </div>
                </div>

                <div class="flex items-center gap-2">
                    <button type="button" @click="selectAll()" class="px-3 py-1.5 rounded-lg bg-navy-50 text-navy hover:bg-navy-100 text-xs font-bold transition-colors">
                        Select All
                    </button>
                    <button type="button" @click="deselectAll()" class="px-3 py-1.5 rounded-lg bg-slate-100 text-slate-700 hover:bg-slate-200 text-xs font-bold transition-colors">
                        Deselect All
                    </button>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                @foreach($permissions as $module => $modulePermissions)
                    @php $modSlug = Str::slug($module); @endphp
                    <div class="p-4 rounded-xl bg-slate-50 border border-slate-200 space-y-3">
                        <div class="flex items-center justify-between border-b border-slate-200/60 pb-2">
                            <span class="text-xs font-black text-navy uppercase tracking-wider">{{ $module }}</span>
                            <button type="button" 
                                    @click="toggleModule('{{ $modSlug }}')"
                                    class="text-[11px] font-bold text-navy hover:text-accent transition-colors">
                                Toggle All
                            </button>
                        </div>

                        <div class="space-y-1.5">
                            @foreach($modulePermissions as $perm)
                                <label class="flex items-center gap-2.5 p-1.5 rounded-lg hover:bg-white cursor-pointer transition-colors">
                                    <input type="checkbox" 
                                           name="permissions[]" 
                                           value="{{ $perm->name }}" 
                                           class="perm-checkbox {{ $modSlug }} w-4 h-4 rounded text-navy border-slate-300 focus:ring-accent"
                                           {{ in_array($perm->name, old('permissions', [])) ? 'checked' : '' }}>
                                    @include('admin.partials.permission-label', ['perm' => $perm])
                                </label>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

        <!-- Bottom Action Bar -->
        <div class="flex items-center justify-between p-4 rounded-2xl bg-white border border-slate-200 shadow-sm">
            <a href="{{ route('admin.roles.index') }}" class="px-5 py-2.5 rounded-xl border border-slate-200 text-slate-600 font-bold text-xs hover:bg-slate-50 transition-colors">
                Cancel
            </a>
            <button type="submit" class="px-6 py-2.5 rounded-xl bg-accent hover:bg-accent-hover text-navy font-extrabold text-xs shadow-md shadow-accent/20 hover:shadow-lg transition-all flex items-center gap-2">
                <svg class="w-4 h-4 text-navy" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
                </svg>
                <span>Save Role</span>
            </button>
        </div>
    </form>

</div>
@endsection
