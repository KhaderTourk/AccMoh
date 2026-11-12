<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * سعر مقترح للدولار والدينار بالشيكل (12/11/2026): اقتراح بس، ما بينحفظ إلا لما المستخدم يأكد.
 * المصدر الأول: السعر الرسمي لبنك إسرائيل (الأدق للشيكل). إذا ما ردّ: سعر سوق عالمي (open.er-api.com).
 * الدينار مربوط بالدولار (1 دينار = 1.41044 دولار تقريباً)، فإذا المصدر ما فيه دينار بينحسب من الدولار.
 * بينحفظ بالكاش 3 ساعات، وكل طلب مهلته 5 ثواني عشان الصفحة ما تعلق.
 *
 * @phpstan-type Suggestion array{rates: array<string, float>, source: string, as_of: ?string, derived: list<string>}
 */
class OfficialRates
{
    public const JOD_PER_USD = 0.709;

    /** @return array{rates: array<string, float>, source: string, as_of: ?string, derived: list<string>}|null */
    public static function suggest(): ?array
    {
        $hit = Cache::get('official_rates.v1');
        if (is_array($hit)) {
            return isset($hit['none']) ? null : $hit;
        }
        $out = self::fromBankOfIsrael() ?? self::fromOpenEr();
        if ($out) {
            Cache::put('official_rates.v1', $out, now()->addHours(3));
        } else {
            Cache::put('official_rates.v1', ['none' => true], now()->addMinutes(10));
        }

        return $out;
    }

    protected static function fromBankOfIsrael(): ?array
    {
        try {
            $res = Http::timeout(5)->acceptJson()->get('https://boi.org.il/PublicApi/GetExchangeRates', ['asJson' => 'true']);
            if (! $res->ok()) {
                return null;
            }
            $rates = [];
            $asOf = null;
            foreach ((array) $res->json('exchangeRates', []) as $row) {
                $code = strtoupper((string) ($row['key'] ?? ''));
                $rate = (float) ($row['currentExchangeRate'] ?? 0);
                $unit = max(1, (float) ($row['unit'] ?? 1));
                if (in_array($code, ['USD', 'JOD'], true) && $rate > 0) {
                    $rates[$code] = round($rate / $unit, 4);
                    $asOf ??= $row['lastUpdate'] ?? null;
                }
            }

            return self::finish($rates, 'بنك إسرائيل (السعر الرسمي)', $asOf);
        } catch (\Throwable) {
            return null;
        }
    }

    protected static function fromOpenEr(): ?array
    {
        try {
            $res = Http::timeout(5)->acceptJson()->get('https://open.er-api.com/v6/latest/USD');
            $ils = (float) $res->json('rates.ILS', 0);
            if (! $res->ok() || $ils <= 0) {
                return null;
            }
            $rates = ['USD' => round($ils, 4)];
            $jod = (float) $res->json('rates.JOD', 0);
            if ($jod > 0) {
                $rates['JOD'] = round($ils / $jod, 4);
            }
            $ts = (int) $res->json('time_last_update_unix', 0);

            return self::finish($rates, 'سعر السوق العالمي', $ts ? date('c', $ts) : null);
        } catch (\Throwable) {
            return null;
        }
    }

    protected static function finish(array $rates, string $source, ?string $asOf): ?array
    {
        if (empty($rates['USD'])) {
            return null;
        }
        $derived = [];
        if (empty($rates['JOD'])) {
            $rates['JOD'] = round($rates['USD'] / self::JOD_PER_USD, 4);
            $derived[] = 'JOD';
        }

        return ['rates' => $rates, 'source' => $source, 'as_of' => $asOf ? substr((string) $asOf, 0, 10) : null, 'derived' => $derived];
    }
}
