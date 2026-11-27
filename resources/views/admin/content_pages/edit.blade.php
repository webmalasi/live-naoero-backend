@extends('layouts.admin')

@section('title', 'Edit Page - ' . $page->title)

@section('content')
@php
    $input = 'w-full px-3 py-2 text-sm rounded-xl border border-slate-200 focus:ring-2 focus:ring-navy';
    $label = 'block text-[11px] font-bold text-slate-600 uppercase tracking-wider mb-1';
    $website = rtrim(config('naoero.website_url', 'https://napacificbridge.info'), '/');
@endphp
<div class="max-w-6xl mx-auto space-y-6" x-data="pageEditor()" x-init="init()">
    @include('admin.content_pages._entry_panel')


    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3">
        <div>
            <a href="{{ route('admin.content-pages.index') }}" class="text-xs font-bold text-slate-500 hover:text-navy">&larr; Back to pages</a>
            <h1 class="text-2xl font-extrabold text-navy mt-1">{{ $page->title }}</h1>
            <p class="text-sm text-ink-muted">
                Version {{ $page->version }}, saved {{ $page->updated_at?->diffForHumans() }}@if($page->editor) by {{ $page->editor->name }}@endif.
                The website and the app show a save within a few seconds.
            </p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <a href="{{ url('/api/pages/'.$page->slug) }}" target="_blank" class="text-xs font-mono font-bold text-navy bg-slate-100 px-3 py-1.5 rounded-lg border border-slate-200 hover:bg-slate-200">/api/pages/{{ $page->slug }}</a>
            @if($page->path)
                <a href="{{ $website.$page->path }}" target="_blank" rel="noopener" class="text-xs font-bold text-navy bg-white px-3 py-1.5 rounded-lg border border-slate-200 hover:bg-slate-50">View on website &nearr;</a>
            @endif
            <span class="px-2.5 py-1 rounded-full text-[11px] font-bold {{ $page->is_published ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800' }}">{{ $page->is_published ? 'Published' : 'Hidden' }}</span>
        </div>
    </div>

    @if ($errors->any())
        <div class="p-3 rounded-xl bg-rose-50 border border-rose-200 text-sm text-rose-700">{{ $errors->first() }}</div>
    @endif

    @if($extraLists)
        <div class="rounded-2xl bg-white border border-slate-200 shadow-sm p-4">
            <div class="text-xs font-extrabold text-navy uppercase tracking-wider">Lists on this page</div>
            <div class="text-[11px] text-slate-500 mb-3">Edited here, in a panel, and saved on their own.</div>
            <div class="flex flex-wrap gap-2">
                @foreach($extraLists as $list)
                    <button type="button" @click='openPanel(@json($list["url"]), @json($list["label"]))' class="px-3.5 py-2 text-xs font-bold text-navy bg-accent hover:bg-accent-hover rounded-xl">Manage {{ $list['label'] }}</button>
                    @if($list['add'])<button type="button" @click='openPanel(@json($list["add"]), @json("Add to ".$list["label"]))' class="px-3.5 py-2 text-xs font-bold text-navy bg-slate-100 hover:bg-slate-200 rounded-xl">+ Add</button>@endif
                @endforeach
            </div>
        </div>
    @endif

    <form method="POST" action="{{ route('admin.content-pages.update', $page) }}" x-ref="form" @submit="prepare($event)" class="space-y-6">
        @csrf
        @method('PUT')
        <input type="hidden" name="version" value="{{ $page->version }}">
        <input type="hidden" name="page_json" x-ref="json">

        {{-- Page settings --}}
        <div class="naoero-card p-6 bg-white border border-slate-200 shadow-sm space-y-4">
            <h2 class="text-base font-bold text-navy border-b border-slate-100 pb-2">Page</h2>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div class="md:col-span-2">
                    <label class="{{ $label }}">Title</label>
                    <input type="text" x-model="page.title" class="{{ $input }} font-bold" required>
                </div>
                <div>
                    <label class="{{ $label }}">Group</label>
                    <select x-model="page.group" class="{{ $input }}">
                        @foreach($groups as $value => $name)
                            <option value="{{ $value }}">{{ $name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="{{ $label }}">Website address</label>
                    <input type="text" x-model="page.path" placeholder="/charter" class="{{ $input }} font-mono">
                </div>
                <div>
                    <label class="{{ $label }}">Top banner</label>
                    <input type="text" x-model="page.banner_key" placeholder="{{ $page->slug }}" class="{{ $input }} font-mono">
                    <p class="text-[11px] text-slate-400 mt-1">The banner's page key under <a class="underline" href="{{ route('admin.banners.index') }}">Page Banners</a>.</p>
                </div>
                <div>
                    <label class="{{ $label }}">Order in menus</label>
                    <input type="number" x-model.number="page.sort_order" class="{{ $input }}">
                </div>
            </div>
            <div>
                <label class="{{ $label }}">Short summary</label>
                <div x-rich="page.summary"></div>
                <p class="mt-1 text-[11px] text-slate-400">Used in lists of pages only. The text a visitor reads is in the sections below.</p>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="{{ $label }}">Search engine title</label>
                    <input type="text" x-model="page.seo_title" :placeholder="page.title" maxlength="255" class="{{ $input }}">
                </div>
                <div>
                    <label class="{{ $label }}">Search engine description</label>
                    <input type="text" x-model="page.seo_description" maxlength="500" class="{{ $input }}">
                </div>
            </div>
            <label class="inline-flex items-center gap-2 text-sm font-bold text-navy">
                <input type="checkbox" x-model="page.is_published" class="rounded border-slate-300 text-navy focus:ring-navy">
                Published (when hidden, the website and the app show their built-in text)
            </label>
        </div>

        {{-- Sections --}}
        <div class="naoero-card p-6 bg-white border border-slate-200 shadow-sm space-y-4">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 border-b border-slate-100 pb-3">
                <div>
                    <h2 class="text-base font-bold text-navy">Sections <span class="text-slate-400 font-semibold" x-text="'(' + page.sections.length + ')'"></span></h2>
                    <p class="text-xs text-slate-500">Shown in this order. Text marks: a blank line starts a paragraph; lines starting with <b>-</b> make a list (<b>1.</b> a numbered one); <b>**bold**</b>; <b>[label](https://link)</b>. Web and email addresses become links.</p>
                </div>
                <div class="flex gap-2">
                    <button type="button" @click="openAll(true)" class="px-3 py-1.5 text-xs font-bold text-slate-600 bg-slate-100 rounded-lg">Open all</button>
                    <button type="button" @click="openAll(false)" class="px-3 py-1.5 text-xs font-bold text-slate-600 bg-slate-100 rounded-lg">Close all</button>
                </div>
            </div>

            <div class="flex items-center gap-2">
                <input type="search" x-model="find" placeholder="Find text on this page, e.g. a question or a sentence" class="{{ $input }}">
                <span x-show="find.trim()" class="shrink-0 text-xs font-bold text-slate-500" x-text="found() + ' found'"></span>
            </div>

            <template x-for="(section, si) in page.sections" :key="section._id">
                <div class="border rounded-2xl" x-show="!linkedGallery(section) && matches(section)" :class="section.is_visible ? 'border-slate-200' : 'border-dashed border-slate-300 bg-slate-50/60'">
                    <div class="flex items-center gap-2 px-4 py-3 cursor-pointer" @click="section._open = !section._open">
                        <span class="w-6 h-6 rounded-full bg-navy text-white text-[11px] font-bold flex items-center justify-center" x-text="si + 1"></span>
                        <div class="flex-1 min-w-0">
                            <div class="font-bold text-navy truncate" x-text="title(section)"></div>
                            <div class="text-[11px] text-slate-400 font-mono truncate">
                                <span x-text="types[section.type] || section.type"></span> · <span x-text="section.key"></span>
                                <template x-if="section.items.length"><span x-text="' · ' + section.items.length + ' items'"></span></template>
                                <template x-if="section.show_on !== 'both'"><span class="text-indigo-600 font-bold" x-text="section.show_on === 'app' ? ' · app only' : ' · website only'"></span></template>
                                <template x-if="!section.is_visible"><span class="text-amber-600 font-bold"> · hidden</span></template>
                            </div>
                        </div>
                        <div class="flex items-center gap-1" @click.stop>
                            <button type="button" @click="move(page.sections, si, -1)" :disabled="si === 0" title="Move up" class="w-7 h-7 rounded-lg bg-slate-100 text-slate-600 disabled:opacity-30">&uarr;</button>
                            <button type="button" @click="move(page.sections, si, 1)" :disabled="si === page.sections.length - 1" title="Move down" class="w-7 h-7 rounded-lg bg-slate-100 text-slate-600 disabled:opacity-30">&darr;</button>
                            <button type="button" @click="section.is_visible = !section.is_visible" :title="section.is_visible ? 'Hide this section' : 'Show this section'" class="px-2 h-7 rounded-lg bg-slate-100 text-[11px] font-bold text-slate-600" x-text="section.is_visible ? 'Hide' : 'Show'"></button>
                            <button type="button" @click="duplicate(si)" title="Duplicate" class="px-2 h-7 rounded-lg bg-slate-100 text-[11px] font-bold text-slate-600">Copy</button>
                            <button type="button" @click="removeSection(si)" title="Remove" class="w-7 h-7 rounded-lg bg-rose-50 text-rose-600">&times;</button>
                        </div>
                    </div>

                    <div x-show="section._open || find.trim()" class="px-4 pb-4 space-y-3 border-t border-slate-100 pt-3">
                        @if($banner)
                            <div x-show="si === heroIndex()" class="p-3 rounded-xl bg-amber-50 border border-amber-200 text-xs text-amber-900 space-y-1">
                                <div class="font-bold">The banner at the top of this page comes from <a href="{{ route('admin.banners.index') }}" target="_blank" class="underline">Page Banners</a>.</div>
                                <div>While a banner is set there, its heading and text are shown on the website instead of the heading and text here. A picture uploaded here takes the place of the banner's picture.</div>
                                @if($banner['heading'] || $banner['description'])<div>Shown now: <strong>{{ $banner['heading'] }}</strong>@if($banner['description']) — {{ \Illuminate\Support\Str::limit(strip_tags($banner['description']), 120) }}@endif</div>@endif
                                @if($banner['image'])<img src="{{ $banner['image'] }}" alt="" class="h-16 rounded-lg border border-amber-200 object-cover">@endif
                            </div>
                        @endif
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                            <div>
                                <label class="{{ $label }}">Kind</label>
                                <select x-model="section.type" class="{{ $input }}">
                                    <template x-for="(name, value) in types" :key="value"><option :value="value" x-text="name" :selected="value === section.type"></option></template>
                                </select>
                            </div>
                            <div>
                                <label class="{{ $label }}">Key</label>
                                <input type="text" x-model="section.key" class="{{ $input }} font-mono" title="The website and the app find this section by its key. Change it only for a new section.">
                            </div>
                            <div>
                                <label class="{{ $label }}">Show on</label>
                                <select x-model="section.show_on" class="{{ $input }}">
                                    @foreach($channels as $value => $name)
                                        <option value="{{ $value }}">{{ $name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div x-show="section.type === 'feed'" class="md:col-span-3">
                                <label class="{{ $label }}">List to show</label>
                                <select x-model="section.feed" class="{{ $input }}">
                                    <option value="">Choose…</option>
                                    @foreach($feeds as $value => $name)
                                        <option value="{{ $value }}">{{ $name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        @include('admin.content_pages._feed_entries')
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                            <div>
                                <label class="{{ $label }}">Heading</label>
                                <input type="text" x-model="section.heading" class="{{ $input }} font-bold">
                            </div>
                            <div>
                                <label class="{{ $label }}">Subheading</label>
                                <input type="text" x-model="section.subheading" class="{{ $input }}">
                            </div>
                        </div>
                        <div>
                            <label class="{{ $label }}">Text</label>
                            <div x-rich="section.body"></div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-3 gap-3" x-show="section.type !== 'feed'">
                            <div class="md:col-span-2">
                                <label class="{{ $label }}">Picture</label>
                                <div class="flex items-center gap-2">
                                    <input type="text" x-model="section.image" placeholder="Empty: the website and the app use their own picture" class="{{ $input }} font-mono text-xs">
                                    <label class="shrink-0 px-3 py-2 text-xs font-bold text-navy bg-navy/10 rounded-xl cursor-pointer">
                                        <span x-text="uploading === 's' + si ? 'Uploading…' : 'Upload'"></span>
                                        <input type="file" accept="image/*" class="hidden" @change="upload($event, url => section.image = url, 's' + si)">
                                    </label>
                                </div>
                                <template x-if="section.image"><img :src="preview(section.image)" class="mt-2 h-20 rounded-lg border border-slate-200 object-cover"></template>
                            </div>
                            <div>
                                <label class="{{ $label }}">Picture description</label>
                                <input type="text" x-model="section.image_alt" class="{{ $input }}">
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                            <div>
                                <label class="{{ $label }}">Button label</label>
                                <input type="text" x-model="section.button_label" class="{{ $input }}">
                            </div>
                            <div>
                                <label class="{{ $label }}">Button link</label>
                                <input type="text" x-model="section.button_url" placeholder="/contact-us or https://…" class="{{ $input }} font-mono text-xs">
                            </div>
                        </div>
                        <div x-show="section.type === 'table'">
                            <label class="{{ $label }}">Table columns (separate with |)</label>
                            <input type="text" x-model="section.columns" placeholder="Fare | Cabin | Checked" class="{{ $input }}">
                        </div>
                        <div>
                            <label class="{{ $label }}">Note (small print under the section)</label>
                            <div x-rich="section.note"></div>
                        </div>

                        {{-- Items --}}
                        <div class="rounded-xl bg-slate-50 border border-slate-200 p-3 space-y-2" x-show="section.type === 'gallery'">
                            <div class="text-xs font-extrabold text-navy uppercase tracking-wider" x-text="'Pictures (' + section.items.length + ')'"></div>
                            @include('admin.content_pages._picture_list', ['list' => 'section.items'])
                        </div>
                        <div class="rounded-xl bg-slate-50 border border-slate-200 p-3 space-y-2" x-show="section.type !== 'feed' && section.type !== 'gallery'">
                            <div class="flex items-center justify-between">
                                <div class="text-xs font-extrabold text-navy uppercase tracking-wider" x-text="itemsName(section.type) + ' (' + section.items.length + ')'"></div>
                                <button type="button" @click="addItem(section)" class="px-3 py-1 text-xs font-bold text-navy bg-accent rounded-lg">+ Add</button>
                            </div>
                            <template x-if="section.type !== 'gallery'"><div class="space-y-2"><template x-for="(item, ii) in section.items" :key="item._id">
                                <div class="bg-white border border-slate-200 rounded-xl p-3 space-y-2">
                                    <div class="flex items-center justify-between gap-2">
                                        <span class="text-[11px] font-bold text-slate-400" x-text="'#' + (ii + 1)"></span>
                                        <div class="flex items-center gap-1">
                                            <button type="button" @click="item._more = !item._more" class="px-2 h-6 rounded-md bg-slate-100 text-[11px] font-bold text-slate-600" x-text="item._more ? 'Fewer fields' : 'More fields'"></button>
                                            <button type="button" @click="move(section.items, ii, -1)" :disabled="ii === 0" class="w-6 h-6 rounded-md bg-slate-100 text-slate-600 disabled:opacity-30">&uarr;</button>
                                            <button type="button" @click="move(section.items, ii, 1)" :disabled="ii === section.items.length - 1" class="w-6 h-6 rounded-md bg-slate-100 text-slate-600 disabled:opacity-30">&darr;</button>
                                            <button type="button" @click="section.items.splice(ii, 1)" class="w-6 h-6 rounded-md bg-rose-50 text-rose-600">&times;</button>
                                        </div>
                                    </div>
                                    <template x-if="section.type === 'table'">
                                        <div>
                                            <label class="{{ $label }}">Row (separate cells with |)</label>
                                            <input type="text" x-model="item.cells" class="{{ $input }}">
                                        </div>
                                    </template>
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-2" x-show="section.type !== 'table' || item._more">
                                        <div x-show="show(section.type, 'value', item)">
                                            <label class="{{ $label }}">Figure / value</label>
                                            <input type="text" x-model="item.value" class="{{ $input }}">
                                        </div>
                                        <div x-show="show(section.type, 'title', item)">
                                            <label class="{{ $label }}" x-text="section.type === 'faq' ? 'Question' : 'Title'"></label>
                                            <input type="text" x-model="item.title" class="{{ $input }} font-bold">
                                        </div>
                                        <div x-show="show(section.type, 'subtitle', item)">
                                            <label class="{{ $label }}">Subtitle</label>
                                            <input type="text" x-model="item.subtitle" class="{{ $input }}">
                                        </div>
                                        <div x-show="show(section.type, 'icon', item)">
                                            <label class="{{ $label }}">Icon</label>
                                            @include('admin.content_pages._icon_picker')
                                        </div>
                                    </div>
                                    <div x-show="show(section.type, 'text', item)">
                                        <label class="{{ $label }}" x-text="section.type === 'faq' ? 'Answer' : 'Text'"></label>
                                        <div x-rich="item.text"></div>
                                    </div>
                                    <template x-if="picturesFor(item)">
                                        <div class="rounded-xl bg-slate-50 border border-slate-200 p-3 space-y-2">
                                            <div class="text-[11px] font-bold text-slate-600 uppercase tracking-wider" x-text="'Pictures for ' + (item.title || 'this entry')"></div>
                                            @include('admin.content_pages._picture_list', ['list' => 'picturesFor(item).items'])
                                        </div>
                                    </template>
                                    <div x-show="show(section.type, 'list', item)">
                                        <label class="{{ $label }}">Points (one per line)</label>
                                        <textarea x-model="item.list" rows="3" class="{{ $input }}"></textarea>
                                    </div>
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-2" x-show="show(section.type, 'link_url', item)">
                                        <div>
                                            <label class="{{ $label }}">Link label</label>
                                            <input type="text" x-model="item.link_label" class="{{ $input }}">
                                        </div>
                                        <div>
                                            <label class="{{ $label }}">Link</label>
                                            <input type="text" x-model="item.link_url" placeholder="/page or https://…" class="{{ $input }} font-mono text-xs">
                                        </div>
                                    </div>
                                    <div x-show="show(section.type, 'image', item)">
                                        <label class="{{ $label }}">Picture</label>
                                        <div class="flex items-center gap-2">
                                            <input type="text" x-model="item.image" placeholder="Empty: built-in picture" class="{{ $input }} font-mono text-xs">
                                            <label class="shrink-0 px-3 py-2 text-xs font-bold text-navy bg-navy/10 rounded-xl cursor-pointer">
                                                <span x-text="uploading === 'i' + si + '-' + ii ? 'Uploading…' : 'Upload'"></span>
                                                <input type="file" accept="image/*" class="hidden" @change="upload($event, url => item.image = url, 'i' + si + '-' + ii)">
                                            </label>
                                            <template x-if="item.image"><img :src="preview(item.image)" class="h-10 w-14 rounded-md border border-slate-200 object-cover"></template>
                                            <button type="button" x-show="item.image" @click="item.image = ''" title="Remove picture" class="shrink-0 w-7 h-7 rounded-md bg-rose-50 text-rose-600">&times;</button>
                                        </div>
                                    </div>
                                    <div x-show="item._more" class="grid grid-cols-1 md:grid-cols-2 gap-2">
                                        <div>
                                            <label class="{{ $label }}">More pictures (one per line)</label>
                                            <textarea x-model="item.gallery" rows="2" class="{{ $input }} font-mono text-xs"></textarea>
                                        </div>
                                        <div>
                                            <label class="{{ $label }}">Item address (slug)</label>
                                            <input type="text" x-model="item.slug" class="{{ $input }} font-mono text-xs">
                                        </div>
                                    </div>
                                </div>
                            </template></div></template>
                        </div>
                    </div>
                </div>
            </template>

            <div class="flex flex-wrap items-center gap-2 pt-2">
                <select x-model="newType" class="px-3 py-2 text-sm rounded-xl border border-slate-200">
                    <template x-for="(name, value) in types" :key="value"><option :value="value" x-text="name"></option></template>
                </select>
                <button type="button" @click="addSection()" class="px-4 py-2 text-xs font-bold text-white bg-navy rounded-xl">+ Add section</button>
            </div>
        </div>

        {{-- Save bar --}}
        <div class="sticky bottom-3 z-20">
            <div class="flex flex-col sm:flex-row sm:items-center gap-3 p-3 rounded-2xl bg-navy shadow-lg">
                <input type="text" name="note" maxlength="200" placeholder="What did you change? (optional, shown in the history)" class="flex-1 px-3 py-2 text-sm rounded-xl border-0 focus:ring-2 focus:ring-accent">
                <span class="text-xs text-white/70" x-show="dirty">Unsaved changes</span>
                <button type="submit" class="px-6 py-2.5 text-sm font-extrabold text-navy bg-accent hover:bg-accent-hover rounded-xl">Save page</button>
            </div>
        </div>
    </form>

    {{-- History --}}
    <div class="naoero-card p-6 bg-white border border-slate-200 shadow-sm space-y-3">
        <h2 class="text-base font-bold text-navy border-b border-slate-100 pb-2">History</h2>
        @forelse($revisions as $revision)
            <div class="flex items-center justify-between gap-3 text-sm">
                <div>
                    <span class="font-bold text-navy">Version {{ $revision->version }}</span>
                    <span class="text-slate-500">· replaced {{ $revision->created_at?->format('j M Y, H:i') }}@if($revision->user) by {{ $revision->user->name }}@endif</span>
                    @if($revision->note) <span class="text-slate-400">· {{ $revision->note }}</span> @endif
                </div>
                <form method="POST" action="{{ route('admin.content-pages.restore', [$page, $revision]) }}" onsubmit="return confirm('Put the page back to version {{ $revision->version }}? The current version stays in the history.')">
                    @csrf
                    <button type="submit" class="px-3 py-1 text-xs font-bold text-navy bg-slate-100 hover:bg-slate-200 rounded-lg">Restore</button>
                </form>
            </div>
        @empty
            <p class="text-sm text-slate-500">No earlier versions yet.</p>
        @endforelse
    </div>
</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/lucide@0.460.0/dist/umd/lucide.min.js"></script>
@include('admin.content_pages._rich_editor')
<script>
function pageEditor() {
    const source = @json($data);
    let next = 1;
    const id = () => next++;
    const toItem = (item) => ({
        _id: id(), _more: false,
        slug: item.slug ?? '', title: item.title ?? '', subtitle: item.subtitle ?? '', text: item.text ?? '',
        value: item.value ?? '', icon: item.icon ?? '', image: item.image ?? '', link_label: item.link_label ?? '',
        link_url: item.link_url ?? '', list: (item.list ?? []).join('\n'), gallery: (item.gallery ?? []).join('\n'),
        cells: (item.cells ?? []).join(' | '),
    });
    const toSection = (s) => ({
        _id: id(), _open: false,
        key: s.key ?? '', type: s.type ?? 'text', heading: s.heading ?? '', subheading: s.subheading ?? '',
        body: s.body ?? '', image: s.image ?? '', image_alt: s.image_alt ?? '', button_label: s.button_label ?? '',
        button_url: s.button_url ?? '', note: s.note ?? '', feed: s.feed ?? '', is_visible: s.is_visible !== false,
        show_on: s.show_on ?? 'both',
        columns: (s.columns ?? []).join(' | '), items: (s.items ?? []).map(toItem),
    });
    // Which item fields each kind of section uses (the rest are under "More fields").
    const FIELDS = {
        faq: ['title', 'text'], table: ['cells'], stats: ['value', 'title', 'text', 'icon'], links: ['title', 'text', 'image', 'link_url'],
        steps: ['title', 'text', 'image', 'list'], list: ['title', 'text', 'icon', 'image', 'list'],
        contacts: ['title', 'subtitle', 'text', 'value', 'icon', 'image', 'link_url'], notice: ['title', 'text'],
    };
    return {
        page: { ...source, sections: (source.sections ?? []).map(toSection) },
        types: @json($types),
        newType: 'text',
        find: '',
        icons: @json($icons),
        feedEntries: @json($feedEntries),
        uploading: null,
        dirty: false,
        panel: null,
        panelCanSave: false,
        // The entry's own form, inside the panel: the biggest one that is not a delete or toggle button.
        panelForm() {
            const doc = this.$refs.panelFrame?.contentDocument;
            if (!doc) return null;
            const forms = [...doc.querySelectorAll('form')].filter((f) => !f.querySelector('input[name=_method][value=DELETE]') && f.elements.length > 3);
            return forms.sort((a, b) => b.elements.length - a.elements.length)[0] ?? null;
        },
        panelLoaded() {
            try { this.panelCanSave = !!this.panelForm(); } catch (e) { this.panelCanSave = false; }
        },
        savePanel() {
            const form = this.panelForm();
            if (form) form.requestSubmit();
        },
        openPanel(url, title) {
            const u = new URL(url, location.origin);
            u.searchParams.set('embed', '1');
            this.panel = { url: u.toString(), title };
        },
        async closePanel() {
            this.panel = null;
            try {
                const r = await fetch(@json(route('admin.content-pages.feed-entries')), { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
                if (r.ok) this.feedEntries = await r.json();
            } catch (e) { /* the list is refreshed the next time the page is opened */ }
        },
        init() {
            this.$watch('page', () => this.dirty = true, { deep: true });
            window.addEventListener('beforeunload', (e) => { if (this.dirty) { e.preventDefault(); e.returnValue = ''; } });
            if (this.page.sections.length === 1) this.page.sections[0]._open = true;
        },
        show(type, field, item) {
            const fields = FIELDS[type] ?? ['title', 'subtitle', 'text', 'icon', 'image', 'list', 'link_url'];
            return item._more || fields.includes(field) || (field === 'image' && !!item.image);
        },
        // Names the first pages were written with.
        iconName(name) {
            return { 'file-bar-chart': 'file-chart-column', dollar: 'dollar-sign', pin: 'map-pin', airplane: 'plane' }[name] ?? (name || '');
        },
        iconSvg(name) {
            const pascal = this.iconName(name).replace(/(^|-)(\w)/g, (_, __, c) => c.toUpperCase());
            const node = window.lucide?.icons?.[pascal];
            if (!node) return '';
            const svg = window.lucide.createElement(node);
            svg.setAttribute('width', '100%');
            svg.setAttribute('height', '100%');
            return svg.outerHTML;
        },
        iconLabel(name) {
            if (!name) return 'Choose an icon';
            const known = this.iconName(name);
            const label = known.replace(/-/g, ' ');
            return this.icons.includes(known) ? label : label + ' (site icon)';
        },
        setIcon(item, name) {
            item.icon = name;
            if (name) item.image = '';
        },
        // A section with no heading is named after what is in it.
        title(section) {
            const entries = section.items.map(i => i.title).filter(Boolean);
            return section.heading || (entries.length ? entries.slice(0, 3).join(' · ') + (entries.length > 3 ? ' …' : '') : '')
                || (section.body || '').replace(/[*_\[\]]/g, '').slice(0, 80) || section.key || 'Untitled section';
        },
        heroIndex() {
            return this.page.sections.findIndex(s => s.show_on !== 'app');
        },
        matches(section) {
            const words = this.find.trim().toLowerCase();
            return !words || JSON.stringify(section).toLowerCase().includes(words);
        },
        found() {
            return this.page.sections.filter(s => !this.linkedGallery(s) && this.matches(s)).length;
        },
        // Pictures kept for one entry (section "pictures-<entry>") are edited on that entry.
        picturesFor(item) {
            return item.slug ? this.page.sections.find(s => s.type === 'gallery' && s.key === 'pictures-' + item.slug) ?? null : null;
        },
        linkedGallery(section) {
            if (section.type !== 'gallery' || !section.key.startsWith('pictures-')) return false;
            const slug = section.key.slice('pictures-'.length);
            return this.page.sections.some(s => s.items.some(i => i.slug === slug));
        },
        addPicture(list) {
            list.push(toItem({}));
        },
        itemsName(type) {
            return { faq: 'Questions', table: 'Rows', stats: 'Figures', links: 'Links', steps: 'Steps', list: 'Entries', gallery: 'Pictures', cards: 'Cards', contacts: 'Contacts' }[type] ?? 'Items';
        },
        openAll(open) { this.page.sections.forEach(s => s._open = open); },
        move(list, index, by) {
            const to = index + by;
            if (to < 0 || to >= list.length) return;
            const [entry] = list.splice(index, 1);
            list.splice(to, 0, entry);
        },
        addSection() {
            const section = toSection({ type: this.newType, key: 'section-' + Date.now().toString(36) });
            section._open = true;
            this.page.sections.push(section);
        },
        duplicate(index) {
            const copy = JSON.parse(JSON.stringify(this.page.sections[index]));
            copy._id = id(); copy.key = copy.key + '-copy';
            copy.items.forEach(i => i._id = id());
            this.page.sections.splice(index + 1, 0, copy);
        },
        removeSection(index) {
            if (confirm('Remove this section? You can undo it from the history after saving.')) this.page.sections.splice(index, 1);
        },
        addItem(section) {
            const item = toItem({});
            section.items.push(item);
        },
        preview(path) {
            if (/^https?:\/\//.test(path) || path.startsWith('/')) return path;
            return @json($imageBase) + '/' + path.replace(/^\/+/, '');
        },
        async upload(event, done, slot) {
            const file = event.target.files[0];
            if (!file) return;
            this.uploading = slot;
            const body = new FormData();
            body.append('file', file);
            try {
                const res = await fetch(@json(route('admin.content-pages.upload-image')), {
                    method: 'POST', body,
                    headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content, 'Accept': 'application/json' },
                });
                const json = await res.json();
                if (!res.ok) throw new Error(json.message || 'Upload failed');
                done(json.path);
            } catch (e) {
                alert('The picture could not be uploaded: ' + e.message);
            } finally {
                this.uploading = null;
                event.target.value = '';
            }
        },
        prepare(event) {
            const lines = (text) => (text || '').split('\n').map(l => l.trim()).filter(Boolean);
            // Editors may not have opened: every value is the stored text already.
            const data = {
                ...this.page,
                sections: this.page.sections.map(({ _id, _open, ...s }) => ({
                    ...s,
                    columns: (s.columns || '').split('|').map(c => c.trim()).filter(Boolean),
                    items: s.items.map(({ _id, _more, ...i }) => ({
                        ...i,
                        list: lines(i.list),
                        gallery: lines(i.gallery),
                        cells: (i.cells || '').trim() === '' ? [] : i.cells.split('|').map(c => c.trim()),
                    })),
                })),
            };
            const keys = data.sections.map(s => s.key.trim()).filter(Boolean);
            if (new Set(keys).size !== keys.length) {
                event.preventDefault();
                alert('Two sections have the same key. Each section needs its own key.');
                return;
            }
            this.$refs.json.value = JSON.stringify(data);
            this.dirty = false;
        },
    };
}
</script>
@endpush
