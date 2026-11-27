@include('admin.sales._tag', [
    'tag' => ['ticketed' => 'success', 'pending_payment' => 'pending', 'expired' => 'abandoned', 'cancelled' => 'cancelled'][$status] ?? 'info',
    'text' => \App\Http\Controllers\Admin\FlightBookingController::STATUSES[$status] ?? ucfirst($status),
])
