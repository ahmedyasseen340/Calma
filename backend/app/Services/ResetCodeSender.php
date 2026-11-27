<?php

namespace App\Services;

use App\Mail\ResetCodeMail;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;

/**
 * Sends the 6-digit "forgot password" code: through Brevo's HTTPS API when BREVO_API_KEY is set,
 * otherwise through the normal Laravel mailer.
 */
class ResetCodeSender
{
    public function send(string $email, string $code, int $minutes): void
    {
        $apiKey = config('mailapi.brevo_key');

        if (! $apiKey) {
            Mail::to($email)->send(new ResetCodeMail($code, $minutes));

            return;
        }

        Http::timeout(15)
            ->withHeaders(['api-key' => $apiKey, 'accept' => 'application/json'])
            ->post('https://api.brevo.com/v3/smtp/email', [
                'sender' => ['name' => config('mail.from.name'), 'email' => config('mail.from.address')],
                'to' => [['email' => $email]],
                'subject' => 'كود إعادة تعيين كلمة المرور - Calma',
                'textContent' => view('emails.reset-code', ['code' => $code, 'minutes' => $minutes])->render(),
            ])
            ->throw();
    }
}
