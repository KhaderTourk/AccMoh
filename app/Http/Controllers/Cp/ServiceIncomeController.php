<?php

namespace App\Http\Controllers\Cp;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Cp\Concerns\LoadsFinanceLookups;
use App\Models\ClientService;
use App\Models\ServiceGroup;
use App\Models\ServiceType;
use App\Support\DateRange;
use Illuminate\Http\Request;

/** تقرير دخل الخدمات حسب المجموعة والخدمة، مفصول تصميم/تنفيذ/الاثنين. */
class ServiceIncomeController extends Controller
{
    use LoadsFinanceLookups;

    public const COLS = ['design', 'execution', 'both', 'none'];

    public function index(Request $request)
    {
        [$from, $to] = $this->dateRange($request);

        $sums = ClientService::query()
            ->tap(fn ($q) => DateRange::constrain($q, 'service_date', $from, $to))
            ->selectRaw('service_type_id, work_phase, SUM(amount) as total, COUNT(*) as n')
            ->groupBy('service_type_id', 'work_phase')
            ->get();

        // الخدمات الموقوفة والمحذوفة بتضل بالتقرير لأن حركاتها مربوطة برقم الخدمة.
        $types = ServiceType::withTrashed()->get()->keyBy('id');
        $groups = ServiceGroup::query()->orderBy('sort_order')->orderBy('name')->get();

        $rows = [];
        foreach ($sums as $s) {
            $key = $s->service_type_id ?: 0;
            $type = $types[$key] ?? null;
            $rows[$key] ??= [
                'name' => $type?->name ?? 'خدمات بدون نوع («أخرى…»)',
                'group' => $type?->service_group_id,
                'phases' => (bool) $type?->has_phases,
                'off' => $type && (! $type->is_active || $type->trashed()),
                'design' => 0.0, 'execution' => 0.0, 'both' => 0.0, 'none' => 0.0, 'total' => 0.0, 'n' => 0,
            ];
            $col = in_array($s->work_phase, ['design', 'execution', 'both'], true) ? $s->work_phase : 'none';
            $rows[$key][$col] += (float) $s->total;
            $rows[$key]['total'] += (float) $s->total;
            $rows[$key]['n'] += (int) $s->n;
        }

        $blank = fn ($name) => ['name' => $name, 'rows' => [], 'design' => 0.0, 'execution' => 0.0, 'both' => 0.0, 'none' => 0.0, 'total' => 0.0];
        $sections = [];
        foreach ($groups as $g) {
            $sections[$g->id] = $blank($g->name);
        }
        $sections[0] = $blank('بدون مجموعة');
        foreach ($rows as $r) {
            $gid = isset($sections[$r['group']]) ? $r['group'] : 0;
            $sections[$gid]['rows'][] = $r;
            foreach ([...self::COLS, 'total'] as $c) {
                $sections[$gid][$c] += $r[$c];
            }
        }
        $sections = collect($sections)->filter(fn ($s) => $s['rows'])
            ->map(function ($s) {
                usort($s['rows'], fn ($a, $b) => $b['total'] <=> $a['total']);

                return $s;
            })->values();

        $grand = [];
        foreach ([...self::COLS, 'total'] as $c) {
            $grand[$c] = $sections->sum($c);
        }

        return view('cp.finance.reports.services', [
            'sections' => $sections,
            'grand' => $grand,
            'from' => $from,
            'to' => $to,
        ]);
    }
}
