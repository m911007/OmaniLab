<?php

namespace Tests\Support;

use App\Core\Ingestion\Connectors\ApiConnector;
use App\Core\Ingestion\Data\DatasetDefinition;
use App\Core\Ingestion\Data\IndicatorDefinition;
use App\Core\Ingestion\Data\ProbeResult;
use App\Core\Ingestion\Data\RawPayload;

/** يدّعي مؤشرًا يملكه FakeConnector: يجب أن ترفضه المزامنة. */
final class RivalFakeConnector extends ApiConnector
{
    public function definition(): DatasetDefinition
    {
        $base = (new FakeConnector)->definition();

        return new DatasetDefinition('fake.rival', $base->source, 'منافس', 'Rival', 'test', 60, [new IndicatorDefinition('fake.GDP', 'x', 'x')]);
    }

    public function revision(): int
    {
        return 1;
    }

    public function probe(): ProbeResult
    {
        return new ProbeResult('x');
    }

    public function fetch(): RawPayload
    {
        return new RawPayload('{}');
    }

    public function parse(RawPayload $payload): iterable
    {
        return [];
    }
}
