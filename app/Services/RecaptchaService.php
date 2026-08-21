<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class RecaptchaService
{
    public function isEnabled(): bool
    {
        return filled(config('services.recaptcha.secret_key'))
            && filled(config('services.recaptcha.site_key'));
    }

    public function verify(?string $response, ?string $remoteIp = null): bool
    {
        if (! $this->isEnabled()) {
            return true;
        }

        if ($response === null || $response === '') {
            return false;
        }

        try {
            $res = Http::asForm()->timeout(10)->post('https://www.google.com/recaptcha/api/siteverify', [
                'secret' => config('services.recaptcha.secret_key'),
                'response' => $response,
                'remoteip' => $remoteIp,
            ]);

            if (! $res->successful()) {
                return false;
            }

            return (bool) data_get($res->json(), 'success');
        } catch (\Throwable) {
            return false;
        }
    }
}
