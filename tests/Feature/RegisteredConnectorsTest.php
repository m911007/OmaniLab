<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Tests\DatabaseTestCase;

class RegisteredConnectorsTest extends DatabaseTestCase
{
    public function test_every_registered_connector_definition_syncs_into_the_database(): void
    {
        $this->artisan('datasets:sync')->assertSuccessful();

        $this->assertSame(count(config('omanilab.connectors')), DB::table('ops.datasets')->where('enabled', true)->count());
    }
}
