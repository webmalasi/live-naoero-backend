@extends('layouts.admin')

@section('title', 'Add New User Account')

@section('content')
<div class="max-w-5xl mx-auto space-y-6" x-data="{
    avatarPreview: null,
    handleAvatarUpload(event) {
        const file = event.target.files[0];
        if (file) {
            const reader = new FileReader();
            reader.onload = (e) => {
                this.avatarPreview = e.target.result;
            };
            reader.readAsDataURL(file);
        }
    }
}">

    <!-- Top Header & Action Bar -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div class="flex items-center gap-3">
            <a href="{{ route('admin.users.index') }}" 
               class="p-2.5 rounded-xl bg-white border border-slate-200 text-slate-600 hover:text-navy hover:bg-slate-50 hover:border-slate-300 transition-all shadow-sm">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                </svg>
            </a>
            <div>
                <div class="flex items-center gap-2 text-xs font-semibold text-slate-500 mb-0.5">
                    <a href="{{ route('admin.users.index') }}" class="hover:text-navy transition-colors">Users & Personnel</a>
                    <span>/</span>
                    <span class="text-navy font-bold">New Account</span>
                </div>
                <h1 class="text-2xl font-extrabold text-navy tracking-tight">Add New User Account</h1>
                <p class="text-xs text-ink-muted">Create administrative, flight operations, dispatcher, or customer service user accounts.</p>
            </div>
        </div>

        <div class="flex items-center gap-2.5">
            <a href="{{ route('admin.users.index') }}" 
               class="px-4 py-2.5 rounded-xl border border-slate-200 bg-white hover:bg-slate-50 text-xs font-bold text-slate-700 transition-all shadow-sm">
                Cancel
            </a>
            <button type="button" 
                    @click="document.getElementById('user-create-form').submit()" 
                    class="px-5 py-2.5 rounded-xl bg-accent hover:bg-accent-hover text-navy font-extrabold text-xs shadow-md shadow-accent/20 hover:shadow-lg transition-all flex items-center gap-2">
                <svg class="w-4 h-4 text-navy" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
                </svg>
                <span>Save User Account</span>
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

    <form id="user-create-form" method="POST" action="{{ route('admin.users.store') }}" enctype="multipart/form-data" class="space-y-6">
        @csrf

        <!-- CARD 1: Identity & Credentials -->
        <div class="naoero-card p-6 bg-white border border-slate-200 space-y-5">
            <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                <div class="flex items-center gap-2.5">
                    <div class="w-8 h-8 rounded-xl bg-navy-50 text-navy font-black text-xs flex items-center justify-center">1</div>
                    <div>
                        <h2 class="text-sm font-extrabold text-navy uppercase tracking-wider">Account Identity & Credentials</h2>
                        <p class="text-xs text-ink-muted">Set user name, official email, phone number, and access credentials.</p>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                <!-- Full Name -->
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">
                        Full Name <span class="text-rose-500">*</span>
                    </label>
                    <input type="text" 
                           name="name" 
                           value="{{ old('name') }}" 
                           required 
                           placeholder="e.g. Captain David Adeang" 
                           class="w-full px-3.5 py-2.5 text-sm rounded-xl border border-slate-200 focus:outline-none focus:ring-2 focus:ring-accent/40 focus:border-accent font-semibold">
                </div>

                <!-- Email -->
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">
                        Official Email Address <span class="text-rose-500">*</span>
                    </label>
                    <input type="email" 
                           name="email" 
                           value="{{ old('email') }}" 
                           required 
                           placeholder="e.g. david.adeang@naoero-airlines.com" 
                           class="w-full px-3.5 py-2.5 text-sm rounded-xl border border-slate-200 focus:outline-none focus:ring-2 focus:ring-accent/40 focus:border-accent">
                </div>

                <!-- Phone -->
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">
                        Phone Number
                    </label>
                    <input type="text" 
                           name="phone" 
                           value="{{ old('phone') }}" 
                           placeholder="e.g. +674 557 7000" 
                           class="w-full px-3.5 py-2.5 text-sm rounded-xl border border-slate-200 focus:outline-none focus:ring-2 focus:ring-accent/40 focus:border-accent">
                </div>

                <!-- Account Status -->
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">
                        Account Status <span class="text-rose-500">*</span>
                    </label>
                    <select name="status" required class="w-full px-3.5 py-2.5 text-sm font-semibold rounded-xl border border-slate-200 focus:outline-none focus:ring-2 focus:ring-accent/40 focus:border-accent bg-white">
                        <option value="active" {{ old('status', 'active') === 'active' ? 'selected' : '' }}>🟢 Active (Authorized to Sign In)</option>
                        <option value="inactive" {{ old('status') === 'inactive' ? 'selected' : '' }}>⚪ Inactive (Deactivated Account)</option>
                        <option value="suspended" {{ old('status') === 'suspended' ? 'selected' : '' }}>🔴 Suspended (Access Blocked)</option>
                    </select>
                </div>

                <!-- Job Title -->
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">
                        Job Title / Designation
                    </label>
                    <input type="text" 
                           name="job_title" 
                           value="{{ old('job_title') }}" 
                           placeholder="e.g. Flight Dispatch Manager" 
                           class="w-full px-3.5 py-2.5 text-sm rounded-xl border border-slate-200 focus:outline-none focus:ring-2 focus:ring-accent/40 focus:border-accent">
                </div>

                <!-- Department -->
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">
                        Department
                    </label>
                    <input type="text" 
                           name="department" 
                           value="{{ old('department') }}" 
                           placeholder="e.g. Flight Operations & Ground Crew" 
                           class="w-full px-3.5 py-2.5 text-sm rounded-xl border border-slate-200 focus:outline-none focus:ring-2 focus:ring-accent/40 focus:border-accent">
                </div>

                <!-- Initial Password -->
                <div class="md:col-span-2">
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">
                        Initial Account Password <span class="text-rose-500">*</span>
                    </label>
                    <input type="password" 
                           name="password" 
                           required 
                           placeholder="Minimum 8 characters with letters & numbers" 
                           class="w-full px-3.5 py-2.5 text-sm rounded-xl border border-slate-200 focus:outline-none focus:ring-2 focus:ring-accent/40 focus:border-accent">
                </div>
            </div>
        </div>

        <!-- CARD 2: Bio & Notes -->
        <div class="naoero-card p-6 bg-white border border-slate-200 space-y-4">
            <div class="flex items-center gap-2.5 border-b border-slate-100 pb-3">
                <div class="w-8 h-8 rounded-xl bg-navy-50 text-navy font-black text-xs flex items-center justify-center">2</div>
                <div>
                    <h2 class="text-sm font-extrabold text-navy uppercase tracking-wider">Biography & Personnel Notes</h2>
                    <p class="text-xs text-ink-muted">Internal staff description, operational role notes, or special authorizations.</p>
                </div>
            </div>

            <div>
                <textarea name="bio" 
                          rows="4" 
                          placeholder="Provide staff bio or security authorization remarks..." 
                          class="w-full px-3.5 py-2.5 text-sm rounded-xl border border-slate-200 focus:outline-none focus:ring-2 focus:ring-accent/40 focus:border-accent font-sans">{{ old('bio') }}</textarea>
            </div>
        </div>

        <!-- CARD 3: Profile Photo / Avatar -->
        <div class="naoero-card p-6 bg-white border border-slate-200 space-y-5">
            <div class="flex items-center gap-2.5 border-b border-slate-100 pb-3">
                <div class="w-8 h-8 rounded-xl bg-navy-50 text-navy font-black text-xs flex items-center justify-center">3</div>
                <div>
                    <h2 class="text-sm font-extrabold text-navy uppercase tracking-wider">Profile Photo / Avatar</h2>
                    <p class="text-xs text-ink-muted">Upload staff portrait picture.</p>
                </div>
            </div>

            <div class="flex flex-col sm:flex-row items-start sm:items-center gap-5">
                <div class="w-20 h-20 rounded-2xl bg-navy-50 border-2 border-slate-200 flex items-center justify-center text-navy font-bold text-2xl overflow-hidden shrink-0 shadow-sm">
                    <template x-if="avatarPreview">
                        <img :src="avatarPreview" alt="Avatar" class="w-full h-full object-cover">
                    </template>
                    <template x-if="!avatarPreview">
                        <span>✈️</span>
                    </template>
                </div>

                <div class="flex-1">
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">
                        Upload Avatar Photo
                    </label>
                    <input type="file" 
                           name="avatar" 
                           accept="image/*"
                           @change="handleAvatarUpload($event)"
                           class="w-full text-xs text-slate-500 file:mr-4 file:py-2.5 file:px-4 file:rounded-xl file:border-0 file:text-xs file:font-bold file:bg-navy-50 file:text-navy hover:file:bg-navy-100 cursor-pointer border border-slate-200 rounded-xl p-1 bg-slate-50/50">
                    <p class="text-[11px] text-slate-400 mt-1">JPEG, PNG, WebP up to 2MB.</p>
                </div>
            </div>
        </div>

        <!-- CARD 4: Security Roles -->
        <div class="naoero-card p-6 bg-white border border-slate-200 space-y-5">
            <div class="flex items-center gap-2.5 border-b border-slate-100 pb-3">
                <div class="w-8 h-8 rounded-xl bg-navy-50 text-navy font-black text-xs flex items-center justify-center">4</div>
                <div>
                    <h2 class="text-sm font-extrabold text-navy uppercase tracking-wider">Assign Security Roles</h2>
                    <p class="text-xs text-ink-muted">Select one or more administrative security roles assigned to this account.</p>
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3.5">
                @foreach($roles as $role)
                    <label class="flex items-start gap-3 p-3.5 rounded-xl border border-slate-200 hover:border-navy hover:bg-slate-50 cursor-pointer transition-all">
                        <input type="checkbox" 
                               name="roles[]" 
                               value="{{ $role->name }}" 
                               {{ in_array($role->name, old('roles', [])) ? 'checked' : '' }}
                               class="mt-1 w-4 h-4 rounded text-navy border-slate-300 focus:ring-accent">
                        <div>
                            <div class="flex items-center gap-2">
                                <span class="text-xs font-extrabold text-navy">{{ $role->name }}</span>
                                @if($role->name === 'Super Admin')
                                    <span class="text-[10px] px-1.5 py-0.5 rounded bg-amber-100 text-amber-800 font-black uppercase">Full Access</span>
                                @endif
                            </div>
                            <p class="text-[11px] text-slate-500 mt-0.5">{{ $role->permissions->count() }} permissions included</p>
                        </div>
                    </label>
                @endforeach
            </div>
        </div>

        <!-- CARD 5: Permissions for this account only -->
        @include('admin.users.partials.access-matrix', ['direct' => (array) old('permissions', []), 'blocked' => []])

        <!-- Bottom Action Bar -->
        <div class="flex items-center justify-between p-4 rounded-2xl bg-white border border-slate-200 shadow-sm">
            <a href="{{ route('admin.users.index') }}" class="px-5 py-2.5 rounded-xl border border-slate-200 text-slate-600 font-bold text-xs hover:bg-slate-50 transition-colors">
                Cancel
            </a>
            <button type="submit" class="px-6 py-2.5 rounded-xl bg-accent hover:bg-accent-hover text-navy font-extrabold text-xs shadow-md shadow-accent/20 hover:shadow-lg transition-all flex items-center gap-2">
                <svg class="w-4 h-4 text-navy" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
                </svg>
                <span>Save User Account</span>
            </button>
        </div>
    </form>

</div>
@endsection
