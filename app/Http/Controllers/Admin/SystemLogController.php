<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * API, App & Login Logs: what the backend did (each request, Zenith and
 * NAB call), what the apps reported, and every sign-in and security event,
 * searchable in one place. Secrets were redacted before they were stored.
 */
class SystemLogController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($request->user()?->can('system.logs'), 403, 'You do not have access to the logs.');
        $tab = in_array($request->input('tab'), ['api', 'app', 'logins'], true) ? $request->input('tab') : 'api';
        $search = trim((string) $request->input('search'));
        $level = $request->input('level');
        $since = CarbonImmutable::now()->subDay()->utc()->format('Y-m-d H:i:s.uP');

        $customers = fn ($q, string $col) => $q->leftJoin('customers as c', 'c.id', '=', $col)->addSelect(DB::raw('coalesce(c.email, c.zenith_login) as customer_email'));

        if ($tab === 'api') {
            $query = DB::table('server_logs as l')->select('l.*');
            $customers($query, 'l.customer_id');
            if (in_array($level, ['debug', 'info', 'warn', 'error'], true)) {
                $query->where('l.level', $level);
            }
            if ($source = $request->input('source')) {
                $query->where('l.source', $source);
            }
            if ($search !== '') {
                $like = '%'.mb_strtolower($search).'%';
                $query->where(fn ($q) => $q->whereRaw('lower(l.message) like ?', [$like])->orWhereRaw('lower(coalesce(l.path, \'\')) like ?', [$like])
                    ->orWhereRaw('lower(coalesce(c.zenith_login, \'\')) like ?', [$like])->orWhereRaw("lower(coalesce(c.email, '')) like ?", [$like])->orWhereRaw('cast(l.request_id as text) = ?', [$search]));
            }
            $rows = $query->orderByDesc('l.logged_at')->orderByDesc('l.id')->paginate(50)->withQueryString();
            $sources = DB::table('server_logs')->distinct()->orderBy('source')->pluck('source');
            $summary = DB::table('server_logs')->where('logged_at', '>=', $since)->select('level', DB::raw('count(*) as n'))->groupBy('level')->pluck('n', 'level');
        } elseif ($tab === 'app') {
            $query = DB::table('app_logs as l')->select('l.*');
            $customers($query, 'l.customer_id');
            if (in_array($level, ['debug', 'info', 'warn', 'error'], true)) {
                $query->where('l.level', $level);
            }
            if ($search !== '') {
                $like = '%'.mb_strtolower($search).'%';
                $query->where(fn ($q) => $q->whereRaw('lower(l.event) like ?', [$like])->orWhereRaw('lower(coalesce(l.message, \'\')) like ?', [$like])
                    ->orWhere('l.device_id', $search)->orWhereRaw('lower(coalesce(c.zenith_login, \'\')) like ?', [$like])->orWhereRaw("lower(coalesce(c.email, '')) like ?", [$like]));
            }
            $rows = $query->orderByDesc('l.received_at')->orderByDesc('l.id')->paginate(50)->withQueryString();
            $sources = collect();
            $summary = DB::table('app_logs')->where('received_at', '>=', $since)->select('level', DB::raw('count(*) as n'))->groupBy('level')->pluck('n', 'level');
        } else {
            $query = DB::table('auth_events as l')->select('l.*');
            $customers($query, 'l.customer_id');
            if ($level === 'failed') {
                $query->where('l.success', false);
            } elseif ($level === 'success') {
                $query->where('l.success', true);
            }
            if ($event = $request->input('source')) {
                $query->where('l.event', $event);
            }
            if ($search !== '') {
                $like = '%'.mb_strtolower($search).'%';
                $query->where(fn ($q) => $q->whereRaw('lower(coalesce(l.login, \'\')) like ?', [$like])->orWhere('l.ip', $search)->orWhereRaw('lower(coalesce(l.detail, \'\')) like ?', [$like]));
            }
            $rows = $query->orderByDesc('l.created_at')->orderByDesc('l.id')->paginate(50)->withQueryString();
            $sources = DB::table('auth_events')->distinct()->orderBy('event')->pluck('event');
            $summary = DB::table('auth_events')->where('created_at', '>=', $since)->select('success', DB::raw('count(*) as n'))->groupBy('success')->pluck('n', 'success');
        }

        return view('admin.system_logs.index', compact('tab', 'rows', 'sources', 'summary'));
    }
}
