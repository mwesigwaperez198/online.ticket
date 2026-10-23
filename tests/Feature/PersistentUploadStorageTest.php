<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;

final class PersistentUploadStorageTest extends TestCase
{
    public function test_deploy_check_includes_a_persistent_upload_storage_check(): void
    {
        config(['ticketing.admin.email' => 'admin@example.test']);
        putenv('RENDER_SERVICE_ID=');

        $this->artisan('ticketing:deploy-check --json')
            ->assertExitCode(1)
            ->expectsOutputToContain('Persistent upload storage');
    }
}
