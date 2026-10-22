<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\FinanceException;
use App\Http\Controllers\Cp\ClientImportController as CpClientImportController;
use App\Models\Client;
use App\Services\Import\LegacyClientImportService;
use App\Services\Import\LegacyClientWorkbookParser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Throwable;

/**
 * Two steps like the CP: upload & preview (returns a token), then confirm with that token.
 */
class ClientImportController extends CpClientImportController
{
    public function preview(Request $request, LegacyClientWorkbookParser $parser): JsonResponse
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

        return response()->json([
            'token' => $token,
            'expires_in_minutes' => 120,
            'drafts' => $drafts,
        ]);
    }

    public function store(Request $request, LegacyClientImportService $importer): JsonResponse
    {
        $data = $request->validate([
            'token' => ['required', 'string'],
            'clients' => ['nullable', 'array'],
            'clients.*.skip' => ['nullable', 'boolean'],
            'clients.*.merge' => ['nullable', 'boolean'],
            'clients.*.client_name' => ['nullable', 'string', 'max:255'],
            'clients.*.company_name' => ['nullable', 'string', 'max:255'],
        ]);

        $drafts = Cache::get($this->cacheKey($data['token']));
        if (! is_array($drafts) || $drafts === []) {
            return response()->json(['message' => 'انتهت جلسة المعاينة. ارفع الملفات مرة أخرى.'], 422);
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

        return response()->json([
            'message' => "تم استيراد {$imported} زبون، {$services} خدمة، {$payments} دفعة.",
            'imported_clients' => $imported,
            'imported_services' => $services,
            'imported_payments' => $payments,
            'warnings' => $warnings,
            'errors' => $errors,
        ], $errors !== [] && $imported === 0 ? 422 : 200);
    }
}
