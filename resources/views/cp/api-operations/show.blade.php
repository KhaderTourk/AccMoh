@extends('cp.layout')
@section('title', 'عملية API #'.$operation->id)
@section('content')
@php
    $tableNames = [
        'clients' => 'زبون', 'client_services' => 'خدمة زبون', 'client_goods_takes' => 'منتج مأخوذ',
        'cash_payments' => 'دفعة', 'ledger_entries' => 'قيد مالي', 'fund_transfers' => 'تحويل',
        'party_balance_memos' => 'رصيد مرحّل', 'family_members' => 'شخص', 'vendors' => 'موظف/مورد',
        'vendor_charges' => 'مستحق', 'service_types' => 'نوع خدمة', 'users' => 'مستخدم', 'roles' => 'دور',
        'role_permission' => 'صلاحيات دور', 'sync_operations' => 'عملية مزامنة',
    ];
    $opNames = ['created' => ['جديد', 'text-emerald-600'], 'updated' => ['تعديل', 'text-amber-600'], 'deleted' => ['حذف', 'text-rose-600'], 'pivot' => ['تعديل', 'text-amber-600']];
    $hidden = ['password', 'remember_token'];
@endphp
<div class="space-y-4">
    <div class="cp-toolbar">
        <div>
            <div class="text-lg font-semibold">
                <span class="inline-flex items-center rounded-md bg-indigo-100 text-indigo-700 dark:bg-indigo-900/40 dark:text-indigo-300 px-1.5 py-0.5 text-xs font-bold ml-1">API</span>
                {{ $operation->label() }}
            </div>
            <div class="text-sm text-slate-500">
                {{ $operation->user?->name ?? '—' }}@if($operation->token_name) ({{ $operation->token_name }})@endif
                · {{ format_date($operation->created_at, true) }}
                · <span dir="ltr" class="font-mono">{{ $operation->method }} {{ $operation->path }}</span>
            </div>
        </div>
        <div class="flex gap-2">
            <a href="{{ route('cp.api-operations.index') }}" class="cp-btn cp-btn-muted">رجوع للسجل</a>
            @unless($operation->isUndone())
                <form method="post" action="{{ route('cp.api-operations.undo', $operation) }}" onsubmit="return confirm('التراجع عن هذه العملية وإرجاع البيانات كما كانت قبلها؟')">
                    @csrf
                    <button class="cp-btn cp-btn-primary bg-rose-600 border-rose-600" @disabled($conflicts !== [])>
                        <span class="material-symbols-outlined">undo</span> تراجع عن العملية
                    </button>
                </form>
            @endunless
        </div>
    </div>

    @if($operation->isUndone())
        <div class="cp-flash cp-flash-success">تم التراجع عن هذه العملية {{ format_date($operation->undone_at, true) }}@if($operation->undoer) بواسطة {{ $operation->undoer->name }}@endif.</div>
    @elseif($conflicts !== [])
        <div class="cp-flash cp-flash-error">
            <div class="font-semibold mb-1">لا يمكن التراجع الآن:</div>
            <ul class="list-disc pr-5">@foreach($conflicts as $c)<li>{{ $c }}</li>@endforeach</ul>
            <div class="mt-1 text-xs">تراجع عن العمليات الأحدث التي غيّرت هذه السجلات أولاً.</div>
        </div>
    @endif

    <div class="rounded-2xl border bg-white dark:bg-slate-800 overflow-hidden">
        <table class="w-full text-sm text-right">
            <thead class="bg-slate-50 dark:bg-slate-700/50"><tr>
                <th class="px-3 py-2">النوع</th><th class="px-3 py-2">السجل</th><th class="px-3 py-2">الحقول المتغيرة (قبل ← بعد)</th>
            </tr></thead>
            <tbody class="divide-y dark:divide-slate-700">
            @foreach($operation->changes as $change)
                <tr class="align-top">
                    <td class="px-3 py-2 {{ $opNames[$change['op']][1] }}">{{ $opNames[$change['op']][0] }}</td>
                    <td class="px-3 py-2">{{ $tableNames[$change['table']] ?? $change['table'] }} @isset($change['key'])#{{ $change['key'] }}@endisset @isset($change['owner_id'])#{{ $change['owner_id'] }}@endisset</td>
                    <td class="px-3 py-2 text-xs">
                        @if($change['op'] === 'pivot')
                            [{{ implode(', ', $change['before']) }}] ← [{{ implode(', ', $change['after']) }}]
                        @else
                            @php
                                $before = $change['before'] ?? [];
                                $after = $change['after'] ?? [];
                                $keys = collect(array_keys($before + $after))->reject(fn ($k) => in_array($k, $hidden, true) || in_array($k, ['created_at', 'updated_at', 'tenant_id'], true));
                            @endphp
                            <dl class="grid grid-cols-[auto,1fr] gap-x-3 gap-y-0.5">
                                @foreach($keys as $k)
                                    @php($b = $before[$k] ?? null)
                                    @php($a = $after[$k] ?? null)
                                    @if($change['op'] !== 'updated' || (string) $b !== (string) $a)
                                        <dt class="text-slate-500 font-mono" dir="ltr">{{ $k }}</dt>
                                        <dd>
                                            @if($change['op'] === 'updated')<span class="line-through text-slate-400">{{ \Illuminate\Support\Str::limit((string) $b, 60) }}</span> ← @endif
                                            {{ \Illuminate\Support\Str::limit((string) ($change['op'] === 'deleted' ? $b : $a), 80) }}
                                        </dd>
                                    @endif
                                @endforeach
                            </dl>
                        @endif
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>

    @if($operation->request)
        <details class="rounded-2xl border bg-white dark:bg-slate-800 p-3">
            <summary class="cursor-pointer text-sm text-slate-600">البيانات المرسلة في الطلب</summary>
            <pre class="mt-2 text-xs overflow-x-auto" dir="ltr">{{ json_encode($operation->request, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
        </details>
    @endif
</div>
@endsection
