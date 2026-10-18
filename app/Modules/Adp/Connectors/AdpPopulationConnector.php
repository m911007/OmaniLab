<?php

namespace App\Modules\Adp\Connectors;

final class AdpPopulationConnector extends AdpConnector
{
    protected function datasetId(): string
    {
        return 'population';
    }
}
