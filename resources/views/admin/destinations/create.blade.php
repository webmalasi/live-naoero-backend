@extends('layouts.admin')

@section('title', 'Add Destination')

@section('content')
<div class="max-w-5xl mx-auto space-y-6" x-data="destinationForm()">

    <!-- Header -->
    <div class="flex items-center justify-between">
        <div class="flex items-center gap-3">
            <a href="{{ route('admin.destinations.index') }}" 
               class="p-2 rounded-xl bg-white border border-slate-200 text-slate-600 hover:text-navy hover:bg-slate-50 transition-colors">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                </svg>
            </a>
            <div>
                <h1 class="text-2xl font-extrabold text-navy">Add New Destination</h1>
                <p class="text-sm text-ink-muted mt-0.5">Create a flight destination with media, explore cards, why visit highlights, activities, and stays.</p>
            </div>
        </div>
    </div>

    @if ($errors->any())
        <div class="p-4 rounded-xl bg-rose-50 border border-rose-200 text-rose-700 text-sm">
            <p class="font-bold mb-1">Please correct the following errors:</p>
            <ul class="list-disc list-inside space-y-0.5">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form id="destination-create-form" method="POST" action="{{ route('admin.destinations.store') }}" enctype="multipart/form-data" @submit="prepareJson($event)">
        @csrf

        <input type="hidden" name="why_visit_json" id="why_visit_json_input" x-model="whyVisitJson">
        <input type="hidden" name="things_to_do_json" id="things_to_do_json_input" x-model="thingsToDoJson">
        <input type="hidden" name="stays_json" id="stays_json_input" x-model="staysJson">

        <div class="space-y-6">

            <!-- Section 1: Basic Information -->
            <div class="naoero-card p-6 bg-white border border-slate-200 space-y-5">
                <div class="border-b border-slate-100 pb-3">
                    <h2 class="text-lg font-bold text-navy">1. Basic Information</h2>
                    <p class="text-xs text-slate-500">Core destination name, country, slug, and display settings.</p>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-5">
                    <!-- Name -->
                    <div>
                        <label class="block text-xs font-extrabold uppercase tracking-wider text-slate-700 mb-1.5">
                            Destination Name <span class="text-rose-500">*</span>
                        </label>
                        <input type="text" 
                               name="name" 
                               x-model="name" 
                               @input="updateSlug()"
                               value="{{ old('name') }}" 
                               required
                               placeholder="e.g. Brisbane"
                               class="w-full px-3.5 py-2.5 text-sm rounded-xl border border-slate-200 focus:outline-none focus:ring-2 focus:ring-accent/40 focus:border-accent">
                    </div>

                    <!-- Slug -->
                    <div>
                        <label class="block text-xs font-extrabold uppercase tracking-wider text-slate-700 mb-1.5">
                            URL Slug
                        </label>
                        <input type="text" 
                               name="slug" 
                               x-model="slug" 
                               value="{{ old('slug') }}" 
                               placeholder="e.g. brisbane"
                               class="w-full px-3.5 py-2.5 text-sm font-mono rounded-xl border border-slate-200 focus:outline-none focus:ring-2 focus:ring-accent/40 focus:border-accent">
                        <p class="text-[11px] text-slate-400 mt-1">Route: /destinations/<span x-text="slug || 'slug'"></span></p>
                    </div>

                    <!-- Country -->
                    <div>
                        <label class="block text-xs font-extrabold uppercase tracking-wider text-slate-700 mb-1.5">
                            Country / Territory <span class="text-rose-500">*</span>
                        </label>
                        <input type="text" 
                               name="country" 
                               value="{{ old('country') }}" 
                               required
                               placeholder="e.g. Australia"
                               class="w-full px-3.5 py-2.5 text-sm rounded-xl border border-slate-200 focus:outline-none focus:ring-2 focus:ring-accent/40 focus:border-accent">
                    </div>

                    <!-- Flag Emoji -->
                    <div>
                        <label class="block text-xs font-extrabold uppercase tracking-wider text-slate-700 mb-1.5">
                            Flag Emoji
                        </label>
                        <input type="text" 
                               name="flag" 
                               value="{{ old('flag') }}" 
                               placeholder="e.g. 🇦🇺 or 🇳🇷"
                               class="w-full px-3.5 py-2.5 text-sm rounded-xl border border-slate-200 focus:outline-none focus:ring-2 focus:ring-accent/40 focus:border-accent">
                    </div>

                    <!-- Sort Order -->
                    <div>
                        <label class="block text-xs font-extrabold uppercase tracking-wider text-slate-700 mb-1.5">
                            Display Sort Order
                        </label>
                        <input type="number" 
                               name="sort_order" 
                               value="{{ old('sort_order', $nextOrder) }}" 
                               class="w-full px-3.5 py-2.5 text-sm rounded-xl border border-slate-200 focus:outline-none focus:ring-2 focus:ring-accent/40 focus:border-accent">
                    </div>

                    <!-- Status Flags -->
                    <div class="flex flex-col justify-end space-y-2 pt-2">
                        <label class="inline-flex items-center gap-2 cursor-pointer">
                            <input type="checkbox" name="is_featured" value="1" {{ old('is_featured', true) ? 'checked' : '' }} class="w-4 h-4 rounded text-accent focus:ring-accent border-slate-300">
                            <span class="text-sm font-semibold text-slate-700">Show in Explore Destinations (Home)</span>
                        </label>
                        <label class="inline-flex items-center gap-2 cursor-pointer">
                            <input type="checkbox" name="is_active" value="1" {{ old('is_active', true) ? 'checked' : '' }} class="w-4 h-4 rounded text-accent focus:ring-accent border-slate-300">
                            <span class="text-sm font-semibold text-slate-700">Published / Active</span>
                        </label>
                    </div>
                </div>
            </div>

            <!-- Section 2: Media & Images -->
            <div class="naoero-card p-6 bg-white border border-slate-200 space-y-5">
                <div class="border-b border-slate-100 pb-3">
                    <h2 class="text-lg font-bold text-navy">2. Media & Images</h2>
                    <p class="text-xs text-slate-500">Thumbnail for cards and Banner image for destination detail page.</p>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- Thumbnail -->
                    <div class="space-y-3">
                        <label class="block text-xs font-extrabold uppercase tracking-wider text-slate-700">
                            Card Thumbnail (Explore Destinations)
                        </label>
                        <div class="p-3 bg-slate-50 rounded-xl border border-slate-200 space-y-2">
                            <div>
                                <label class="text-xs text-slate-600 font-semibold">Upload Image:</label>
                                <input type="file" name="thumbnail_file" accept="image/*" class="block w-full text-xs text-slate-500 mt-1 file:mr-3 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-navy file:text-white hover:file:bg-navy-700">
                            </div>
                            <div class="text-center text-xs text-slate-400 font-bold">— OR —</div>
                            <div>
                                <label class="text-xs text-slate-600 font-semibold">Image URL:</label>
                                <input type="text" name="thumbnail_url" value="{{ old('thumbnail_url') }}" placeholder="https://..." class="w-full mt-1 px-3 py-1.5 text-xs rounded-lg border border-slate-200 focus:outline-none focus:border-accent">
                            </div>
                        </div>
                    </div>

                    <!-- Banner Image -->
                    <div class="space-y-3">
                        <label class="block text-xs font-extrabold uppercase tracking-wider text-slate-700">
                            Hero Banner Image (Detail Page)
                        </label>
                        <div class="p-3 bg-slate-50 rounded-xl border border-slate-200 space-y-2">
                            <div>
                                <label class="text-xs text-slate-600 font-semibold">Upload Banner:</label>
                                <input type="file" name="banner_file" accept="image/*" class="block w-full text-xs text-slate-500 mt-1 file:mr-3 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-navy file:text-white hover:file:bg-navy-700">
                            </div>
                            <div class="text-center text-xs text-slate-400 font-bold">— OR —</div>
                            <div>
                                <label class="text-xs text-slate-600 font-semibold">Banner Image URL:</label>
                                <input type="text" name="banner_url" value="{{ old('banner_url') }}" placeholder="https://..." class="w-full mt-1 px-3 py-1.5 text-xs rounded-lg border border-slate-200 focus:outline-none focus:border-accent">
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Section 3: Overview & Content -->
            <div class="naoero-card p-6 bg-white border border-slate-200 space-y-5">
                <div class="border-b border-slate-100 pb-3">
                    <h2 class="text-lg font-bold text-navy">3. Overview & Hero Text</h2>
                    <p class="text-xs text-slate-500">Descriptions used on cards, hero banners, and intro sections.</p>
                </div>

                <div class="space-y-4">
                    <!-- Short Description -->
                    <div>
                        <label class="block text-xs font-extrabold uppercase tracking-wider text-slate-700 mb-1.5">
                            Short Description (Home Explore Card)
                        </label>
                        <textarea name="short_description" rows="2" placeholder="Discover creative spaces, music and restaurants in Queensland's capital city" class="w-full px-3.5 py-2.5 text-sm rounded-xl border border-slate-200 focus:outline-none focus:ring-2 focus:ring-accent/40 focus:border-accent">{{ old('short_description') }}</textarea>
                    </div>

                    <!-- Discover Heading -->
                    <div>
                        <label class="block text-xs font-extrabold uppercase tracking-wider text-slate-700 mb-1.5">
                            Discover Heading (Detail Page)
                        </label>
                        <input type="text" name="discover_heading" value="{{ old('discover_heading') }}" placeholder="e.g. Discover Brisbane – Your Gateway to Queensland" class="w-full px-3.5 py-2.5 text-sm rounded-xl border border-slate-200 focus:outline-none focus:ring-2 focus:ring-accent/40 focus:border-accent">
                    </div>

                    <!-- Intro Text -->
                    <div>
                        <label class="block text-xs font-extrabold uppercase tracking-wider text-slate-700 mb-1.5">
                            Intro Paragraph (Detail Page - Rich Editor)
                        </label>
                        <textarea name="intro" rows="4" placeholder="Nestled on the east coast of Australia..." class="wysiwyg-editor w-full px-3.5 py-2.5 text-sm rounded-xl border border-slate-200 focus:outline-none focus:ring-2 focus:ring-accent/40 focus:border-accent">{{ old('intro') }}</textarea>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <!-- Banner Text -->
                        <div>
                            <label class="block text-xs font-extrabold uppercase tracking-wider text-slate-700 mb-1.5">
                                Banner Floating Box Text
                            </label>
                            <textarea name="banner_text" rows="2" placeholder="With an incredible restaurant scene..." class="w-full px-3.5 py-2.5 text-sm rounded-xl border border-slate-200 focus:outline-none focus:ring-2 focus:ring-accent/40 focus:border-accent">{{ old('banner_text') }}</textarea>
                        </div>

                        <!-- Quote -->
                        <div>
                            <label class="block text-xs font-extrabold uppercase tracking-wider text-slate-700 mb-1.5">
                                Highlight Quote Box
                            </label>
                            <textarea name="quote" rows="2" placeholder="If you're after endless blue skies, golden beaches..." class="w-full px-3.5 py-2.5 text-sm rounded-xl border border-slate-200 focus:outline-none focus:ring-2 focus:ring-accent/40 focus:border-accent">{{ old('quote') }}</textarea>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Section 4: Why Visit (Cards / Highlights with Image Upload) -->
            <div class="naoero-card p-6 bg-white border border-slate-200 space-y-4">
                <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                    <div>
                        <h2 class="text-lg font-bold text-navy">4. Why Visit Highlights (Cards with Image & Description)</h2>
                        <p class="text-xs text-slate-500">Key highlight cards displayed on the destination detail page with image photo, title, and description.</p>
                    </div>
                    <button type="button" @click="addWhyVisit()" class="px-3.5 py-2 text-xs font-bold rounded-xl bg-navy text-white hover:bg-navy-700 transition-colors shadow-sm flex items-center gap-1.5">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                        <span>Add Highlight</span>
                    </button>
                </div>

                <div class="space-y-4">
                    <template x-for="(item, index) in whyVisit" :key="item._id">
                        <div class="p-5 rounded-2xl border border-slate-200 bg-slate-50/70 space-y-4 shadow-sm hover:border-slate-300 transition-all">
                            <div class="flex items-center justify-between border-b border-slate-200/60 pb-2.5">
                                <div class="flex items-center gap-2">
                                    <span class="w-6 h-6 rounded-full bg-navy text-white text-xs font-black flex items-center justify-center" x-text="index + 1"></span>
                                    <span class="text-xs font-black uppercase text-navy" x-text="item.title ? item.title : 'Highlight #' + (index + 1)"></span>
                                </div>
                                <div class="flex items-center gap-2">
                                    <button type="button" @click="moveWhyVisit(index, index - 1)" :disabled="index === 0" class="p-1 rounded-lg text-slate-400 hover:text-navy disabled:opacity-30" title="Move Up">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7"/></svg>
                                    </button>
                                    <button type="button" @click="moveWhyVisit(index, index + 1)" :disabled="index === whyVisit.length - 1" class="p-1 rounded-lg text-slate-400 hover:text-navy disabled:opacity-30" title="Move Down">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                                    </button>
                                    <button type="button" @click="removeWhyVisit(index)" class="px-2.5 py-1 text-xs font-bold text-rose-600 hover:bg-rose-100 rounded-lg transition-colors">
                                        Remove
                                    </button>
                                </div>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-12 gap-5 items-start">
                                <!-- Image Upload & Preview (4 cols) -->
                                <div class="md:col-span-4 space-y-2">
                                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-700">
                                        Card Image / Photo
                                    </label>

                                    <!-- Live Image Preview Box -->
                                    <div class="relative w-full h-36 rounded-xl overflow-hidden bg-slate-200 border border-slate-300 flex items-center justify-center group">
                                        <template x-if="item.image">
                                            <img :src="getImageUrl(item.image)" alt="Highlight Image" class="w-full h-full object-cover">
                                        </template>
                                        <template x-if="!item.image">
                                            <div class="flex flex-col items-center justify-center text-slate-400 p-2 text-center">
                                                <svg class="w-8 h-8 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                                <span class="text-[11px] font-semibold mt-1">No Image Set</span>
                                            </div>
                                        </template>

                                        <!-- Uploading Overlay -->
                                        <div x-show="item.is_uploading" class="absolute inset-0 bg-navy/80 flex flex-col items-center justify-center text-white text-xs font-bold space-y-1">
                                            <svg class="animate-spin h-5 w-5 text-accent" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                                            <span>Uploading...</span>
                                        </div>

                                        <!-- Remove Image Button -->
                                        <button type="button" x-show="item.image && !item.is_uploading" @click="item.image = ''" class="absolute top-1.5 right-1.5 p-1 bg-rose-600 text-white rounded-lg opacity-0 group-hover:opacity-100 transition-opacity" title="Remove Photo">
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                        </button>
                                    </div>

                                    <!-- Upload File Button -->
                                    <div class="space-y-1.5">
                                        <label class="block w-full text-center px-3 py-2 text-xs font-bold rounded-xl bg-white border border-slate-300 text-slate-700 hover:bg-slate-50 cursor-pointer shadow-sm transition-all">
                                            <span>📷 Upload Photo</span>
                                            <input type="file" accept="image/*" class="hidden" @change="uploadWhyVisitImage(index, $event)">
                                        </label>
                                        <input type="text" x-model="item.image" placeholder="Or paste image URL (https://...)" class="w-full px-2.5 py-1.5 text-xs rounded-lg border border-slate-200 bg-white placeholder-slate-400">
                                    </div>
                                </div>

                                <!-- Text Content (8 cols) -->
                                <div class="md:col-span-8 space-y-3">
                                    <div>
                                        <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1">
                                            Highlight Title <span class="text-rose-500">*</span>
                                        </label>
                                        <input type="text" x-model="item.title" placeholder="e.g. Stunning Weather, Nature & Outdoor Adventures" class="w-full px-3.5 py-2 text-sm rounded-xl border border-slate-200 bg-white focus:outline-none focus:ring-2 focus:ring-accent/40">
                                    </div>
                                    <div>
                                        <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1">
                                            Highlight Description
                                        </label>
                                        <textarea x-model="item.description" rows="3" placeholder="Detailed description of why visitors love this..." class="w-full px-3.5 py-2 text-sm rounded-xl border border-slate-200 bg-white focus:outline-none focus:ring-2 focus:ring-accent/40"></textarea>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </template>
                </div>
            </div>

            <!-- Section 5: Things To Do (with 2-Image Showcase per Activity) -->
            <div class="naoero-card p-6 bg-white border border-slate-200 space-y-4">
                <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                    <div>
                        <h2 class="text-lg font-bold text-navy">5. Things To Do In Destination (Showcase with Images)</h2>
                        <p class="text-xs text-slate-500">Activities and attractions. The frontend displays 2 side-by-side showcase photos per activity.</p>
                    </div>
                    <button type="button" @click="addThingsToDo()" class="px-3.5 py-2 text-xs font-bold rounded-xl bg-navy text-white hover:bg-navy-700 transition-colors shadow-sm flex items-center gap-1.5">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                        <span>Add Activity</span>
                    </button>
                </div>

                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1.5">
                        Things To Do Section Intro Text
                    </label>
                    <input type="text" name="things_to_do_intro" value="{{ old('things_to_do_intro') }}" placeholder="e.g. Discover endless activities and iconic sights around Brisbane." class="w-full px-3.5 py-2.5 text-sm rounded-xl border border-slate-200 focus:outline-none focus:ring-2 focus:ring-accent/40">
                </div>

                <div class="space-y-5">
                    <template x-for="(act, actIndex) in thingsToDo" :key="act._id">
                        <div class="p-5 rounded-2xl border border-slate-200 bg-slate-50/70 space-y-4 shadow-sm hover:border-slate-300 transition-all">
                            <div class="flex items-center justify-between border-b border-slate-200/60 pb-2.5">
                                <div class="flex items-center gap-2">
                                    <span class="w-6 h-6 rounded-full bg-accent text-navy text-xs font-black flex items-center justify-center" x-text="actIndex + 1"></span>
                                    <span class="text-xs font-black uppercase text-navy" x-text="act.title ? act.title : 'Activity #' + (actIndex + 1)"></span>
                                </div>
                                <div class="flex items-center gap-2">
                                    <button type="button" @click="moveThingsToDo(actIndex, actIndex - 1)" :disabled="actIndex === 0" class="p-1 rounded-lg text-slate-400 hover:text-navy disabled:opacity-30" title="Move Up">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7"/></svg>
                                    </button>
                                    <button type="button" @click="moveThingsToDo(actIndex, actIndex + 1)" :disabled="actIndex === thingsToDo.length - 1" class="p-1 rounded-lg text-slate-400 hover:text-navy disabled:opacity-30" title="Move Down">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                                    </button>
                                    <button type="button" @click="removeThingsToDo(actIndex)" class="px-2.5 py-1 text-xs font-bold text-rose-600 hover:bg-rose-100 rounded-lg transition-colors">
                                        Remove Activity
                                    </button>
                                </div>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1">
                                        Activity / Sight Title <span class="text-rose-500">*</span>
                                    </label>
                                    <input type="text" x-model="act.title" placeholder="e.g. Southbank, Stunning beaches, Deep-Sea Fishing" class="w-full px-3.5 py-2 text-sm rounded-xl border border-slate-200 bg-white focus:outline-none focus:ring-2 focus:ring-accent/40">
                                </div>
                                <div>
                                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1">
                                        Activity Description
                                    </label>
                                    <textarea x-model="act.description" rows="2" placeholder="Describe the activity, highlights, or tips..." class="w-full px-3.5 py-2 text-sm rounded-xl border border-slate-200 bg-white focus:outline-none focus:ring-2 focus:ring-accent/40"></textarea>
                                </div>
                            </div>

                            <!-- Showcase Images Section (2 images for frontend layout) -->
                            <div class="pt-2 border-t border-slate-200/60 space-y-2.5">
                                <div class="flex items-center justify-between">
                                    <p class="text-xs font-extrabold uppercase tracking-wider text-navy">
                                        Activity Showcase Photos <span class="text-[11px] font-normal text-slate-500 normal-case">(Frontend layout renders 2 side-by-side images)</span>
                                    </p>
                                    <button type="button" @click="addActivityImageSlot(actIndex)" class="text-xs font-bold text-navy hover:underline">
                                        + Add Another Photo
                                    </button>
                                </div>

                                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-2 gap-4">
                                    <template x-for="(imgUrl, imgIndex) in act.images" :key="imgIndex">
                                        <div class="p-3 bg-white rounded-xl border border-slate-200 space-y-2.5 shadow-sm">
                                            <div class="flex items-center justify-between text-xs font-bold text-slate-600">
                                                <span x-text="'Photo #' + (imgIndex + 1)"></span>
                                                <button type="button" @click="removeActivityImage(actIndex, imgIndex)" class="text-rose-500 hover:text-rose-700 text-[11px] font-bold">Remove</button>
                                            </div>

                                            <!-- Preview Box -->
                                            <div class="relative w-full h-32 rounded-lg overflow-hidden bg-slate-100 border border-slate-200 flex items-center justify-center group">
                                                <template x-if="imgUrl">
                                                    <img :src="getImageUrl(imgUrl)" alt="Activity Image" class="w-full h-full object-cover">
                                                </template>
                                                <template x-if="!imgUrl">
                                                    <div class="text-center text-slate-400 p-2">
                                                        <svg class="w-6 h-6 mx-auto text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                                        <span class="text-[10px] font-semibold mt-0.5 block">No Photo</span>
                                                    </div>
                                                </template>

                                                <!-- Uploading Spinner -->
                                                <div x-show="act['is_uploading_' + imgIndex]" class="absolute inset-0 bg-navy/80 flex flex-col items-center justify-center text-white text-xs font-bold space-y-1">
                                                    <svg class="animate-spin h-5 w-5 text-accent" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                                                    <span>Uploading...</span>
                                                </div>
                                            </div>

                                            <!-- Upload Actions -->
                                            <div class="space-y-1.5">
                                                <label class="block w-full text-center px-2.5 py-1.5 text-xs font-bold rounded-lg bg-slate-50 border border-slate-200 text-slate-700 hover:bg-slate-100 cursor-pointer transition-all">
                                                    <span>📷 Upload File</span>
                                                    <input type="file" accept="image/*" class="hidden" @change="uploadActivityImage(actIndex, imgIndex, $event)">
                                                </label>
                                                <input type="text" x-model="act.images[imgIndex]" placeholder="Or paste image URL" class="w-full px-2.5 py-1 text-xs rounded-lg border border-slate-200 bg-white placeholder-slate-400">
                                            </div>
                                        </div>
                                    </template>
                                </div>
                            </div>
                        </div>
                    </template>
                </div>
            </div>

            <!-- Section 6: Where To Stay (Hotels & Resorts with Image Upload) -->
            <div class="naoero-card p-6 bg-white border border-slate-200 space-y-4">
                <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                    <div>
                        <h2 class="text-lg font-bold text-navy">6. Where To Stay (Hotels & Accommodations with Image Upload)</h2>
                        <p class="text-xs text-slate-500">Accommodation suggestions for travelers with hotel photos, name, and amenities.</p>
                    </div>
                    <button type="button" @click="addStay()" class="px-3.5 py-2 text-xs font-bold rounded-xl bg-navy text-white hover:bg-navy-700 transition-colors shadow-sm flex items-center gap-1.5">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                        <span>Add Hotel / Stay</span>
                    </button>
                </div>

                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1.5">
                        Where To Stay Section Intro Text
                    </label>
                    <input type="text" name="stays_intro" value="{{ old('stays_intro') }}" placeholder="e.g. Whether you're after a central, boutique hotel or somewhere close to shopping..." class="w-full px-3.5 py-2.5 text-sm rounded-xl border border-slate-200 focus:outline-none focus:ring-2 focus:ring-accent/40">
                </div>

                <div class="space-y-4">
                    <template x-for="(stay, stayIndex) in stays" :key="stay._id">
                        <div class="p-5 rounded-2xl border border-slate-200 bg-slate-50/70 space-y-4 shadow-sm hover:border-slate-300 transition-all">
                            <div class="flex items-center justify-between border-b border-slate-200/60 pb-2.5">
                                <div class="flex items-center gap-2">
                                    <span class="w-6 h-6 rounded-full bg-navy text-white text-xs font-black flex items-center justify-center" x-text="stayIndex + 1"></span>
                                    <span class="text-xs font-black uppercase text-navy" x-text="stay.title ? stay.title : 'Hotel / Stay #' + (stayIndex + 1)"></span>
                                </div>
                                <div class="flex items-center gap-2">
                                    <button type="button" @click="moveStay(stayIndex, stayIndex - 1)" :disabled="stayIndex === 0" class="p-1 rounded-lg text-slate-400 hover:text-navy disabled:opacity-30" title="Move Up">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7"/></svg>
                                    </button>
                                    <button type="button" @click="moveStay(stayIndex, stayIndex + 1)" :disabled="stayIndex === stays.length - 1" class="p-1 rounded-lg text-slate-400 hover:text-navy disabled:opacity-30" title="Move Down">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                                    </button>
                                    <button type="button" @click="removeStay(stayIndex)" class="px-2.5 py-1 text-xs font-bold text-rose-600 hover:bg-rose-100 rounded-lg transition-colors">
                                        Remove Hotel
                                    </button>
                                </div>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-12 gap-5 items-start">
                                <!-- Hotel Image Upload & Preview (4 cols) -->
                                <div class="md:col-span-4 space-y-2">
                                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-700">
                                        Hotel Photo / Image
                                    </label>

                                    <!-- Live Preview Box -->
                                    <div class="relative w-full h-36 rounded-xl overflow-hidden bg-slate-200 border border-slate-300 flex items-center justify-center group">
                                        <template x-if="stay.image">
                                            <img :src="getImageUrl(stay.image)" alt="Hotel Image" class="w-full h-full object-cover">
                                        </template>
                                        <template x-if="!stay.image">
                                            <div class="flex flex-col items-center justify-center text-slate-400 p-2 text-center">
                                                <svg class="w-8 h-8 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                                                <span class="text-[11px] font-semibold mt-1">No Hotel Image</span>
                                            </div>
                                        </template>

                                        <!-- Uploading Overlay -->
                                        <div x-show="stay.is_uploading" class="absolute inset-0 bg-navy/80 flex flex-col items-center justify-center text-white text-xs font-bold space-y-1">
                                            <svg class="animate-spin h-5 w-5 text-accent" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                                            <span>Uploading...</span>
                                        </div>

                                        <!-- Remove Button -->
                                        <button type="button" x-show="stay.image && !stay.is_uploading" @click="stay.image = ''" class="absolute top-1.5 right-1.5 p-1 bg-rose-600 text-white rounded-lg opacity-0 group-hover:opacity-100 transition-opacity" title="Remove Photo">
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                        </button>
                                    </div>

                                    <!-- Upload File Button -->
                                    <div class="space-y-1.5">
                                        <label class="block w-full text-center px-3 py-2 text-xs font-bold rounded-xl bg-white border border-slate-300 text-slate-700 hover:bg-slate-50 cursor-pointer shadow-sm transition-all">
                                            <span>📷 Upload Hotel Photo</span>
                                            <input type="file" accept="image/*" class="hidden" @change="uploadStayImage(stayIndex, $event)">
                                        </label>
                                        <input type="text" x-model="stay.image" placeholder="Or paste image URL (https://...)" class="w-full px-2.5 py-1.5 text-xs rounded-lg border border-slate-200 bg-white placeholder-slate-400">
                                    </div>
                                </div>

                                <!-- Text Content (8 cols) -->
                                <div class="md:col-span-8 space-y-3">
                                    <div>
                                        <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1">
                                            Hotel / Resort Name <span class="text-rose-500">*</span>
                                        </label>
                                        <input type="text" x-model="stay.title" placeholder="e.g. Gambaro Hotel, Quest Chermside" class="w-full px-3.5 py-2 text-sm rounded-xl border border-slate-200 bg-white focus:outline-none focus:ring-2 focus:ring-accent/40">
                                    </div>
                                    <div>
                                        <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1">
                                            Hotel Description & Amenities
                                        </label>
                                        <textarea x-model="stay.description" rows="3" placeholder="Location, rooms, swimming pool, restaurant amenities..." class="w-full px-3.5 py-2 text-sm rounded-xl border border-slate-200 bg-white focus:outline-none focus:ring-2 focus:ring-accent/40"></textarea>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </template>
                </div>
            </div>

            <!-- Submit Buttons -->
            <div class="flex items-center justify-end gap-3 pt-4">
                <a href="{{ route('admin.destinations.index') }}" class="px-5 py-2.5 rounded-xl bg-slate-100 text-slate-700 font-bold text-sm hover:bg-slate-200 transition-colors">
                    Cancel
                </a>
                <button type="submit" class="px-6 py-2.5 rounded-xl bg-accent hover:bg-accent-hover text-navy font-bold text-sm shadow-md shadow-accent/20 transition-all flex items-center gap-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                    <span>Create Destination</span>
                </button>
            </div>

        </div>
    </form>

</div>

<script>
function destinationForm() {
    function generateId(prefix, idx) {
        return prefix + '_' + Date.now() + '_' + idx + '_' + Math.random().toString(36).substring(2, 7);
    }

    return {
        name: '',
        slug: '',
        whyVisit: [
            { _id: generateId('wv', 0), title: 'Stunning Weather', description: 'Enjoy endless blue skies and warm tropical climate year-round.', image: '', is_uploading: false },
            { _id: generateId('wv', 1), title: 'Nature & Outdoor Adventures', description: 'Explore scenic rainforests, lagoons, and hiking spots.', image: '', is_uploading: false }
        ],
        thingsToDo: [
            { _id: generateId('act', 0), title: 'Explore Highlights', description: 'Discover top sights and waterfront destinations.', images: ['', ''], is_uploading_0: false, is_uploading_1: false }
        ],
        stays: [
            { _id: generateId('stay', 0), title: 'Resort & Spa', description: 'Luxury beachfront accommodation with modern amenities.', image: '', is_uploading: false }
        ],
        whyVisitJson: '',
        thingsToDoJson: '',
        staysJson: '',

        getImageUrl(path) {
            if (!path) return '';
            if (path.startsWith('http://') || path.startsWith('https://') || path.startsWith('data:') || path.startsWith('//')) {
                return path;
            }
            if (path.startsWith('/') || path.startsWith('assets/')) {
                return '/' + path.replace(/^\/+/, '');
            }
            return '/storage/' + path.replace(/^\/+/, '');
        },

        updateSlug() {
            if (!this.slug || this.slug === this.slugify(this.name.slice(0, -1))) {
                this.slug = this.slugify(this.name);
            }
        },
        slugify(text) {
            return text.toString().toLowerCase()
                .replace(/\s+/g, '-')
                .replace(/[^\w\-]+/g, '')
                .replace(/\-\-+/g, '-')
                .replace(/^-+/, '')
                .replace(/-+$/, '');
        },

        addWhyVisit() {
            this.whyVisit.push({
                _id: generateId('wv', this.whyVisit.length),
                title: '',
                description: '',
                image: '',
                is_uploading: false
            });
        },
        removeWhyVisit(index) {
            this.whyVisit.splice(index, 1);
        },
        moveWhyVisit(from, to) {
            if (to < 0 || to >= this.whyVisit.length) return;
            const item = this.whyVisit.splice(from, 1)[0];
            this.whyVisit.splice(to, 0, item);
        },
        async uploadWhyVisitImage(index, event) {
            const file = event.target.files[0];
            if (!file) return;
            this.whyVisit[index].is_uploading = true;
            const formData = new FormData();
            formData.append('image', file);
            formData.append('folder', 'why_visit');
            formData.append('_token', '{{ csrf_token() }}');
            try {
                const res = await fetch('{{ route('admin.destinations.upload-image') }}', {
                    method: 'POST',
                    body: formData,
                    headers: { 'Accept': 'application/json' }
                });
                const data = await res.json();
                if (data.success && data.url) {
                    this.whyVisit[index].image = data.url;
                    this.whyVisit = JSON.parse(JSON.stringify(this.whyVisit));
                } else {
                    alert(data.message || 'Image upload failed.');
                }
            } catch (err) {
                alert('Upload error: ' + err.message);
            } finally {
                this.whyVisit[index].is_uploading = false;
                event.target.value = '';
            }
        },

        addThingsToDo() {
            this.thingsToDo.push({
                _id: generateId('act', this.thingsToDo.length),
                title: '',
                description: '',
                images: ['', ''],
                is_uploading_0: false,
                is_uploading_1: false
            });
        },
        removeThingsToDo(index) {
            this.thingsToDo.splice(index, 1);
        },
        moveThingsToDo(from, to) {
            if (to < 0 || to >= this.thingsToDo.length) return;
            const item = this.thingsToDo.splice(from, 1)[0];
            this.thingsToDo.splice(to, 0, item);
        },
        addActivityImageSlot(actIndex) {
            if (!this.thingsToDo[actIndex].images) {
                this.thingsToDo[actIndex].images = [];
            }
            this.thingsToDo[actIndex].images.push('');
        },
        removeActivityImage(actIndex, imgIndex) {
            if (this.thingsToDo[actIndex].images.length <= 2) {
                this.thingsToDo[actIndex].images[imgIndex] = '';
            } else {
                this.thingsToDo[actIndex].images.splice(imgIndex, 1);
            }
        },
        async uploadActivityImage(actIndex, imgIndex, event) {
            const file = event.target.files[0];
            if (!file) return;
            this.thingsToDo[actIndex]['is_uploading_' + imgIndex] = true;
            const formData = new FormData();
            formData.append('image', file);
            formData.append('folder', 'activities');
            formData.append('_token', '{{ csrf_token() }}');
            try {
                const res = await fetch('{{ route('admin.destinations.upload-image') }}', {
                    method: 'POST',
                    body: formData,
                    headers: { 'Accept': 'application/json' }
                });
                const data = await res.json();
                if (data.success && data.url) {
                    this.thingsToDo[actIndex].images[imgIndex] = data.url;
                    this.thingsToDo = JSON.parse(JSON.stringify(this.thingsToDo));
                } else {
                    alert(data.message || 'Activity image upload failed.');
                }
            } catch (err) {
                alert('Upload error: ' + err.message);
            } finally {
                this.thingsToDo[actIndex]['is_uploading_' + imgIndex] = false;
                event.target.value = '';
            }
        },

        addStay() {
            this.stays.push({
                _id: generateId('stay', this.stays.length),
                title: '',
                description: '',
                image: '',
                is_uploading: false
            });
        },
        removeStay(index) {
            this.stays.splice(index, 1);
        },
        moveStay(from, to) {
            if (to < 0 || to >= this.stays.length) return;
            const item = this.stays.splice(from, 1)[0];
            this.stays.splice(to, 0, item);
        },
        async uploadStayImage(index, event) {
            const file = event.target.files[0];
            if (!file) return;
            this.stays[index].is_uploading = true;
            const formData = new FormData();
            formData.append('image', file);
            formData.append('folder', 'stays');
            formData.append('_token', '{{ csrf_token() }}');
            try {
                const res = await fetch('{{ route('admin.destinations.upload-image') }}', {
                    method: 'POST',
                    body: formData,
                    headers: { 'Accept': 'application/json' }
                });
                const data = await res.json();
                if (data.success && data.url) {
                    this.stays[index].image = data.url;
                    this.stays = JSON.parse(JSON.stringify(this.stays));
                } else {
                    alert(data.message || 'Hotel image upload failed.');
                }
            } catch (err) {
                alert('Upload error: ' + err.message);
            } finally {
                this.stays[index].is_uploading = false;
                event.target.value = '';
            }
        },

        prepareJson(e) {
            // Flush all active Quill editor instances into Alpine model state
            const form = document.getElementById('destination-create-form');
            if (form) {
                form.querySelectorAll('textarea').forEach(t => {
                    if (t._quill) {
                        const h = t._quill.root.innerHTML;
                        const val = (h === '<p><br></p>' || h === '<p></p>') ? '' : h;
                        t.value = val;
                        t.dispatchEvent(new Event('input', { bubbles: true }));
                        t.dispatchEvent(new Event('change', { bubbles: true }));
                    }
                });
            }

            const whyVisitClean = this.whyVisit.filter(i => (i.title && i.title.trim() !== '') || (i.description && i.description.trim() !== '') || (i.image && i.image.trim() !== '')).map(i => ({
                title: i.title || '',
                description: i.description || '',
                image: i.image || ''
            }));
            const whyVisitStr = JSON.stringify(whyVisitClean);

            const thingsToDoClean = this.thingsToDo.filter(act => (act.title && act.title.trim() !== '') || (act.description && act.description.trim() !== '') || ((act.images || []).some(img => img && img.trim() !== ''))).map(act => ({
                title: act.title || '',
                description: act.description || '',
                images: (act.images || []).filter(img => img && img.trim() !== '')
            }));
            const thingsToDoStr = JSON.stringify(thingsToDoClean);

            const staysClean = this.stays.filter(stay => (stay.title && stay.title.trim() !== '') || (stay.description && stay.description.trim() !== '') || (stay.image && stay.image.trim() !== '')).map(stay => ({
                title: stay.title || '',
                description: stay.description || '',
                image: stay.image || ''
            }));
            const staysStr = JSON.stringify(staysClean);

            this.whyVisitJson = whyVisitStr;
            this.thingsToDoJson = thingsToDoStr;
            this.staysJson = staysStr;

            const whyInput = document.getElementById('why_visit_json_input');
            if (whyInput) whyInput.value = whyVisitStr;

            const thingsInput = document.getElementById('things_to_do_json_input');
            if (thingsInput) thingsInput.value = thingsToDoStr;

            const staysInput = document.getElementById('stays_json_input');
            if (staysInput) staysInput.value = staysStr;
        }
    }
}
</script>
@endsection
