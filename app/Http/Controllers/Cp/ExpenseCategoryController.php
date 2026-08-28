<?php

namespace App\Http\Controllers\Cp;

use App\Http\Controllers\Controller;
use App\Models\ExpenseCategory;
use Illuminate\Http\Request;

class ExpenseCategoryController extends Controller
{
    public function index(string $scope)
    {
        [$fundSlug, $title] = $this->scopeMeta($scope);

        $categories = ExpenseCategory::query()
            ->where('fund_slug', $fundSlug)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->paginate(30);

        return view('cp.finance.expense-categories.index', compact('categories', 'scope', 'title', 'fundSlug'));
    }

    public function create(string $scope)
    {
        [$fundSlug, $title] = $this->scopeMeta($scope);

        return view('cp.finance.expense-categories.form', [
            'category' => new ExpenseCategory(['is_active' => true, 'fund_slug' => $fundSlug]),
            'scope' => $scope,
            'title' => $title,
            'fundSlug' => $fundSlug,
        ]);
    }

    public function store(Request $request, string $scope)
    {
        [$fundSlug, $title] = $this->scopeMeta($scope);
        ExpenseCategory::query()->create($this->validated($request, $fundSlug));

        return redirect()->route('cp.expense-categories.index', $scope)
            ->with('success', 'تم إضافة التصنيف.');
    }

    public function edit(string $scope, ExpenseCategory $expenseCategory)
    {
        [$fundSlug, $title] = $this->scopeMeta($scope);
        abort_unless($expenseCategory->fund_slug === $fundSlug, 404);

        return view('cp.finance.expense-categories.form', [
            'category' => $expenseCategory,
            'scope' => $scope,
            'title' => $title,
            'fundSlug' => $fundSlug,
        ]);
    }

    public function update(Request $request, string $scope, ExpenseCategory $expenseCategory)
    {
        [$fundSlug] = $this->scopeMeta($scope);
        abort_unless($expenseCategory->fund_slug === $fundSlug, 404);
        $expenseCategory->update($this->validated($request, $fundSlug));

        return redirect()->route('cp.expense-categories.index', $scope)
            ->with('success', 'تم التحديث.');
    }

    public function destroy(string $scope, ExpenseCategory $expenseCategory)
    {
        [$fundSlug] = $this->scopeMeta($scope);
        abort_unless($expenseCategory->fund_slug === $fundSlug, 404);

        if ($expenseCategory->expenses()->exists()) {
            $expenseCategory->update(['is_active' => false]);

            return back()->with('success', 'تم تعطيل التصنيف لأنه مستخدم في سجلات.');
        }

        $expenseCategory->delete();

        return back()->with('success', 'تم الحذف.');
    }

    /**
     * @return array{0: string, 1: string}
     */
    protected function scopeMeta(string $scope): array
    {
        if ($scope === 'work' && ! tenantBusinessEnabled()) {
            abort(404);
        }

        return match ($scope) {
            'personal' => ['family', 'تصنيفات المصروفات الشخصية'],
            'work' => ['business', 'تصنيفات مصروفات العمل'],
            default => abort(404),
        };
    }

    /**
     * @return array<string, mixed>
     */
    protected function validated(Request $request, string $fundSlug): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
        ]) + [
            'fund_slug' => $fundSlug,
            'sort_order' => (int) $request->input('sort_order', 0),
            'is_active' => $request->boolean('is_active', true),
        ];
    }
}
