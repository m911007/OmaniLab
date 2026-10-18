<?php

namespace App\Modules\Adp\Connectors;

final class AdpLabourConnector extends AdpConnector
{
    protected function datasetId(): string
    {
        return 'labour';
    }
}
