<?php

namespace App\Core\Notifications;

use App\Core\Support\Secrets;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

final class TelegramClient
{
    public function isConfigured(): bool
    {
        return filled(Secrets::get('telegram_bot_token')) && filled(Secrets::get('telegram_chat_id'));
    }

    /** يرسل رسالة HTML. يعيد false (دون رمي استثناء) إن لم يُهيّأ البوت أو فشل الإرسال. */
    public function send(string $html): bool
    {
        if (! $this->isConfigured()) {
            Log::info('[telegram:غير مهيّأ] '.strip_tags($html));

            return false;
        }

        try {
            $response = Http::timeout(15)->post(
                'https://api.telegram.org/bot'.Secrets::get('telegram_bot_token').'/sendMessage',
                [
                    'chat_id' => Secrets::get('telegram_chat_id'),
                    'text' => mb_substr($html, 0, 4000),
                    'parse_mode' => 'HTML',
                    'disable_web_page_preview' => true,
                ],
            );

            return $response->successful();
        } catch (Throwable $e) {
            Log::warning('فشل إرسال تنبيه تيليجرام: '.$e->getMessage());

            return false;
        }
    }
}
