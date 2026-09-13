<?php

namespace App\Http\Controllers\Cp;

use App\Exceptions\FinanceException;
use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Services\Import\LegacyClientImportService;
use App\Services\Import\LegacyClientWorkbookParser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Throwable;

class ClientImportController extends Controller
{
    public function create()
    {
        return view('cp.finance.clients.import');
    }

    public function preview(Request $request, LegacyClientWorkbookParser $parser)
    {
        $request->validate([
            'files' => ['required', 'array', 'min:1', 'max:40'],
            'files.*' => ['file', 'extensions:xlsx,xls,ods', 'max:10240'],
        ], [
            'files.required' => 'اختر ملف إكسيل واحداً على الأقل.',
            'files.*.extensions' => 'يُسمح بملفات Excel فقط (xlsx / xls).',
        ]);

        $drafts = [];
        foreach ($request->file('files') as $file) {
            $parsed = $parser->parse($file->getRealPath(), $file->getClientOriginalName());
            $existing = Client::query()
                ->where(function ($q) use ($parsed) {
                    $q->where('name', $parsed['client_name'])
                        ->orWhere('company_name', $parsed['client_name']);
                })
                ->first();
            $parsed['existing_client_id'] = $existing?->id;
            $parsed['existing_client_name'] = $existing?->name;
            $parsed['merge'] = (bool) $existing;
            $drafts[] = $parsed;
        }

        $token = (string) Str::uuid();
        Cache::put($this->cacheKey($token), $drafts, now()->addHours(2));

        return redirect()->route('cp.clients.import.review', ['token' => $token]);
    }

    public function review(Request $request)
    {
        $token = (string) $request->query('token', '');
        $drafts = Cache::get($this->cacheKey($token));
        if (! is_array($drafts) || $drafts === []) {
            return redirect()->route('cp.clients.import')
                ->with('error', 'انتهت جلسة المعاينة. ارفع الملفات مرة أخرى.');
        }

        return view('cp.finance.clients.import-preview', compact('drafts', 'token'));
    }

    public function store(Request $request, LegacyClientImportService $importer)
    {
        $data = $request->validate([
            'token' => ['required', 'string'],
            'clients' => ['required', 'array'],
            'clients.*.skip' => ['nullable', 'boolean'],
            'clients.*.merge' => ['nullable', 'boolean'],
            'clients.*.client_name' => ['nullable', 'string', 'max:255'],
            'clients.*.company_name' => ['nullable', 'string', 'max:255'],
        ]);

        $drafts = Cache::get($this->cacheKey($data['token']));
        if (! is_array($drafts) || $drafts === []) {
            return redirect()->route('cp.clients.import')
                ->with('error', 'انتهت جلسة المعاينة. ارفع الملفات مرة أخرى.');
        }

        $imported = 0;
        $services = 0;
        $payments = 0;
        $warnings = [];
        $errors = [];

        foreach ($drafts as $index => $draft) {
            $form = $data['clients'][$index] ?? [];
            if ($request->boolean("clients.$index.skip")) {
                continue;
            }
            if (($draft['errors'] ?? []) !== []) {
                $errors[] = ($draft['filename'] ?? 'ملف').': '.implode(' ', $draft['errors']);

                continue;
            }

            $draft['client_name'] = trim((string) ($form['client_name'] ?? $draft['client_name']));
            $companyName = trim((string) ($form['company_name'] ?? $draft['company_name'] ?? ''));
            $draft['company_name'] = $companyName !== '' ? $companyName : null;
            $draft['merge'] = $request->boolean("clients.$index.merge", (bool) ($draft['existing_client_id'] ?? false));

            try {
                $result = $importer->importOne($draft);
                $imported++;
                $services += $result['services'];
                $payments += $result['payments'];
                foreach ($result['warnings'] as $warning) {
                    $warnings[] = $result['client']->name.': '.$warning;
                }
            } catch (FinanceException $e) {
                $errors[] = ($draft['client_name'] ?: $draft['filename']).': '.$e->getMessage();
            } catch (Throwable $e) {
                $errors[] = ($draft['client_name'] ?: $draft['filename']).': تعذر الاستيراد ('.$e->getMessage().')';
            }
        }

        Cache::forget($this->cacheKey($data['token']));

        $message = "تم استيراد {$imported} زبون، {$services} خدمة، {$payments} دفعة.";
        if ($warnings !== []) {
            $message .= ' تنبيهات: '.implode(' | ', array_slice($warnings, 0, 8));
        }

        $redirect = redirect()->route('cp.clients.index')->with('success', $message);
        if ($errors !== []) {
            $redirect->with('error', implode(' | ', $errors));
        }

        return $redirect;
    }

    protected function cacheKey(string $token): string
    {
        return 'client-excel-import:'.$token;
    }
}
