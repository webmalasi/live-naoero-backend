<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\CheckInTime;
use App\Models\DangerousGoodsItem;
use App\Models\TravelInfoCard;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TravelInfoController extends Controller
{
    /**
     * Display unified Travel Info operations console.
     */
    public function index(Request $request): View
    {
        $checkInTimes = CheckInTime::query()->ordered()->get();
        $dangerousGoods = DangerousGoodsItem::query()->ordered()->get();
        $travelCards = TravelInfoCard::query()->ordered()->get();

        $stats = [
            'total_ports' => $checkInTimes->count(),
            'active_ports' => $checkInTimes->where('is_active', true)->count(),
            'total_dg' => $dangerousGoods->count(),
            'active_dg' => $dangerousGoods->where('is_active', true)->count(),
            'total_cards' => $travelCards->count(),
            'active_cards' => $travelCards->where('is_active', true)->count(),
        ];

        return view('admin.travel_info.index', compact('checkInTimes', 'dangerousGoods', 'travelCards', 'stats'));
    }

    // ----------------------------------------------------
    // CHECK-IN TIMES (Port Timetable) CRUD
    // ----------------------------------------------------

    public function createCheckIn(): View
    {
        return view('admin.travel_info.create_check_in');
    }

    public function storeCheckIn(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'port' => ['required', 'string', 'max:100'],
            'airport_code' => ['nullable', 'string', 'max:10'],
            'opens' => ['required', 'string', 'max:100'],
            'closes' => ['required', 'string', 'max:100'],
            'is_highlighted' => ['nullable', 'boolean'],
            'notes' => ['nullable', 'string', 'max:500'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $validated['is_highlighted'] = $request->boolean('is_highlighted');
        $validated['is_active'] = $request->boolean('is_active');
        $validated['sort_order'] = (int)($validated['sort_order'] ?? 0);

        $checkIn = CheckInTime::create($validated);

        ActivityLog::log('created', 'travel_info', "Added Check-in port '{$checkIn->port}'");

        return redirect()->route('admin.travel-info.index')->with('success', "Port '{$checkIn->port}' created successfully.");
    }

    public function editCheckIn(CheckInTime $checkInTime): View
    {
        return view('admin.travel_info.edit_check_in', compact('checkInTime'));
    }

    public function updateCheckIn(Request $request, CheckInTime $checkInTime): RedirectResponse
    {
        $validated = $request->validate([
            'port' => ['required', 'string', 'max:100'],
            'airport_code' => ['nullable', 'string', 'max:10'],
            'opens' => ['required', 'string', 'max:100'],
            'closes' => ['required', 'string', 'max:100'],
            'is_highlighted' => ['nullable', 'boolean'],
            'notes' => ['nullable', 'string', 'max:500'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $validated['is_highlighted'] = $request->boolean('is_highlighted');
        $validated['is_active'] = $request->boolean('is_active');
        $validated['sort_order'] = (int)($validated['sort_order'] ?? $checkInTime->sort_order);

        $checkInTime->update($validated);

        ActivityLog::log('updated', 'travel_info', "Updated Check-in port '{$checkInTime->port}'");

        return redirect()->route('admin.travel-info.index')->with('success', "Port '{$checkInTime->port}' updated successfully.");
    }

    public function toggleCheckIn(CheckInTime $checkInTime): RedirectResponse
    {
        $checkInTime->update(['is_active' => !$checkInTime->is_active]);
        $status = $checkInTime->is_active ? 'activated' : 'deactivated';
        ActivityLog::log('updated', 'travel_info', "Port '{$checkInTime->port}' was {$status}");

        return back()->with('success', "Port '{$checkInTime->port}' {$status}.");
    }

    public function destroyCheckIn(CheckInTime $checkInTime): RedirectResponse
    {
        $port = $checkInTime->port;
        $checkInTime->delete();
        ActivityLog::log('deleted', 'travel_info', "Deleted Check-in port '{$port}'");

        return redirect()->route('admin.travel-info.index')->with('success', "Port '{$port}' deleted successfully.");
    }

    // ----------------------------------------------------
    // DANGEROUS GOODS CRUD
    // ----------------------------------------------------

    public function createDangerousGoods(): View
    {
        return view('admin.travel_info.create_dangerous_goods');
    }

    public function storeDangerousGoods(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'item_name' => ['required', 'string', 'max:255'],
            'carry_on' => ['nullable', 'string', 'max:100'],
            'checked' => ['nullable', 'string', 'max:100'],
            'approval_required' => ['nullable', 'string', 'max:255'],
            'category' => ['required', 'string', 'in:quick_reference,conditional,prohibited,topic'],
            'icon' => ['nullable', 'string', 'max:50'],
            'details' => ['nullable', 'string', 'max:1000'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $validated['is_active'] = $request->boolean('is_active');
        $validated['sort_order'] = (int)($validated['sort_order'] ?? 0);

        $item = DangerousGoodsItem::create($validated);

        ActivityLog::log('created', 'travel_info', "Added Dangerous Goods item '{$item->item_name}'");

        return redirect()->route('admin.travel-info.index')->with('success', "Item '{$item->item_name}' created successfully.");
    }

    public function editDangerousGoods(DangerousGoodsItem $dangerousGood): View
    {
        return view('admin.travel_info.edit_dangerous_goods', compact('dangerousGood'));
    }

    public function updateDangerousGoods(Request $request, DangerousGoodsItem $dangerousGood): RedirectResponse
    {
        $validated = $request->validate([
            'item_name' => ['required', 'string', 'max:255'],
            'carry_on' => ['nullable', 'string', 'max:100'],
            'checked' => ['nullable', 'string', 'max:100'],
            'approval_required' => ['nullable', 'string', 'max:255'],
            'category' => ['required', 'string', 'in:quick_reference,conditional,prohibited,topic'],
            'icon' => ['nullable', 'string', 'max:50'],
            'details' => ['nullable', 'string', 'max:1000'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $validated['is_active'] = $request->boolean('is_active');
        $validated['sort_order'] = (int)($validated['sort_order'] ?? $dangerousGood->sort_order);

        $dangerousGood->update($validated);

        ActivityLog::log('updated', 'travel_info', "Updated Dangerous Goods item '{$dangerousGood->item_name}'");

        return redirect()->route('admin.travel-info.index')->with('success', "Item '{$dangerousGood->item_name}' updated successfully.");
    }

    public function toggleDangerousGoods(DangerousGoodsItem $dangerousGood): RedirectResponse
    {
        $dangerousGood->update(['is_active' => !$dangerousGood->is_active]);
        $status = $dangerousGood->is_active ? 'activated' : 'deactivated';
        ActivityLog::log('updated', 'travel_info', "Item '{$dangerousGood->item_name}' was {$status}");

        return back()->with('success', "Item '{$dangerousGood->item_name}' {$status}.");
    }

    public function destroyDangerousGoods(DangerousGoodsItem $dangerousGood): RedirectResponse
    {
        $name = $dangerousGood->item_name;
        $dangerousGood->delete();
        ActivityLog::log('deleted', 'travel_info', "Deleted Dangerous Goods item '{$name}'");

        return redirect()->route('admin.travel-info.index')->with('success', "Item '{$name}' deleted successfully.");
    }

    // ----------------------------------------------------
    // TRAVEL INFO CARDS CRUD
    // ----------------------------------------------------

    public function createCard(): View
    {
        return view('admin.travel_info.create_card');
    }

    public function storeCard(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string', 'max:500'],
            'icon' => ['nullable', 'string', 'max:50'],
            'route_url' => ['nullable', 'string', 'max:255'],
            'has_link' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $validated['has_link'] = $request->boolean('has_link');
        $validated['is_active'] = $request->boolean('is_active');
        $validated['sort_order'] = (int)($validated['sort_order'] ?? 0);

        $card = TravelInfoCard::create($validated);

        ActivityLog::log('created', 'travel_info', "Added Travel Info Card '{$card->title}'");

        return redirect()->route('admin.travel-info.index')->with('success', "Card '{$card->title}' created successfully.");
    }

    public function editCard(TravelInfoCard $travelInfoCard): View
    {
        return view('admin.travel_info.edit_card', compact('travelInfoCard'));
    }

    public function updateCard(Request $request, TravelInfoCard $travelInfoCard): RedirectResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string', 'max:500'],
            'icon' => ['nullable', 'string', 'max:50'],
            'route_url' => ['nullable', 'string', 'max:255'],
            'has_link' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $validated['has_link'] = $request->boolean('has_link');
        $validated['is_active'] = $request->boolean('is_active');
        $validated['sort_order'] = (int)($validated['sort_order'] ?? $travelInfoCard->sort_order);

        $travelInfoCard->update($validated);

        ActivityLog::log('updated', 'travel_info', "Updated Travel Info Card '{$travelInfoCard->title}'");

        return redirect()->route('admin.travel-info.index')->with('success', "Card '{$travelInfoCard->title}' updated successfully.");
    }

    public function toggleCard(TravelInfoCard $travelInfoCard): RedirectResponse
    {
        $travelInfoCard->update(['is_active' => !$travelInfoCard->is_active]);
        $status = $travelInfoCard->is_active ? 'activated' : 'deactivated';
        ActivityLog::log('updated', 'travel_info', "Card '{$travelInfoCard->title}' was {$status}");

        return back()->with('success', "Card '{$travelInfoCard->title}' {$status}.");
    }

    public function destroyCard(TravelInfoCard $travelInfoCard): RedirectResponse
    {
        $title = $travelInfoCard->title;
        $travelInfoCard->delete();
        ActivityLog::log('deleted', 'travel_info', "Deleted Travel Info Card '{$title}'");

        return redirect()->route('admin.travel-info.index')->with('success', "Card '{$title}' deleted successfully.");
    }
}
