@include('admin.sales._tag', [
    'tag' => ['submitted' => 'pending', 'in_review' => 'review', 'approved' => 'success', 'declined' => 'declined', 'closed' => 'cancelled'][$status] ?? 'info',
    'text' => \App\Services\Booking\RefundRequests::STATUSES[$status] ?? ucfirst($status),
])
