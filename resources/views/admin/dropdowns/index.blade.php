@extends('layouts.admin')

@section('title', 'Dynamic Dropdowns & Master Taxonomies')

@section('content')
<div class="max-w-7xl mx-auto space-y-6" x-data="{
    showAddModal: false,
    showEditModal: false,
    selectedGroup: '{{ $selectedGroup }}',
    editData: {
        id: null,
        name: '',
        value: '',
        badge_color: 'blue',
        icon: '',
        description: '',
        sort_order: 0,
        is_active: true,
        actionUrl: ''
    },
    openEdit(item, url) {
        this.editData = {
            id: item.id,
            name: item.name,
            value: item.value,
            badge_color: item.badge_color || 'blue',
            icon: item.icon || '',
            description: item.description || '',
            sort_order: item.sort_order || 0,
            is_active: !!item.is_active,
            actionUrl: url
        };
        this.showEditModal = true;
    },
    async toggleStatus(optId, el) {
        try {
            let res = await fetch(`/admin/dropdowns/${optId}/toggle`, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                    'Accept': 'application/json'
                }
            });
            let data = await res.json();
            if (data.success) {
                // Flash visual update
                el.classList.add('ring-2', 'ring-emerald-400');
                setTimeout(() => el.classList.remove('ring-2', 'ring-emerald-400'), 1000);
            }
        } catch (e) {
            console.error(e);
        }
    }
}">

    <!-- Top Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <div class="flex items-center gap-2 text-xs font-semibold text-slate-500 mb-0.5">
                <a href="{{ route('admin.dashboard') }}" class="hover:text-navy transition-colors">Admin</a>
                <span>/</span>
                <span class="text-navy font-bold">Dynamic Dropdowns & Master Taxonomies</span>
            </div>
            <h1 class="text-2xl font-extrabold text-navy tracking-tight">Dynamic Dropdowns & Taxonomy Master</h1>
            <p class="text-xs text-ink-muted">Complete centralized control over all dropdown categories, lookup values, and classification options across all airline modules.</p>
        </div>

        <button type="button" 
                @click="showAddModal = true" 
                class="px-5 py-2.5 rounded-xl bg-accent hover:bg-accent-hover text-navy font-extrabold text-xs shadow-md shadow-accent/20 hover:shadow-lg transition-all flex items-center gap-2 self-start sm:self-auto">
            <svg class="w-4 h-4 text-navy" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"/>
            </svg>
            <span>+ Add New Dropdown Option</span>
        </button>
    </div>

    <!-- Alert Messages -->
    @if(session('success'))
        <div class="p-4 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-800 text-xs font-bold flex items-center justify-between shadow-sm">
            <div class="flex items-center gap-2">
                <span>✅</span>
                <span>{{ session('success') }}</span>
            </div>
            <button type="button" onclick="this.parentElement.remove()" class="text-emerald-500 hover:text-emerald-700">&times;</button>
        </div>
    @endif

    <!-- KPI Summary Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <div class="naoero-card p-4 bg-white border border-slate-200 flex items-center justify-between">
            <div>
                <span class="text-[11px] font-bold text-slate-500 uppercase tracking-wider block">Total Dropdown Options</span>
                <span class="text-2xl font-black text-navy">{{ $totalOptionsCount }}</span>
                <span class="text-[10px] text-slate-400 block mt-0.5">Across all {{ $totalGroupsCount }} module groups</span>
            </div>
            <div class="w-10 h-10 rounded-xl bg-blue-50 text-blue-600 flex items-center justify-center font-bold text-base">
                🗂️
            </div>
        </div>

        <div class="naoero-card p-4 bg-white border border-slate-200 flex items-center justify-between">
            <div>
                <span class="text-[11px] font-bold text-slate-500 uppercase tracking-wider block">Active Live Options</span>
                <span class="text-2xl font-black text-emerald-600">{{ $activeOptionsCount }}</span>
                <span class="text-[10px] text-emerald-500 block mt-0.5">Available in forms & filters</span>
            </div>
            <div class="w-10 h-10 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center font-bold text-base">
                ✅
            </div>
        </div>

        <div class="naoero-card p-4 bg-white border border-slate-200 flex items-center justify-between">
            <div>
                <span class="text-[11px] font-bold text-slate-500 uppercase tracking-wider block">Master Option Groups</span>
                <span class="text-2xl font-black text-navy">{{ $totalGroupsCount }}</span>
                <span class="text-[10px] text-slate-400 block mt-0.5">Zero hardcoded dropdowns</span>
            </div>
            <div class="w-10 h-10 rounded-xl bg-amber-50 text-amber-600 flex items-center justify-center font-bold text-base">
                ⚙️
            </div>
        </div>
    </div>

    <!-- Main Layout: Group Navigation Pills & Dynamic Table -->
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">
        
        <!-- Left Sidebar: Dropdown Groups Menu (4 Cols) -->
        <div class="lg:col-span-4 space-y-3">
            <div class="naoero-card p-4 bg-white border border-slate-200 shadow-sm space-y-3">
                <div class="flex items-center justify-between border-b border-slate-100 pb-2.5">
                    <span class="text-xs font-extrabold text-navy uppercase tracking-wider">Select Dropdown Group</span>
                    <a href="{{ route('admin.dropdowns.index', ['group' => 'all']) }}" 
                       class="text-[11px] font-bold {{ $selectedGroup === 'all' ? 'text-navy font-extrabold' : 'text-slate-400 hover:text-navy' }}">
                        View All ({{ $totalOptionsCount }})
                    </a>
                </div>

                <div class="space-y-1 max-h-[600px] overflow-y-auto pr-1">
                    @foreach($allGroupsMeta as $gKey => $gMeta)
                        @php
                            $cnt = $groupCounts[$gKey]->total ?? 0;
                            $isSelected = ($selectedGroup === $gKey);
                        @endphp
                        <a href="{{ route('admin.dropdowns.index', ['group' => $gKey]) }}" 
                           class="flex items-center justify-between p-2.5 rounded-xl text-xs transition-all {{ $isSelected ? 'bg-navy text-white font-extrabold shadow-sm' : 'text-slate-700 hover:bg-slate-50 hover:text-navy' }}">
                            <div class="flex items-center gap-2.5 truncate">
                                <span class="text-sm shrink-0">{{ $gMeta['icon'] ?? '📁' }}</span>
                                <div class="truncate">
                                    <span class="block truncate {{ $isSelected ? 'text-white' : 'text-slate-800' }}">{{ $gMeta['title'] }}</span>
                                    <span class="text-[10px] block truncate {{ $isSelected ? 'text-white/70' : 'text-slate-400' }}">{{ $gMeta['module'] }}</span>
                                </div>
                            </div>
                            <span class="px-2 py-0.5 rounded-full text-[10px] font-mono font-bold shrink-0 {{ $isSelected ? 'bg-accent text-navy' : 'bg-slate-100 text-slate-600' }}">
                                {{ $cnt }}
                            </span>
                        </a>
                    @endforeach
                </div>
            </div>
        </div>

        <!-- Right Side: Dynamic DataTable & Actions (8 Cols) -->
        <div class="lg:col-span-8 space-y-4">

            <!-- Active Group Context Banner -->
            @if(isset($allGroupsMeta[$selectedGroup]))
                <div class="naoero-card p-4 bg-gradient-to-r from-navy/5 via-white to-accent/5 border border-slate-200 shadow-sm flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                    <div class="flex items-center gap-3">
                        <span class="text-2xl p-2 rounded-xl bg-white border border-slate-200 shadow-xs">{{ $allGroupsMeta[$selectedGroup]['icon'] ?? '📁' }}</span>
                        <div>
                            <div class="flex items-center gap-2">
                                <h2 class="text-sm font-extrabold text-navy">{{ $allGroupsMeta[$selectedGroup]['title'] }}</h2>
                                <span class="px-2 py-0.5 rounded bg-navy/10 text-navy font-mono text-[10px] font-bold">{{ $selectedGroup }}</span>
                            </div>
                            <p class="text-[11px] text-slate-500 mt-0.5">{{ $allGroupsMeta[$selectedGroup]['description'] }}</p>
                        </div>
                    </div>

                    <button type="button" 
                            @click="showAddModal = true" 
                            class="px-3.5 py-1.5 rounded-lg bg-navy text-white hover:bg-navy-900 text-xs font-bold transition-all shadow-xs flex items-center gap-1.5 self-start sm:self-auto shrink-0">
                        <span>+ Add to this group</span>
                    </button>
                </div>
            @endif

            <!-- Search, Filter & Per-Page Controls Toolbar -->
            <div class="naoero-card p-4 bg-white border border-slate-200 shadow-sm">
                <form method="GET" action="{{ route('admin.dropdowns.index') }}" class="grid grid-cols-1 sm:grid-cols-12 gap-3">
                    <input type="hidden" name="group" value="{{ $selectedGroup }}">

                    <!-- Search Input -->
                    <div class="sm:col-span-6 relative">
                        <input type="text" 
                               name="search" 
                               value="{{ request('search') }}" 
                               placeholder="Search option name, value, description..." 
                               class="w-full pl-9 pr-3.5 py-2 text-xs rounded-xl border border-slate-200 focus:outline-none focus:ring-2 focus:ring-accent/40 font-medium">
                        <svg class="w-4 h-4 text-slate-400 absolute left-3 top-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                        </svg>
                    </div>

                    <!-- Status Filter -->
                    <div class="sm:col-span-3">
                        <select name="status" onchange="this.form.submit()" class="w-full px-3 py-2 text-xs font-bold rounded-xl border border-slate-200 bg-white">
                            <option value="">All Statuses</option>
                            <option value="active" {{ request('status') === 'active' ? 'selected' : '' }}>🟢 Active Only</option>
                            <option value="inactive" {{ request('status') === 'inactive' ? 'selected' : '' }}>⚪ Inactive Only</option>
                        </select>
                    </div>

                    <!-- Sort Order -->
                    <div class="sm:col-span-3">
                        <select name="sort" onchange="this.form.submit()" class="w-full px-3 py-2 text-xs font-bold rounded-xl border border-slate-200 bg-white">
                            <option value="order_asc" {{ request('sort') === 'order_asc' ? 'selected' : '' }}>Sort Order (1,2,3)</option>
                            <option value="name_asc" {{ request('sort') === 'name_asc' ? 'selected' : '' }}>Name (A to Z)</option>
                            <option value="name_desc" {{ request('sort') === 'name_desc' ? 'selected' : '' }}>Name (Z to A)</option>
                            <option value="newest" {{ request('sort') === 'newest' ? 'selected' : '' }}>Newest Created</option>
                        </select>
                    </div>
                </form>
            </div>

            <!-- Dynamic Table Card -->
            <div class="naoero-card bg-white border border-slate-200 shadow-sm overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs">
                        <thead class="bg-slate-50 text-slate-500 font-extrabold uppercase tracking-wider border-b border-slate-200">
                            <tr>
                                <th class="py-3 px-4 w-12 text-center">#</th>
                                <th class="py-3 px-4">Display Label & Icon</th>
                                <th class="py-3 px-4">Stored Value / Key</th>
                                <th class="py-3 px-4">Badge Preview</th>
                                <th class="py-3 px-4 text-center">Status</th>
                                <th class="py-3 px-4 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 font-medium">
                            @forelse($options as $opt)
                                <tr class="hover:bg-slate-50/70 transition-colors">
                                    <!-- Sort Order -->
                                    <td class="py-3.5 px-4 text-center">
                                        <span class="px-2 py-0.5 rounded bg-slate-100 text-slate-600 font-mono text-[10px] font-bold">
                                            {{ $opt->sort_order }}
                                        </span>
                                    </td>

                                    <!-- Name & Icon -->
                                    <td class="py-3.5 px-4">
                                        <div class="flex items-center gap-2">
                                            @if($opt->icon)
                                                <span class="text-sm shrink-0">{{ $opt->icon }}</span>
                                            @endif
                                            <div>
                                                <span class="font-extrabold text-navy text-xs block">{{ $opt->name }}</span>
                                                @if($opt->description)
                                                    <span class="text-[10px] text-slate-400 block line-clamp-1">{{ $opt->description }}</span>
                                                @endif
                                            </div>
                                        </div>
                                    </td>

                                    <!-- Value -->
                                    <td class="py-3.5 px-4">
                                        <code class="px-2 py-0.5 rounded bg-slate-100 text-slate-700 font-mono text-[11px] font-bold border border-slate-200">
                                            {{ $opt->value }}
                                        </code>
                                    </td>

                                    <!-- Badge Preview -->
                                    <td class="py-3.5 px-4">
                                        @php
                                            $colorMap = [
                                                'blue' => 'bg-blue-100 text-blue-900 border-blue-200',
                                                'emerald' => 'bg-emerald-100 text-emerald-900 border-emerald-200',
                                                'amber' => 'bg-amber-100 text-amber-900 border-amber-300',
                                                'rose' => 'bg-rose-100 text-rose-900 border-rose-200',
                                                'purple' => 'bg-purple-100 text-purple-900 border-purple-200',
                                                'slate' => 'bg-slate-100 text-slate-900 border-slate-300',
                                                'indigo' => 'bg-indigo-100 text-indigo-900 border-indigo-200',
                                            ];
                                            $cls = $colorMap[$opt->badge_color] ?? $colorMap['blue'];
                                        @endphp
                                        <span class="px-2.5 py-0.5 rounded-full text-[10px] font-black uppercase tracking-wider border {{ $cls }}">
                                            {{ $opt->name }}
                                        </span>
                                    </td>

                                    <!-- Active Toggle -->
                                    <td class="py-3.5 px-4 text-center">
                                        <button type="button" 
                                                @click="toggleStatus({{ $opt->id }}, $el)"
                                                class="px-2.5 py-1 rounded-full text-[10px] font-black uppercase tracking-wider transition-all {{ $opt->is_active ? 'bg-emerald-100 text-emerald-800 hover:bg-emerald-200' : 'bg-slate-100 text-slate-500 hover:bg-slate-200' }}">
                                            {{ $opt->is_active ? '● Active' : '○ Disabled' }}
                                        </button>
                                    </td>

                                    <!-- Actions -->
                                    <td class="py-3.5 px-4 text-right">
                                        <div class="flex items-center justify-end gap-1.5">
                                            <button type="button" 
                                                    @click="openEdit({{ json_encode($opt) }}, '{{ route('admin.dropdowns.update', $opt) }}')" 
                                                    class="p-1.5 rounded-lg border border-slate-200 hover:bg-slate-100 text-slate-600 hover:text-navy transition-colors title='Edit Option'">
                                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/>
                                                </svg>
                                            </button>

                                            <form method="POST" action="{{ route('admin.dropdowns.destroy', $opt) }}" onsubmit="return confirm('Delete dropdown option \'{{ $opt->name }}\'?');" class="inline">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="p-1.5 rounded-lg border border-slate-200 hover:bg-rose-50 text-slate-400 hover:text-rose-600 transition-colors" title="Delete Option">
                                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                                    </svg>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="py-12 text-center text-slate-400 text-xs">
                                        <div class="space-y-2">
                                            <span class="text-3xl block">📭</span>
                                            <p class="font-bold text-slate-600">No dropdown options found in this group.</p>
                                            <button type="button" @click="showAddModal = true" class="text-navy underline font-bold">
                                                + Add First Option
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                @if($options->hasPages())
                    <div class="p-4 border-t border-slate-100 bg-slate-50">
                        {{ $options->links() }}
                    </div>
                @endif
            </div>

        </div>

    </div>

    <!-- Modal: Add New Dropdown Option -->
    <div x-show="showAddModal" 
         x-cloak 
         class="fixed inset-0 z-50 overflow-y-auto bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4">
        <div class="bg-white rounded-2xl max-w-lg w-full p-6 shadow-2xl border border-slate-200 space-y-5" @click.away="showAddModal = false">
            <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                <div class="flex items-center gap-2">
                    <span class="w-8 h-8 rounded-xl bg-accent/20 text-navy font-bold flex items-center justify-center text-sm">+</span>
                    <div>
                        <h3 class="text-base font-extrabold text-navy">Add New Dropdown Option</h3>
                        <p class="text-[11px] text-slate-400">Dynamically add a new category or lookup option to the system.</p>
                    </div>
                </div>
                <button type="button" @click="showAddModal = false" class="text-slate-400 hover:text-slate-600 text-lg font-bold">&times;</button>
            </div>

            <form method="POST" action="{{ route('admin.dropdowns.store') }}" class="space-y-4">
                @csrf

                <!-- Group Selector -->
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">
                        Dropdown Group <span class="text-rose-500">*</span>
                    </label>
                    <select name="group" required class="w-full px-3.5 py-2 text-xs font-bold rounded-xl border border-slate-200 bg-white">
                        @foreach($allGroupsMeta as $gKey => $gMeta)
                            <option value="{{ $gKey }}" {{ $selectedGroup === $gKey ? 'selected' : '' }}>
                                {{ $gMeta['icon'] ?? '📁' }} {{ $gMeta['title'] }} ({{ $gMeta['module'] }})
                            </option>
                        @endforeach
                    </select>
                </div>

                <!-- Display Name -->
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">
                        Display Name / Label <span class="text-rose-500">*</span>
                    </label>
                    <input type="text" 
                           name="name" 
                           placeholder="e.g. Travel Accessories, Boeing 787, First Class" 
                           required 
                           class="w-full px-3.5 py-2 text-sm font-semibold rounded-xl border border-slate-200 focus:outline-none focus:ring-2 focus:ring-accent/40">
                </div>

                <!-- Stored Value (Slug) -->
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">
                        Stored Value / Slug (Optional, auto-uses Name if blank)
                    </label>
                    <input type="text" 
                           name="value" 
                           placeholder="e.g. Accessories, B787, first_class" 
                           class="w-full px-3.5 py-2 text-xs font-mono rounded-xl border border-slate-200 bg-slate-50 focus:outline-none">
                </div>

                <!-- Badge Color & Icon -->
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">
                            Badge Pill Color
                        </label>
                        <select name="badge_color" class="w-full px-3 py-2 text-xs font-bold rounded-xl border border-slate-200 bg-white">
                            <option value="blue">🔵 Blue Pill</option>
                            <option value="emerald">🟢 Emerald Green</option>
                            <option value="amber">🟡 Amber Gold</option>
                            <option value="rose">🔴 Rose Red</option>
                            <option value="purple">🟣 Purple</option>
                            <option value="indigo">🌌 Indigo</option>
                            <option value="slate">⚪ Slate Gray</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">
                            Emoji / Icon (Optional)
                        </label>
                        <input type="text" name="icon" placeholder="e.g. 🧥, ✈️, 🎒" class="w-full px-3 py-2 text-sm text-center rounded-xl border border-slate-200">
                    </div>
                </div>

                <!-- Sort Order & Description -->
                <div class="grid grid-cols-3 gap-3">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">
                            Sort Order
                        </label>
                        <input type="number" name="sort_order" value="0" class="w-full px-3 py-2 text-xs rounded-xl border border-slate-200 font-bold">
                    </div>
                    <div class="col-span-2">
                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">
                            Description / Notes
                        </label>
                        <input type="text" name="description" placeholder="Brief note..." class="w-full px-3 py-2 text-xs rounded-xl border border-slate-200">
                    </div>
                </div>

                <!-- Active Toggle -->
                <div class="pt-2">
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="checkbox" name="is_active" value="1" checked class="w-4 h-4 rounded text-navy focus:ring-accent">
                        <span class="text-xs font-bold text-slate-700">Enable Option Immediately</span>
                    </label>
                </div>

                <div class="flex items-center justify-end gap-2.5 pt-3 border-t border-slate-100">
                    <button type="button" @click="showAddModal = false" class="px-4 py-2 rounded-xl border border-slate-200 text-xs font-bold text-slate-600 hover:bg-slate-50">
                        Cancel
                    </button>
                    <button type="submit" class="px-5 py-2 rounded-xl bg-navy hover:bg-navy-900 text-white font-extrabold text-xs shadow-sm">
                        Create Dropdown Option
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal: Edit Dropdown Option -->
    <div x-show="showEditModal" 
         x-cloak 
         class="fixed inset-0 z-50 overflow-y-auto bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4">
        <div class="bg-white rounded-2xl max-w-lg w-full p-6 shadow-2xl border border-slate-200 space-y-5" @click.away="showEditModal = false">
            <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                <h3 class="text-base font-extrabold text-navy">Edit Dropdown Option</h3>
                <button type="button" @click="showEditModal = false" class="text-slate-400 hover:text-slate-600 text-lg font-bold">&times;</button>
            </div>

            <form method="POST" :action="editData.actionUrl" class="space-y-4">
                @csrf
                @method('PUT')

                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">
                        Display Name / Label <span class="text-rose-500">*</span>
                    </label>
                    <input type="text" 
                           name="name" 
                           x-model="editData.name" 
                           required 
                           class="w-full px-3.5 py-2 text-sm font-semibold rounded-xl border border-slate-200 focus:outline-none focus:ring-2 focus:ring-accent/40">
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">
                        Stored Value / Slug <span class="text-rose-500">*</span>
                    </label>
                    <input type="text" 
                           name="value" 
                           x-model="editData.value" 
                           required 
                           class="w-full px-3.5 py-2 text-xs font-mono rounded-xl border border-slate-200 bg-slate-50 focus:outline-none">
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">
                            Badge Pill Color
                        </label>
                        <select name="badge_color" x-model="editData.badge_color" class="w-full px-3 py-2 text-xs font-bold rounded-xl border border-slate-200 bg-white">
                            <option value="blue">🔵 Blue Pill</option>
                            <option value="emerald">🟢 Emerald Green</option>
                            <option value="amber">🟡 Amber Gold</option>
                            <option value="rose">🔴 Rose Red</option>
                            <option value="purple">🟣 Purple</option>
                            <option value="indigo">🌌 Indigo</option>
                            <option value="slate">⚪ Slate Gray</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">
                            Emoji / Icon
                        </label>
                        <input type="text" name="icon" x-model="editData.icon" class="w-full px-3 py-2 text-sm text-center rounded-xl border border-slate-200">
                    </div>
                </div>

                <div class="grid grid-cols-3 gap-3">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">
                            Sort Order
                        </label>
                        <input type="number" name="sort_order" x-model="editData.sort_order" class="w-full px-3 py-2 text-xs rounded-xl border border-slate-200 font-bold">
                    </div>
                    <div class="col-span-2">
                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">
                            Description
                        </label>
                        <input type="text" name="description" x-model="editData.description" class="w-full px-3 py-2 text-xs rounded-xl border border-slate-200">
                    </div>
                </div>

                <div class="pt-2">
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="checkbox" name="is_active" value="1" x-model="editData.is_active" class="w-4 h-4 rounded text-navy focus:ring-accent">
                        <span class="text-xs font-bold text-slate-700">Option Active</span>
                    </label>
                </div>

                <div class="flex items-center justify-end gap-2.5 pt-3 border-t border-slate-100">
                    <button type="button" @click="showEditModal = false" class="px-4 py-2 rounded-xl border border-slate-200 text-xs font-bold text-slate-600 hover:bg-slate-50">
                        Cancel
                    </button>
                    <button type="submit" class="px-5 py-2 rounded-xl bg-navy hover:bg-navy-900 text-white font-extrabold text-xs shadow-sm">
                        Update Option
                    </button>
                </div>
            </form>
        </div>
    </div>

</div>
@endsection
