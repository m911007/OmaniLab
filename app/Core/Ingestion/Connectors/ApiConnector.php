<?php

namespace App\Core\Ingestion\Connectors;

use App\Core\Ingestion\Contracts\Connector;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

/** أساس مشترك لموصلات واجهات HTTP/JSON: مهلة، إعادة محاولة، هوية واضحة. */
abstract class ApiConnector implements Connector
{
    public function queue(): string
    {
        return 'ingest';
    }

    public function revision(): int
    {
        return 1;
    }

    /** الهوية المرسلة للمصدر. تتجاوزها بعض الموصلات إن كان جدار حماية المصدر يرفض الهوية الافتراضية. */
    protected function userAgent(): string
    {
        return (string) config('omanilab.ingest.user_agent');
    }

    protected function http(): PendingRequest
    {
        return Http::withHeaders(['User-Agent' => $this->userAgent(), 'Accept' => 'application/json'])
            ->timeout((int) config('omanilab.ingest.http_timeout_seconds'))
            ->retry(3, 1500, throw: false);
    }

    /** @return array<int|string, mixed> */
    protected function getJson(string $url): array
    {
        return $this->http()->get($url)->throw()->json() ?? [];
    }
}
