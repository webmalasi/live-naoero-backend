@extends('layouts.admin')

@section('title', 'Executive Account Profile')

@section('content')
<div class="max-w-5xl mx-auto space-y-6" x-data="{
    avatarPreview: '{{ $user->avatar_url }}',
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
        <div>
            <div class="flex items-center gap-2 text-xs font-semibold text-slate-500 mb-0.5">
                <a href="{{ route('admin.dashboard') }}" class="hover:text-navy transition-colors">Admin Portal</a>
                <span>/</span>
                <span class="text-navy font-bold">My Profile</span>
            </div>
            <h1 class="text-2xl font-extrabold text-navy tracking-tight">Executive Profile & Security</h1>
            <p class="text-xs text-ink-muted">Manage your personal credentials, contact details, and view your active system clearance privileges.</p>
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

    <!-- PROFILE HERO BANNER -->
    <div class="naoero-card p-6 bg-gradient-to-r from-navy via-navy-900 to-navy-950 text-white shadow-lg overflow-hidden">
        <div class="flex flex-col sm:flex-row items-start sm:items-center gap-6">
            <div class="w-20 h-20 rounded-2xl overflow-hidden border-2 border-accent shrink-0 shadow-md">
                <img :src="avatarPreview" alt="{{ $user->name }}" class="w-full h-full object-cover">
            </div>
            <div class="space-y-1 flex-1">
                <div class="flex items-center gap-3 flex-wrap">
                    <h2 class="text-xl font-extrabold text-white">{{ $user->name }}</h2>
                    <span class="px-3 py-0.5 rounded-full text-[11px] font-black uppercase tracking-wider bg-accent text-navy">
                        {{ $user->primary_role }}
                    </span>
                </div>
                <p class="text-xs text-white/80 font-medium">{{ $user->job_title ?? 'Executive Staff' }} &bull; <strong class="text-accent">{{ $user->department ?? 'Flight Dispatch & Ground Operations' }}</strong></p>
                <p class="text-xs text-white/60 font-mono">{{ $user->email }}</p>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">

        <!-- LEFT COLUMN: Profile Details Form -->
        <div class="lg:col-span-7 space-y-6">
            <div class="naoero-card p-6 bg-white border border-slate-200 space-y-5">
                <div class="flex items-center gap-2.5 border-b border-slate-100 pb-3">
                    <div class="w-8 h-8 rounded-xl bg-navy-50 text-navy font-black text-xs flex items-center justify-center">1</div>
                    <div>
                        <h2 class="text-sm font-extrabold text-navy uppercase tracking-wider">Personal Information & Bio</h2>
                        <p class="text-xs text-ink-muted">Update your display name, contact numbers, and profile photo.</p>
                    </div>
                </div>

                <form method="POST" action="{{ route('admin.profile.update') }}" enctype="multipart/form-data" class="space-y-5">
                    @csrf
                    @method('PUT')

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div class="sm:col-span-2">
                            <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1.5">
                                Full Name <span class="text-rose-500">*</span>
                            </label>
                            <input type="text" name="name" value="{{ old('name', $user->name) }}" required class="w-full px-3.5 py-2.5 text-sm rounded-xl border border-slate-200 focus:outline-none focus:ring-2 focus:ring-accent/40 focus:border-accent font-semibold">
                        </div>

                        <div class="sm:col-span-2">
                            <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1.5">
                                Official Email Address <span class="text-rose-500">*</span>
                            </label>
                            <input type="email" name="email" value="{{ old('email', $user->email) }}" required class="w-full px-3.5 py-2.5 text-sm rounded-xl border border-slate-200 focus:outline-none focus:ring-2 focus:ring-accent/40 focus:border-accent">
                        </div>

                        <div>
                            <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1.5">
                                Phone Number
                            </label>
                            <input type="text" name="phone" value="{{ old('phone', $user->phone) }}" class="w-full px-3.5 py-2.5 text-sm rounded-xl border border-slate-200 focus:outline-none focus:ring-2 focus:ring-accent/40 focus:border-accent">
                        </div>

                        <div>
                            <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1.5">
                                Job Title
                            </label>
                            <input type="text" name="job_title" value="{{ old('job_title', $user->job_title) }}" class="w-full px-3.5 py-2.5 text-sm rounded-xl border border-slate-200 focus:outline-none focus:ring-2 focus:ring-accent/40 focus:border-accent">
                        </div>

                        <div class="sm:col-span-2">
                            <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1.5">
                                Department
                            </label>
                            <input type="text" name="department" value="{{ old('department', $user->department) }}" class="w-full px-3.5 py-2.5 text-sm rounded-xl border border-slate-200 focus:outline-none focus:ring-2 focus:ring-accent/40 focus:border-accent">
                        </div>

                        <div class="sm:col-span-2">
                            <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1.5">
                                Upload New Avatar Photo
                            </label>
                            <input type="file" 
                                   name="avatar" 
                                   accept="image/*" 
                                   @change="handleAvatarUpload($event)"
                                   class="w-full text-xs text-slate-500 file:mr-4 file:py-2.5 file:px-4 file:rounded-xl file:border-0 file:text-xs file:font-bold file:bg-navy-50 file:text-navy hover:file:bg-navy-100 cursor-pointer border border-slate-200 rounded-xl p-1 bg-slate-50/50">
                        </div>

                        <div class="sm:col-span-2">
                            <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1.5">
                                Biography & Remarks
                            </label>
                            <textarea name="bio" rows="4" class="w-full px-3.5 py-2.5 text-sm rounded-xl border border-slate-200 focus:outline-none focus:ring-2 focus:ring-accent/40 focus:border-accent font-sans">{{ old('bio', $user->bio) }}</textarea>
                        </div>
                    </div>

                    <div class="pt-3 border-t border-slate-100 flex justify-end">
                        <button type="submit" class="px-6 py-2.5 rounded-xl bg-accent hover:bg-accent-hover text-navy font-extrabold text-xs shadow-md shadow-accent/20 hover:shadow-lg transition-all flex items-center gap-2">
                            <svg class="w-4 h-4 text-navy" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
                            </svg>
                            <span>Save Profile Details</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- RIGHT COLUMN: Security & Clearances -->
        <div class="lg:col-span-5 space-y-6">
            
            <!-- Update Password Card -->
            <div class="naoero-card p-6 bg-white border border-slate-200 space-y-4">
                <div class="flex items-center gap-2.5 border-b border-slate-100 pb-3">
                    <div class="w-8 h-8 rounded-xl bg-navy-50 text-navy font-black text-xs flex items-center justify-center">2</div>
                    <div>
                        <h2 class="text-sm font-extrabold text-navy uppercase tracking-wider">Security & Password</h2>
                        <p class="text-xs text-ink-muted">Change your current account sign-in password.</p>
                    </div>
                </div>

                <form method="POST" action="{{ route('admin.profile.password') }}" class="space-y-4">
                    @csrf
                    @method('PUT')

                    <div>
                        <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1.5">Current Password</label>
                        <input type="password" name="current_password" required placeholder="••••••••" class="w-full px-3.5 py-2.5 text-sm rounded-xl border border-slate-200 focus:outline-none focus:ring-2 focus:ring-accent/40 focus:border-accent">
                    </div>

                    <div>
                        <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1.5">New Password</label>
                        <input type="password" name="password" required placeholder="Minimum 8 characters" class="w-full px-3.5 py-2.5 text-sm rounded-xl border border-slate-200 focus:outline-none focus:ring-2 focus:ring-accent/40 focus:border-accent">
                    </div>

                    <div>
                        <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1.5">Confirm New Password</label>
                        <input type="password" name="password_confirmation" required placeholder="Re-enter new password" class="w-full px-3.5 py-2.5 text-sm rounded-xl border border-slate-200 focus:outline-none focus:ring-2 focus:ring-accent/40 focus:border-accent">
                    </div>

                    <button type="submit" class="w-full py-2.5 rounded-xl bg-navy hover:bg-navy-700 text-white font-extrabold text-xs shadow-md shadow-navy/20 transition-all flex items-center justify-center gap-2">
                        <svg class="w-4 h-4 text-accent" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                        </svg>
                        <span>Update Password</span>
                    </button>
                </form>
            </div>

            <!-- Assigned Roles & Clearances -->
            <div class="naoero-card p-6 bg-white border border-slate-200 space-y-3">
                <div class="flex items-center gap-2.5 border-b border-slate-100 pb-2.5">
                    <div class="w-8 h-8 rounded-xl bg-navy-50 text-navy font-black text-xs flex items-center justify-center">3</div>
                    <div>
                        <h2 class="text-sm font-extrabold text-navy uppercase tracking-wider">Assigned Clearances</h2>
                        <p class="text-xs text-ink-muted">Security privileges associated with this account.</p>
                    </div>
                </div>
                <div class="flex flex-wrap gap-2 pt-1">
                    @foreach($user->roles as $role)
                        <span class="px-3 py-1.5 rounded-xl text-xs font-extrabold bg-navy-50 text-navy border border-navy-100 flex items-center gap-1.5">
                            <svg class="w-3.5 h-3.5 text-accent" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M2.166 4.999A11.954 11.954 0 0010 1.944 11.954 11.954 0 0017.834 5c.11.65.166 1.32.166 2.001 0 5.225-3.34 9.67-8 11.317C5.34 16.67 2 12.225 2 7c0-.682.057-1.35.166-2.001zm11.541 3.708a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                            </svg>
                            <span>{{ $role->name }}</span>
                        </span>
                    @endforeach
                </div>
            </div>

        </div>

    </div>

</div>
@endsection
