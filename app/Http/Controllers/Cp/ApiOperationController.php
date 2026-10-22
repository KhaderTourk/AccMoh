<?php

namespace App\Http\Controllers\Cp;

use App\Exceptions\FinanceException;
use App\Http\Controllers\Controller;
use App\Models\ApiOperation;
use App\Models\User;
use App\Services\Api\ApiJournal;
use App\Support\DateRange;
use Illuminate\Http\Request;

class ApiOperationController extends Controller
{
    public function index(Request $request)
    {
        abort_unless(auth()->user()?->canAccess('finance'), 403, 'ليس لديك صلاحية للوصول إلى هذه الصفحة.');
        [$from, $to] = DateRange::fromRequest($request);

        $operations = ApiOperation::query()
            ->with(['user', 'undoer'])
            ->when($request->status === 'pending', fn ($q) => $q->pending())
            ->when($request->status === 'undone', fn ($q) => $q->whereNotNull('undone_at'))
            ->when($request->user_id, fn ($q, $id) => $q->where('user_id', $id))
            ->when($from, fn ($q) => $q->whereDate('created_at', '>=', $from))
            ->when($to, fn ($q) => $q->whereDate('created_at', '<=', $to))
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString();

        $users = User::query()->where('tenant_id', auth()->user()->tenant_id)->orderBy('name')->get(['id', 'name']);

        return view('cp.api-operations.index', compact('operations', 'users', 'from', 'to'));
    }

    public function show(ApiOperation $apiOperation, ApiJournal $journal)
    {
        $this->authorizeOperation($apiOperation);

        return view('cp.api-operations.show', [
            'operation' => $apiOperation->load(['user', 'undoer']),
            'conflicts' => $apiOperation->isUndone() ? [] : $journal->conflicts($apiOperation),
        ]);
    }

    public function undo(ApiOperation $apiOperation, ApiJournal $journal)
    {
        $this->authorizeOperation($apiOperation);

        try {
            $journal->undo($apiOperation, (int) auth()->id());
        } catch (FinanceException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'تم التراجع عن العملية وإرجاع البيانات كما كانت قبلها.');
    }

    protected function authorizeOperation(ApiOperation $operation): void
    {
        $user = auth()->user();
        abort_unless($user?->canAccess('finance'), 403, 'ليس لديك صلاحية للوصول إلى هذه الصفحة.');

        $touchesUsers = collect($operation->changes)->contains(fn ($c) => in_array($c['table'], ['users', 'roles', 'role_permission'], true));
        abort_if($touchesUsers && ! $user->canAccess('users'), 403, 'ليس لديك صلاحية إدارة المستخدمين.');
    }
}
