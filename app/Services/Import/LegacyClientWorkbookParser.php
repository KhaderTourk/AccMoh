<?php

namespace App\Services\Import;

use App\Support\Money;
use Carbon\Carbon;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Throwable;

class LegacyClientWorkbookParser
{
    protected ?int $workbookYear = null;

    /**
     * @return array{
     *     filename: string,
     *     client_name: string,
     *     company_name: ?string,
     *     phone: ?string,
     *     notes: ?string,
     *     services: list<array<string, mixed>>,
     *     payments: list<array<string, mixed>>,
     *     warnings: list<string>,
     *     errors: list<string>
     * }
     */
    public function parse(string $path, string $originalName): array
    {
        $warnings = [];
        $errors = [];
        $filename = pathinfo($originalName, PATHINFO_FILENAME) ?: $originalName;
        $fileMeta = $this->parseFilename($filename);
        $this->workbookYear = $fileMeta['year'] ?? (int) now()->format('Y');

        try {
            $spreadsheet = IOFactory::load($path);
        } catch (Throwable $e) {
            return $this->emptyResult($fileMeta['name'], ["تعذر قراءة الملف: {$e->getMessage()}"]);
        }

        $classified = $this->classifySheets($spreadsheet);
        $meta = $this->extractClientMeta($classified['statement'], $fileMeta['name']);

        $payments = $this->parsePayments($classified['payments'], $warnings);
        $totals = $this->parseTotals($classified['totals'], $warnings);
        $sheetServices = [];
        foreach ($classified['services'] as $title => $sheet) {
            foreach ($this->parseServiceSheet($sheet, $title, $warnings) as $row) {
                $sheetServices[] = $row;
            }
        }
        $statementServices = $this->parseStatementServices($classified['statement'], $warnings);

        $services = $this->finalizeServices($sheetServices, $statementServices, $totals, $warnings);

        if ($services === [] && $payments === []) {
            $errors[] = 'لم يُعثر على خدمات أو دفعات يمكن استيرادها.';
        }

        return [
            'filename' => $originalName,
            'client_name' => $meta['name'],
            'company_name' => $meta['company'],
            'phone' => $meta['phone'],
            'notes' => $meta['notes'],
            'services' => $services,
            'payments' => $payments,
            'warnings' => array_values(array_unique($warnings)),
            'errors' => $errors,
        ];
    }

    /**
     * @return array{statement: ?Worksheet, payments: ?Worksheet, totals: ?Worksheet, services: array<string, Worksheet>}
     */
    protected function classifySheets(Spreadsheet $spreadsheet): array
    {
        $result = [
            'statement' => null,
            'payments' => null,
            'totals' => null,
            'services' => [],
        ];

        foreach ($spreadsheet->getWorksheetIterator() as $sheet) {
            $name = trim((string) $sheet->getTitle());
            $kind = $this->sheetKind($name);
            if ($kind === 'statement' && $result['statement'] === null) {
                $result['statement'] = $sheet;

                continue;
            }
            if ($kind === 'payments' && $result['payments'] === null) {
                $result['payments'] = $sheet;

                continue;
            }
            if ($kind === 'totals' && $result['totals'] === null) {
                $result['totals'] = $sheet;

                continue;
            }
            if ($kind === 'service') {
                $result['services'][$name] = $sheet;
            }
        }

        return $result;
    }

    protected function sheetKind(string $name): string
    {
        $normalized = $this->normalize($name);
        if ($this->containsAny($normalized, ['كشف حساب', 'كشف الحساب', 'statement', 'account'])) {
            return 'statement';
        }
        if ($this->containsAny($normalized, ['دفعات', 'الدفعات', 'payments', 'payment'])) {
            return 'payments';
        }
        if ($this->containsAny($normalized, ['المجموع', 'مجموع', 'totals', 'total', 'summary'])) {
            return 'totals';
        }

        return 'service';
    }

    /**
     * @return array{name: string, year: ?int}
     */
    public function parseFilename(string $filename): array
    {
        $year = null;
        if (preg_match('/(20\d{2}|19\d{2})/u', $filename, $match)) {
            $year = (int) $match[1];
        }

        $name = $filename;
        $name = preg_replace('/(20\d{2}|19\d{2})/u', ' ', $name) ?? $name;
        $name = preg_replace('/كشف\s*الحساب|كشف\s*حساب/u', ' ', $name) ?? $name;
        $name = preg_replace('/[\-–_]+/u', ' ', $name) ?? $name;
        $name = trim(preg_replace('/\s+/u', ' ', $name) ?? $name);

        return [
            'name' => $name !== '' ? $name : trim($filename),
            'year' => $year,
        ];
    }

    /**
     * @return array{name: string, company: ?string, phone: ?string, notes: ?string}
     */
    protected function extractClientMeta(?Worksheet $sheet, string $filename): array
    {
        $meta = [
            'name' => trim($filename),
            'company' => null,
            'phone' => null,
            'notes' => null,
        ];

        if (! $sheet) {
            return $meta;
        }

        $maxRow = min(12, (int) $sheet->getHighestDataRow());
        $maxCol = min(8, Coordinate::columnIndexFromString($sheet->getHighestDataColumn()));

        for ($row = 1; $row <= $maxRow; $row++) {
            for ($col = 1; $col <= $maxCol; $col++) {
                $label = $this->normalize($this->cellString($sheet, $row, $col));
                $value = $this->cellString($sheet, $row, $col + 1);
                if ($value === '') {
                    continue;
                }
                if ($this->containsAny($label, ['اسم العميل', 'اسم الزبون', 'العميل', 'الزبون', 'الاسم']) && $meta['name'] === trim($filename)) {
                    $meta['name'] = $value;
                } elseif ($this->containsAny($label, ['الشركة', 'الجهه', 'الجهة', 'المؤسسة'])) {
                    $meta['company'] = $value;
                } elseif ($this->containsAny($label, ['هاتف', 'جوال', 'موبايل', 'phone'])) {
                    $meta['phone'] = $value;
                }
            }
        }

        return $meta;
    }

    /**
     * @param  list<string>  $warnings
     * @return list<array{payment_date: string, amount: string, currency_code: string, payment_method_hint: string, notes: ?string}>
     */
    protected function parsePayments(?Worksheet $sheet, array &$warnings): array
    {
        if (! $sheet) {
            return [];
        }

        $map = $this->findHeaderMap($sheet, [
            'date' => ['تاريخ الدفعة', 'تاريخ الدفع', 'التاريخ', 'تاريخ', 'date'],
            'currency' => ['نوع العملة', 'العملة', 'عملة', 'currency'],
            'amount' => ['المجموع بالشيكل', 'المجموع بالشيقل', 'المبلغ بالشيكل', 'المجموع', 'المبلغ', 'السعر', 'amount', 'total'],
            'notes' => ['طريقة الدفع', 'الملاحظات', 'ملاحظات', 'notes', 'method'],
        ]);

        if ($map === null) {
            $warnings[] = 'صفحة الدفعات موجودة لكن لم يُتعرف على عناوين الأعمدة.';

            return [];
        }

        $rows = [];
        $highest = (int) $sheet->getHighestDataRow();
        for ($row = $map['row'] + 1; $row <= $highest; $row++) {
            if ($this->rowIsEmpty($sheet, $row)) {
                continue;
            }
            $dateRaw = $this->cellValue($sheet, $row, $map['cols']['date'] ?? 0);
            $amountRaw = $this->cellValue($sheet, $row, $map['cols']['amount'] ?? 0);
            $currencyRaw = $this->cellString($sheet, $row, $map['cols']['currency'] ?? 0);
            $notes = $this->cellString($sheet, $row, $map['cols']['notes'] ?? 0);

            if ($this->isTotalLabel($this->cellString($sheet, $row, 1)) || $this->isTotalLabel((string) $dateRaw)) {
                continue;
            }

            $date = $this->parseDate($dateRaw, $sheet, $row, $map['cols']['date'] ?? 0);
            $amount = $this->parseAmount($amountRaw);
            if ($this->rowLooksLikeTotal($sheet, $row)) {
                continue;
            }
            if ($amount === null || ! Money::isPositive($amount)) {
                continue;
            }
            if ($date === null) {
                $warnings[] = "دفعة بمبلغ {$amount} بدون تاريخ في الصف {$row} — تم تجاهلها.";

                continue;
            }

            $rows[] = [
                'payment_date' => $date,
                'amount' => $amount,
                'currency_code' => $this->parseCurrencyCode($currencyRaw, true),
                'payment_method_hint' => $notes,
                'notes' => $notes !== '' ? $notes : null,
            ];
        }

        return $rows;
    }

    /**
     * @param  list<string>  $warnings
     * @return array<string, string>
     */
    protected function parseTotals(?Worksheet $sheet, array &$warnings): array
    {
        if (! $sheet) {
            return [];
        }

        $map = $this->findHeaderMap($sheet, [
            'service' => ['الوصف', 'وصف', 'نوع الخدمة', 'الخدمة', 'اسم الخدمة', 'description'],
            'amount' => ['المجموع', 'المبلغ', 'السعر', 'الاجمالي', 'الإجمالي', 'total', 'amount'],
        ]);

        $totals = [];
        $startRow = $map['row'] ?? 1;
        $serviceCol = $map['cols']['service'] ?? 1;
        $amountCol = $map['cols']['amount'] ?? 2;
        $highest = (int) $sheet->getHighestDataRow();

        for ($row = ($map ? $startRow + 1 : 1); $row <= $highest; $row++) {
            $title = $this->cellString($sheet, $row, $serviceCol);
            $amount = $this->parseAmount($this->cellValue($sheet, $row, $amountCol));
            if ($title === '' || $amount === null) {
                continue;
            }
            if ($this->isTotalLabel($title) || $this->isPaymentSummaryLabel($title)) {
                continue;
            }
            if (! Money::isPositive($amount)) {
                continue;
            }
            $totals[$this->normalize($title)] = $amount;
            $totals['_labels'][$this->normalize($title)] = $title;
        }

        if ($totals === []) {
            $warnings[] = 'تعذر قراءة إجماليات الخدمات من صفحة المجموع.';
        }

        return $totals;
    }

    /**
     * @param  list<string>  $warnings
     * @return list<array{title: string, service_date: ?string, amount: ?string, notes: ?string, source: string}>
     */
    protected function parseServiceSheet(Worksheet $sheet, string $title, array &$warnings): array
    {
        $map = $this->findHeaderMap($sheet, [
            'date' => ['تاريخ الخدمة', 'التاريخ', 'تاريخ', 'الشهر', 'date', 'month'],
            'amount' => ['المجموع بالشيكل', 'المجموع بالشيقل', 'المبلغ بالشيكل', 'المجموع', 'المبلغ', 'القيمة', 'amount', 'total'],
            'usd' => ['دولار', 'usd'],
            'rate' => ['سعر الدولار', 'سعر التحويل'],
            'notes' => ['الملاحظات', 'ملاحظات', 'التفاصيل', 'notes', 'detail'],
        ]);

        $rows = [];
        $highest = (int) $sheet->getHighestDataRow();
        $start = $map ? $map['row'] + 1 : 1;
        $dateCol = $map['cols']['date'] ?? 1;
        $amountCol = $map['cols']['amount'] ?? 2;
        $notesCol = $map['cols']['notes'] ?? 0;
        $usdCol = $map['cols']['usd'] ?? 0;
        $rateCol = $map['cols']['rate'] ?? 0;

        for ($row = $start; $row <= $highest; $row++) {
            if ($this->rowIsEmpty($sheet, $row) || $this->rowLooksLikeTotal($sheet, $row)) {
                continue;
            }
            $first = $this->cellString($sheet, $row, 1);
            if ($this->normalize($first) === $this->normalize($title)) {
                continue;
            }

            $date = $this->parseDate($this->cellValue($sheet, $row, $dateCol), $sheet, $row, $dateCol);
            $amount = $this->parseAmount($this->cellValue($sheet, $row, $amountCol));
            if ($amount !== null && ! Money::isPositive($amount)) {
                continue;
            }
            if ($date === null && $amount === null) {
                continue;
            }
            $notes = $notesCol ? $this->cellString($sheet, $row, $notesCol) : '';
            $usd = $usdCol ? $this->parseAmount($this->cellValue($sheet, $row, $usdCol)) : null;
            $rate = $rateCol ? $this->parseAmount($this->cellValue($sheet, $row, $rateCol)) : null;
            if ($usd && Money::isPositive($usd)) {
                $fx = rtrim(rtrim($usd, '0'), '.');
                if ($rate && Money::isPositive($rate)) {
                    $fx .= ' $ × '.rtrim(rtrim($rate, '0'), '.');
                }
                $notes = trim($notes === '' ? $fx : $notes.' — '.$fx);
            }

            $rows[] = [
                'title' => $title,
                'service_date' => $date,
                'amount' => $amount,
                'source_amount' => ($usd && Money::isPositive($usd)) ? $usd : null,
                'exchange_rate' => ($rate && Money::isPositive($rate)) ? $rate : null,
                'notes' => $notes !== '' ? $notes : null,
                'source' => 'sheet',
            ];
        }

        return $rows;
    }

    /**
     * @param  list<string>  $warnings
     * @return list<array{title: string, service_date: ?string, amount: ?string, notes: ?string, source: string}>
     */
    protected function parseStatementServices(?Worksheet $sheet, array &$warnings): array
    {
        if (! $sheet) {
            return [];
        }

        $byColumns = $this->parseStatementByServiceColumns($sheet);
        if ($byColumns !== []) {
            return $byColumns;
        }

        $monthHeaders = $this->detectMonthHeaderRow($sheet);
        if ($monthHeaders !== null) {
            return $this->parseStatementMatrix($sheet, $monthHeaders);
        }

        $map = $this->findHeaderMap($sheet, [
            'service' => ['نوع الخدمة', 'الخدمة', 'الوصف', 'اسم الخدمة', 'service'],
            'date' => ['الشهر', 'التاريخ', 'تاريخ', 'date', 'month'],
            'amount' => ['المبلغ', 'السعر', 'المجموع', 'القيمة', 'amount'],
            'notes' => ['ملاحظات', 'الملاحظات', 'notes'],
        ]);

        if ($map === null || empty($map['cols']['service'])) {
            return [];
        }

        $rows = [];
        $highest = (int) $sheet->getHighestDataRow();
        for ($row = $map['row'] + 1; $row <= $highest; $row++) {
            $title = $this->cellString($sheet, $row, $map['cols']['service']);
            if ($title === '' || $this->isTotalLabel($title)) {
                continue;
            }
            $dateCol = $map['cols']['date'] ?? 0;
            $date = $dateCol ? $this->parseDate($this->cellValue($sheet, $row, $dateCol), $sheet, $row, $dateCol) : null;
            $amountCol = $map['cols']['amount'] ?? 0;
            $amount = $amountCol ? $this->parseAmount($this->cellValue($sheet, $row, $amountCol)) : null;
            $notes = $this->cellString($sheet, $row, $map['cols']['notes'] ?? 0);

            $rows[] = [
                'title' => $title,
                'service_date' => $date,
                'amount' => $amount,
                'notes' => $notes !== '' ? $notes : null,
                'source' => 'statement',
            ];
        }

        return $rows;
    }

    /**
     * كشف حساب: كل عمود خدمة وكل صف شهر.
     *
     * @return list<array{title: string, service_date: ?string, amount: ?string, notes: ?string, source: string}>
     */
    protected function parseStatementByServiceColumns(Worksheet $sheet): array
    {
        $map = $this->findHeaderMap($sheet, [
            'month' => ['الشهر', 'month'],
        ]);
        if ($map === null || empty($map['cols']['month'])) {
            return [];
        }

        $headerRow = $map['row'];
        $monthCol = $map['cols']['month'];
        $maxCol = min(16, Coordinate::columnIndexFromString($sheet->getHighestDataColumn()));
        $services = [];
        for ($col = 1; $col <= $maxCol; $col++) {
            if ($col === $monthCol) {
                continue;
            }
            $title = $this->cellString($sheet, $headerRow, $col);
            if ($title === '' || $this->isTotalLabel($title) || $this->isPaymentSummaryLabel($title)) {
                continue;
            }
            if ($this->containsAny($this->normalize($title), ['نوع الخدمه', 'الخدمه', 'الوصف', 'اسم الخدمه', 'التاريخ', 'ملاحظات'])) {
                continue;
            }
            $services[$col] = $title;
        }
        if ($services === []) {
            return [];
        }

        $rows = [];
        $highest = (int) $sheet->getHighestDataRow();
        for ($row = $headerRow + 1; $row <= $highest; $row++) {
            if ($this->rowLooksLikeTotal($sheet, $row)) {
                continue;
            }
            $date = $this->parseDate($this->cellValue($sheet, $row, $monthCol), $sheet, $row, $monthCol);
            foreach ($services as $col => $title) {
                $amount = $this->parseAmount($this->cellValue($sheet, $row, $col));
                if ($amount === null || ! Money::isPositive($amount)) {
                    continue;
                }
                $rows[] = [
                    'title' => $title,
                    'service_date' => $date,
                    'amount' => $amount,
                    'notes' => null,
                    'source' => 'statement',
                ];
            }
        }

        return $rows;
    }

    /**
     * @param  array{row: int, months: array<int, string>}  $header
     * @return list<array{title: string, service_date: ?string, amount: ?string, notes: ?string, source: string}>
     */
    protected function parseStatementMatrix(Worksheet $sheet, array $header): array
    {
        $rows = [];
        $highest = (int) $sheet->getHighestDataRow();
        for ($row = $header['row'] + 1; $row <= $highest; $row++) {
            $title = $this->cellString($sheet, $row, 1);
            if ($title === '' || $this->isTotalLabel($title)) {
                continue;
            }
            foreach ($header['months'] as $col => $date) {
                $raw = $this->cellValue($sheet, $row, $col);
                if ($this->isEmptyCell($raw)) {
                    continue;
                }
                $amount = $this->parseAmount($raw);
                $marked = in_array($this->normalize((string) $raw), ['x', 'v', '1', 'نعم', 'تم'], true);
                if ($amount === null && ! $marked && ! is_numeric($raw)) {
                    continue;
                }
                $rows[] = [
                    'title' => $title,
                    'service_date' => $date,
                    'amount' => $amount,
                    'notes' => null,
                    'source' => 'statement',
                ];
            }
        }

        return $rows;
    }

    /**
     * @return array{row: int, months: array<int, string>}|null
     */
    protected function detectMonthHeaderRow(Worksheet $sheet): ?array
    {
        $maxRow = min(8, (int) $sheet->getHighestDataRow());
        $maxCol = min(24, Coordinate::columnIndexFromString($sheet->getHighestDataColumn()));

        for ($row = 1; $row <= $maxRow; $row++) {
            $months = [];
            for ($col = 2; $col <= $maxCol; $col++) {
                $date = $this->parseDate($this->cellValue($sheet, $row, $col), $sheet, $row, $col);
                if ($date) {
                    $months[$col] = $date;
                }
            }
            if (count($months) >= 2) {
                return ['row' => $row, 'months' => $months];
            }
        }

        return null;
    }

    /**
     * @param  list<array{title: string, service_date: ?string, amount: ?string, notes: ?string, source: string}>  $fromSheets
     * @param  list<array{title: string, service_date: ?string, amount: ?string, notes: ?string, source: string}>  $fromStatement
     * @param  array<string, mixed>  $totals
     * @param  list<string>  $warnings
     * @return list<array{title: string, service_date: string, amount: string, currency_code: string, notes: ?string}>
     */
    protected function finalizeServices(array $fromSheets, array $fromStatement, array $totals, array &$warnings): array
    {
        $occurrences = $fromSheets !== [] ? $fromSheets : $fromStatement;

        if ($occurrences === [] && $totals !== []) {
            foreach ($totals as $key => $amount) {
                if ($key === '_labels' || ! is_string($amount)) {
                    continue;
                }
                $occurrences[] = [
                    'title' => $totals['_labels'][$key] ?? $key,
                    'service_date' => null,
                    'amount' => $amount,
                    'notes' => null,
                    'source' => 'totals',
                ];
            }
        }

        $groups = [];
        foreach ($occurrences as $row) {
            $key = $this->normalize($row['title']);
            $groups[$key][] = $row;
        }

        $fallbackDate = $this->fallbackServiceDate($occurrences);
        $result = [];

        foreach ($groups as $key => $rows) {
            $title = $rows[0]['title'];
            $total = $totals[$key] ?? null;
            $known = [];
            $unknown = [];
            foreach ($rows as $row) {
                if ($row['amount'] !== null && Money::isPositive($row['amount'])) {
                    $known[] = $row;
                } else {
                    $unknown[] = $row;
                }
            }

            $knownSum = Money::sum(array_column($known, 'amount'));
            if ($unknown !== []) {
                $remaining = $total !== null ? Money::sub($total, $knownSum) : null;
                if ($remaining !== null && Money::isPositive($remaining)) {
                    $share = $this->splitAmount($remaining, count($unknown));
                    foreach ($unknown as $i => $row) {
                        $row['amount'] = $share[$i];
                        $known[] = $row;
                    }
                }
            } elseif ($total !== null && Money::cmp($knownSum, $total) !== 0 && $fromSheets !== []) {
                $warnings[] = "مجموع تواريخ «{$title}» ({$knownSum}) يختلف عن صفحة المجموع ({$total}). سيتم اعتماد تفاصيل الصفحات.";
            }

            foreach ($known as $row) {
                if ($row['amount'] === null || ! Money::isPositive($row['amount'])) {
                    continue;
                }
                $date = $row['service_date'] ?: $fallbackDate;
                $result[] = [
                    'title' => $title,
                    'service_date' => $date,
                    'amount' => Money::of($row['amount']),
                    'currency_code' => 'ILS',
                    'source_amount' => $row['source_amount'] ?? null,
                    'exchange_rate' => $row['exchange_rate'] ?? null,
                    'notes' => $row['notes'],
                ];
            }
        }

        usort($result, fn ($a, $b) => strcmp($a['service_date'], $b['service_date']));

        return $result;
    }

    /**
     * @param  list<array{service_date: ?string}>  $rows
     */
    protected function fallbackServiceDate(array $rows): string
    {
        $dates = array_values(array_filter(array_column($rows, 'service_date')));
        sort($dates);

        return $dates[0] ?? now()->toDateString();
    }

    /**
     * @return list<string>
     */
    protected function splitAmount(string $total, int $count): array
    {
        if ($count <= 0) {
            return [];
        }

        $each = bcdiv($total, (string) $count, 2);
        $parts = array_fill(0, $count, $each);
        $used = bcmul($each, (string) ($count - 1), 2);
        $parts[$count - 1] = bcsub($total, $used, 2);

        return $parts;
    }

    /**
     * @param  array<string, list<string>>  $aliases
     * @return array{row: int, cols: array<string, int>}|null
     */
    protected function findHeaderMap(Worksheet $sheet, array $aliases): ?array
    {
        $maxRow = min(15, (int) $sheet->getHighestDataRow());
        $maxCol = min(16, Coordinate::columnIndexFromString($sheet->getHighestDataColumn()));

        for ($row = 1; $row <= $maxRow; $row++) {
            $cols = [];
            for ($pass = 0; $pass <= 1; $pass++) {
                for ($col = 1; $col <= $maxCol; $col++) {
                    $label = $this->normalize($this->cellString($sheet, $row, $col));
                    if ($label === '') {
                        continue;
                    }
                    foreach ($aliases as $key => $names) {
                        if (isset($cols[$key])) {
                            continue;
                        }
                        usort($names, fn ($a, $b) => mb_strlen($b) <=> mb_strlen($a));
                        foreach ($names as $name) {
                            $needle = $this->normalize($name);
                            $matched = $pass === 0
                                ? $label === $needle
                                : str_contains($label, $needle);
                            if ($matched) {
                                $cols[$key] = $col;
                                break;
                            }
                        }
                    }
                }
            }
            if (count($cols) >= 1) {
                return ['row' => $row, 'cols' => $cols];
            }
        }

        return null;
    }

    protected function parseDate(mixed $value, Worksheet $sheet, int $row, int $col): ?string
    {
        if ($col < 1) {
            return null;
        }

        try {
            $cell = $sheet->getCell(Coordinate::stringFromColumnIndex($col).$row);
            if (ExcelDate::isDateTime($cell)) {
                $raw = $cell->getCalculatedValue();
                if (is_numeric($raw)) {
                    return Carbon::instance(ExcelDate::excelToDateTimeObject((float) $raw))->toDateString();
                }
            }
        } catch (Throwable) {
            // fall through
        }

        if ($value === null || $value === '') {
            return null;
        }
        if ($value instanceof \DateTimeInterface) {
            return Carbon::instance(\DateTimeImmutable::createFromInterface($value))->toDateString();
        }
        if (is_numeric($value) && (float) $value > 20000 && (float) $value < 80000) {
            try {
                return Carbon::instance(ExcelDate::excelToDateTimeObject((float) $value))->toDateString();
            } catch (Throwable) {
                return null;
            }
        }

        if (is_numeric($value)) {
            $asFloat = (float) $value;
            $month = (int) $asFloat;
            if ($month >= 1 && $month <= 12 && abs($asFloat - $month) < 0.0001) {
                $year = $this->workbookYear ?: (int) now()->format('Y');

                return sprintf('%04d-%02d-01', $year, $month);
            }
        }

        $text = trim((string) $value);
        if ($text === '') {
            return null;
        }

        $months = $this->monthMap();
        uksort($months, fn ($a, $b) => mb_strlen($b) <=> mb_strlen($a));
        foreach ($months as $name => $num) {
            if (str_contains($this->normalize($text), $this->normalize($name))) {
                if (preg_match('/(20\d{2}|19\d{2})/u', $text, $m)) {
                    return sprintf('%s-%02d-01', $m[1], $num);
                }

                return sprintf('%s-%02d-01', now()->year, $num);
            }
        }

        foreach (['Y-m-d', 'd/m/Y', 'd-m-Y', 'Y/m/d', 'm/Y', 'Y-m', 'n/Y'] as $format) {
            try {
                $parsed = Carbon::createFromFormat($format, $text);
                if ($parsed !== false) {
                    if (in_array($format, ['m/Y', 'Y-m', 'n/Y'], true)) {
                        return $parsed->startOfMonth()->toDateString();
                    }

                    return $parsed->toDateString();
                }
            } catch (Throwable) {
                continue;
            }
        }

        try {
            return Carbon::parse($text)->toDateString();
        } catch (Throwable) {
            return null;
        }
    }

    protected function parseAmount(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (is_int($value) || is_float($value)) {
            return Money::of($value);
        }

        $text = trim((string) $value);
        if ($text === '' || $text === '-') {
            return null;
        }
        $text = str_replace(["\u{00A0}", ',', ' '], ['', '', ''], $text);
        $text = preg_replace('/[^\d.\-]/u', '', $text) ?? '';
        if ($text === '' || $text === '-' || $text === '.') {
            return null;
        }
        try {
            return Money::of($text);
        } catch (Throwable) {
            return null;
        }
    }

    protected function parseCurrencyCode(string $raw, bool $defaultIls = true): string
    {
        $n = $this->normalize($raw);
        if ($this->containsAny($n, ['usd', 'دولار', 'dollar', '$'])) {
            return 'USD';
        }

        return $defaultIls ? 'ILS' : 'ILS';
    }

    protected function cellString(Worksheet $sheet, int $row, int $col): string
    {
        $value = $this->cellValue($sheet, $row, $col);
        if ($value === null) {
            return '';
        }

        return trim((string) $value);
    }

    protected function cellValue(Worksheet $sheet, int $row, int $col): mixed
    {
        if ($col < 1 || $row < 1) {
            return null;
        }
        try {
            return $sheet->getCell(Coordinate::stringFromColumnIndex($col).$row)->getCalculatedValue();
        } catch (Throwable) {
            return null;
        }
    }

    protected function rowIsEmpty(Worksheet $sheet, int $row): bool
    {
        $maxCol = min(10, Coordinate::columnIndexFromString($sheet->getHighestDataColumn()));
        for ($col = 1; $col <= $maxCol; $col++) {
            if (! $this->isEmptyCell($this->cellValue($sheet, $row, $col))) {
                return false;
            }
        }

        return true;
    }

    protected function isEmptyCell(mixed $value): bool
    {
        if ($value === null) {
            return true;
        }

        return trim((string) $value) === '';
    }

    protected function isTotalLabel(string $text): bool
    {
        $n = $this->normalize($text);

        return $this->containsAny($n, ['المجموع', 'مجموع', 'الاجمالي', 'الإجمالي', 'صافي', 'المتبقي', 'الرصيد', 'total', 'net']);
    }

    protected function isPaymentSummaryLabel(string $text): bool
    {
        $n = $this->normalize($text);

        return $n === 'دفعات' || $n === 'الدفعات' || $n === 'payments' || $n === 'payment';
    }

    protected function rowLooksLikeTotal(Worksheet $sheet, int $row): bool
    {
        $maxCol = min(10, Coordinate::columnIndexFromString($sheet->getHighestDataColumn()));
        for ($col = 1; $col <= $maxCol; $col++) {
            $value = $this->cellString($sheet, $row, $col);
            if ($this->isTotalLabel($value)) {
                return true;
            }
        }

        return false;
    }

    public function serviceTypeKey(string $name): string
    {
        $value = $this->normalize($name);
        $value = preg_replace('/ات(\s|$)/u', '$1', $value) ?? $value;
        $value = preg_replace('/ه(\s|$)/u', '$1', $value) ?? $value;

        return preg_replace('/\s+/u', '', $value) ?? $value;
    }

    /**
     * @param  list<string>  $needles
     */
    protected function containsAny(string $haystack, array $needles): bool
    {
        foreach ($needles as $needle) {
            if ($needle !== '' && str_contains($haystack, $this->normalize($needle))) {
                return true;
            }
        }

        return false;
    }

    public function normalize(string $value): string
    {
        $value = trim($value);
        $value = str_replace(['أ', 'إ', 'آ', 'ى', 'ة', 'ـ'], ['ا', 'ا', 'ا', 'ي', 'ه', ''], $value);
        $value = preg_replace('/\s+/u', ' ', $value) ?? $value;

        return mb_strtolower($value);
    }

    /**
     * @return array<string, int>
     */
    protected function monthMap(): array
    {
        return [
            'يناير' => 1, 'كانون الثاني' => 1, 'january' => 1, 'jan' => 1,
            'فبراير' => 2, 'شباط' => 2, 'february' => 2, 'feb' => 2,
            'مارس' => 3, 'اذار' => 3, 'آذار' => 3, 'march' => 3, 'mar' => 3,
            'ابريل' => 4, 'أبريل' => 4, 'نيسان' => 4, 'april' => 4, 'apr' => 4,
            'مايو' => 5, 'ايار' => 5, 'أيار' => 5, 'may' => 5,
            'يونيو' => 6, 'حزيران' => 6, 'june' => 6, 'jun' => 6,
            'يوليو' => 7, 'تموز' => 7, 'july' => 7, 'jul' => 7,
            'اغسطس' => 8, 'أغسطس' => 8, 'اب' => 8, 'آب' => 8, 'august' => 8, 'aug' => 8,
            'سبتمبر' => 9, 'ايلول' => 9, 'أيلول' => 9, 'september' => 9, 'sep' => 9,
            'اكتوبر' => 10, 'أكتوبر' => 10, 'تشرين الاول' => 10, 'october' => 10, 'oct' => 10,
            'نوفمبر' => 11, 'تشرين الثاني' => 11, 'november' => 11, 'nov' => 11,
            'ديسمبر' => 12, 'كانون الاول' => 12, 'december' => 12, 'dec' => 12,
        ];
    }

    /**
     * @param  list<string>  $errors
     * @return array<string, mixed>
     */
    protected function emptyResult(string $filename, array $errors): array
    {
        return [
            'filename' => $filename,
            'client_name' => $filename,
            'company_name' => null,
            'phone' => null,
            'notes' => null,
            'services' => [],
            'payments' => [],
            'warnings' => [],
            'errors' => $errors,
        ];
    }
}
