@extends('layouts.admin')

@section('title', 'Write New Article')

@section('content')
<div class="max-w-5xl mx-auto space-y-6" x-data="{
    title: '{{ old('title', '') }}',
    slug: '{{ old('slug', '') }}',
    imageUrl: '{{ old('image_url', '') }}',
    localImagePreview: null,
    handleFileUpload(event) {
        const file = event.target.files[0];
        if (file) {
            const reader = new FileReader();
            reader.onload = (e) => {
                this.localImagePreview = e.target.result;
            };
            reader.readAsDataURL(file);
        }
    },
    generateSlug() {
        if (!this.slug || this.slug === '') {
            this.slug = this.title.toLowerCase()
                .replace(/[^\w\s-]/g, '')
                .replace(/[\s_-]+/g, '-')
                .replace(/^-+|-+$/g, '');
        }
    }
}">

    <!-- Top Header & Action Bar -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div class="flex items-center gap-3">
            <a href="{{ route('admin.posts.index') }}" 
               class="p-2.5 rounded-xl bg-white border border-slate-200 text-slate-600 hover:text-navy hover:bg-slate-50 hover:border-slate-300 transition-all shadow-sm">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                </svg>
            </a>
            <div>
                <div class="flex items-center gap-2 text-xs font-semibold text-slate-500 mb-0.5">
                    <a href="{{ route('admin.posts.index') }}" class="hover:text-navy transition-colors">News & Articles</a>
                    <span>/</span>
                    <span class="text-navy font-bold">New Post</span>
                </div>
                <h1 class="text-2xl font-extrabold text-navy tracking-tight">Write New Article</h1>
                <p class="text-xs text-ink-muted">Publish press releases, airline news, travel insights, or fleet updates.</p>
            </div>
        </div>

        <div class="flex items-center gap-2.5">
            <a href="{{ route('admin.posts.index') }}" 
               class="px-4 py-2.5 rounded-xl border border-slate-200 bg-white hover:bg-slate-50 text-xs font-bold text-slate-700 transition-all shadow-sm">
                Cancel
            </a>
            <button type="button" 
                    @click="document.getElementById('post-create-form').submit()" 
                    class="px-5 py-2.5 rounded-xl bg-accent hover:bg-accent-hover text-navy font-extrabold text-xs shadow-md shadow-accent/20 hover:shadow-lg transition-all flex items-center gap-2">
                <svg class="w-4 h-4 text-navy" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
                </svg>
                <span>Publish Article</span>
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

    <form id="post-create-form" method="POST" action="{{ route('admin.posts.store') }}" enctype="multipart/form-data" class="space-y-6">
        @csrf

        <!-- CARD 1: Article Identity & Metadata -->
        <div class="naoero-card p-6 bg-white border border-slate-200 space-y-5">
            <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                <div class="flex items-center gap-2.5">
                    <div class="w-8 h-8 rounded-xl bg-navy-50 text-navy font-black text-xs flex items-center justify-center">1</div>
                    <div>
                        <h2 class="text-sm font-extrabold text-navy uppercase tracking-wider">Article Title & Details</h2>
                        <p class="text-xs text-ink-muted">Set the post headline, URL slug, category classification, and author attribution.</p>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                <!-- Title -->
                <div class="md:col-span-2">
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">
                        Article Title <span class="text-rose-500">*</span>
                    </label>
                    <input type="text" 
                           name="title" 
                           x-model="title"
                           @input="generateSlug()"
                           value="{{ old('title') }}" 
                           placeholder="e.g. Naoero Airlines Expands Island Hopper Network Across Central Pacific" 
                           required
                           class="w-full px-3.5 py-2.5 text-sm rounded-xl border border-slate-200 focus:outline-none focus:ring-2 focus:ring-accent/40 focus:border-accent">
                </div>

                <!-- Type (Blog vs News) -->
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">
                        Article Type <span class="text-rose-500">*</span>
                    </label>
                    <select name="type" required class="w-full px-3.5 py-2.5 text-sm font-semibold rounded-xl border border-slate-200 focus:outline-none focus:ring-2 focus:ring-accent/40 focus:border-accent bg-white">
                        <option value="blog" {{ old('type', 'blog') === 'blog' ? 'selected' : '' }}>✍️ Blog Article (/api/blogs & /blog)</option>
                        <option value="news" {{ old('type') === 'news' ? 'selected' : '' }}>📰 News Hub & Media Release (/api/news & /news-hub)</option>
                    </select>
                </div>

                <!-- Slug -->
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">
                        URL Slug
                    </label>
                    <input type="text" 
                           name="slug" 
                           x-model="slug"
                           value="{{ old('slug') }}" 
                           placeholder="e.g. naoero-airlines-expands-network" 
                           class="w-full px-3.5 py-2.5 text-sm font-mono rounded-xl border border-slate-200 focus:outline-none focus:ring-2 focus:ring-accent/40 focus:border-accent bg-slate-50/50">
                    <p class="text-[11px] text-slate-400 mt-1">Live URL: /blog/<span x-text="slug || 'slug'"></span></p>
                </div>

                <!-- Category -->
                <div>
                    <div class="flex items-center justify-between mb-1.5">
                        <label class="text-xs font-bold text-slate-700 uppercase tracking-wider">
                            Category <span class="text-rose-500">*</span>
                        </label>
                        <a href="{{ route('admin.dropdowns.index') }}" target="_blank" class="text-[11px] font-bold text-navy hover:text-accent flex items-center gap-1 transition-colors">
                            <span>+ Manage Categories</span>
                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
                        </a>
                    </div>
                    <select name="category" required class="w-full px-3.5 py-2.5 text-sm font-semibold rounded-xl border border-slate-200 focus:outline-none focus:ring-2 focus:ring-accent/40 focus:border-accent bg-white">
                        <option value="">Select Category...</option>
                        <optgroup label="Blog Categories">
                            @foreach($blogCategories as $bCat)
                                <option value="{{ $bCat }}" {{ old('category') === $bCat ? 'selected' : '' }}>{{ $bCat }}</option>
                            @endforeach
                        </optgroup>
                        <optgroup label="News Hub Categories">
                            @foreach($newsCategories as $nCat)
                                <option value="{{ $nCat }}" {{ old('category') === $nCat ? 'selected' : '' }}>{{ $nCat }}</option>
                            @endforeach
                        </optgroup>
                        @if(!empty($categories))
                        <optgroup label="Other Categories">
                            @foreach($categories as $cat)
                                @if(!in_array($cat, $blogCategories) && !in_array($cat, $newsCategories))
                                    <option value="{{ $cat }}" {{ old('category') === $cat ? 'selected' : '' }}>{{ $cat }}</option>
                                @endif
                            @endforeach
                        </optgroup>
                        @endif
                    </select>
                </div>

                <!-- Author -->
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">
                        Author Byline
                    </label>
                    <input type="text" 
                           name="author" 
                           value="{{ old('author', 'Naoero Airlines Editorial Team') }}" 
                           placeholder="e.g. Media Relations / Capt. John Doe"
                           class="w-full px-3.5 py-2.5 text-sm rounded-xl border border-slate-200 focus:outline-none focus:ring-2 focus:ring-accent/40 focus:border-accent">
                </div>

                <!-- Published Date -->
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">
                        Publication Date
                    </label>
                    <input type="date" 
                           name="published_at" 
                           value="{{ old('published_at', now()->format('Y-m-d')) }}" 
                           class="w-full px-3.5 py-2.5 text-sm font-semibold rounded-xl border border-slate-200 focus:outline-none focus:ring-2 focus:ring-accent/40 focus:border-accent bg-white">
                </div>
            </div>
        </div>

        <!-- CARD 2: Excerpt & Summary -->
        <div class="naoero-card p-6 bg-white border border-slate-200 space-y-4">
            <div class="flex items-center gap-2.5 border-b border-slate-100 pb-3">
                <div class="w-8 h-8 rounded-xl bg-navy-50 text-navy font-black text-xs flex items-center justify-center">2</div>
                <div>
                    <h2 class="text-sm font-extrabold text-navy uppercase tracking-wider">Excerpt & Preview Snippet</h2>
                    <p class="text-xs text-ink-muted">A concise teaser displayed on news list cards, search results, and social previews.</p>
                </div>
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">
                    Excerpt (Short Summary)
                </label>
                <textarea name="excerpt" 
                          rows="3" 
                          placeholder="Provide a compelling 2-3 sentence overview of this article..." 
                          class="w-full px-3.5 py-2.5 text-sm rounded-xl border border-slate-200 focus:outline-none focus:ring-2 focus:ring-accent/40 focus:border-accent leading-relaxed">{{ old('excerpt') }}</textarea>
            </div>
        </div>

        <!-- CARD 3: Full Rich Text Body -->
        <div class="naoero-card p-6 bg-white border border-slate-200 space-y-4">
            <div class="flex items-center gap-2.5 border-b border-slate-100 pb-3">
                <div class="w-8 h-8 rounded-xl bg-navy-50 text-navy font-black text-xs flex items-center justify-center">3</div>
                <div>
                    <h2 class="text-sm font-extrabold text-navy uppercase tracking-wider">Full Article Content</h2>
                    <p class="text-xs text-ink-muted">Compose the complete story with rich headings, blockquotes, lists, and formatted text.</p>
                </div>
            </div>

            <div>
                <textarea name="content" 
                          rows="12" 
                          placeholder="Write the full news story or article here..." 
                          class="wysiwyg-editor w-full px-3.5 py-2.5 text-sm rounded-xl border border-slate-200 focus:outline-none focus:ring-2 focus:ring-accent/40 focus:border-accent font-sans">{{ old('content') }}</textarea>
            </div>
        </div>

        <!-- CARD 4: Cover Image & Media -->
        <div class="naoero-card p-6 bg-white border border-slate-200 space-y-5">
            <div class="flex items-center gap-2.5 border-b border-slate-100 pb-3">
                <div class="w-8 h-8 rounded-xl bg-navy-50 text-navy font-black text-xs flex items-center justify-center">4</div>
                <div>
                    <h2 class="text-sm font-extrabold text-navy uppercase tracking-wider">Cover Image & Media</h2>
                    <p class="text-xs text-ink-muted">Upload high-resolution header imagery (16:9 recommended) or specify an image URL.</p>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">
                        Upload Image File
                    </label>
                    <input type="file" 
                           name="image_file" 
                           accept="image/*"
                           @change="handleFileUpload($event)"
                           class="w-full text-xs text-slate-500 file:mr-4 file:py-2.5 file:px-4 file:rounded-xl file:border-0 file:text-xs file:font-bold file:bg-navy-50 file:text-navy hover:file:bg-navy-100 cursor-pointer border border-slate-200 rounded-xl p-1 bg-slate-50/50">
                    <p class="text-[11px] text-slate-400 mt-1">Supports JPG, PNG, WebP up to 5MB.</p>
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">
                        Or External Image URL
                    </label>
                    <input type="url" 
                           name="image_url" 
                           x-model="imageUrl"
                           value="{{ old('image_url') }}" 
                           placeholder="https://images.unsplash.com/..." 
                           class="w-full px-3.5 py-2.5 text-sm rounded-xl border border-slate-200 focus:outline-none focus:ring-2 focus:ring-accent/40 focus:border-accent">
                </div>
            </div>

            <!-- Live Image Preview Box -->
            <div x-show="localImagePreview || imageUrl" class="p-4 rounded-xl bg-slate-50 border border-slate-200" x-cloak>
                <p class="text-xs font-bold text-slate-700 uppercase tracking-wider mb-2">Cover Preview</p>
                <div class="relative rounded-xl overflow-hidden max-h-56 bg-slate-200 border border-slate-300">
                    <img :src="localImagePreview || imageUrl" alt="Preview" class="w-full h-48 object-cover">
                </div>
            </div>
        </div>

        <!-- CARD 5: Publication Flags & Visibility -->
        <div class="naoero-card p-6 bg-white border border-slate-200 space-y-4">
            <div class="flex items-center gap-2.5 border-b border-slate-100 pb-3">
                <div class="w-8 h-8 rounded-xl bg-navy-50 text-navy font-black text-xs flex items-center justify-center">5</div>
                <div>
                    <h2 class="text-sm font-extrabold text-navy uppercase tracking-wider">Visibility & Featured Status</h2>
                    <p class="text-xs text-ink-muted">Control publishing state and frontpage hero banner highlights.</p>
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <label class="flex items-start gap-3.5 p-4 rounded-xl border border-slate-200 bg-slate-50/50 hover:bg-slate-50 cursor-pointer transition-colors">
                    <div class="relative flex items-center mt-0.5">
                        <input type="checkbox" name="is_published" value="1" class="sr-only peer" {{ old('is_published', true) ? 'checked' : '' }}>
                        <div class="w-10 h-6 bg-slate-300 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-emerald-500"></div>
                    </div>
                    <div>
                        <span class="text-sm font-extrabold text-navy block">Publish Immediately</span>
                        <span class="text-xs text-slate-500">Make this article publicly accessible immediately upon saving.</span>
                    </div>
                </label>

                <label class="flex items-start gap-3.5 p-4 rounded-xl border border-slate-200 bg-slate-50/50 hover:bg-slate-50 cursor-pointer transition-colors">
                    <div class="relative flex items-center mt-0.5">
                        <input type="checkbox" name="is_featured" value="1" class="sr-only peer" {{ old('is_featured') ? 'checked' : '' }}>
                        <div class="w-10 h-6 bg-slate-300 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-amber-500"></div>
                    </div>
                    <div>
                        <span class="text-sm font-extrabold text-navy block">Feature on News Header</span>
                        <span class="text-xs text-slate-500">Spotlight this article as the hero feature on the newsroom hub.</span>
                    </div>
                </label>
            </div>
        </div>

        <!-- Bottom Action Bar -->
        <div class="flex items-center justify-between p-4 rounded-2xl bg-white border border-slate-200 shadow-sm">
            <a href="{{ route('admin.posts.index') }}" class="px-5 py-2.5 rounded-xl border border-slate-200 text-slate-600 font-bold text-xs hover:bg-slate-50 transition-colors">
                Cancel
            </a>
            <button type="submit" class="px-6 py-2.5 rounded-xl bg-accent hover:bg-accent-hover text-navy font-extrabold text-xs shadow-md shadow-accent/20 hover:shadow-lg transition-all flex items-center gap-2">
                <svg class="w-4 h-4 text-navy" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
                </svg>
                <span>Publish Article</span>
            </button>
        </div>
    </form>

</div>
@endsection
