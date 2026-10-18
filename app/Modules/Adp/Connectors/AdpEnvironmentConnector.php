<?php

namespace App\Modules\Adp\Connectors;

final class AdpEnvironmentConnector extends AdpConnector
{
    protected function datasetId(): string
    {
        return 'environment';
    }
}
