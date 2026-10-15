<?php

namespace App\Core\Ingestion;

use App\Core\Ingestion\Contracts\Connector;
use App\Models\Dataset;
use InvalidArgumentException;

/** يقرأ الموصلات المسجّلة في config('omanilab.connectors') ويحلّها من الحاوية. */
final class ConnectorRegistry
{
    /** @return list<Connector> */
    public function all(): array
    {
        return array_map(fn (string $class) => app($class), config('omanilab.connectors', []));
    }

    public function forDataset(Dataset $dataset): Connector
    {
        $class = $dataset->connector;
        if (! in_array($class, config('omanilab.connectors', []), true)) {
            throw new InvalidArgumentException("الموصل غير مسجّل: {$class}");
        }

        return app($class);
    }
}
