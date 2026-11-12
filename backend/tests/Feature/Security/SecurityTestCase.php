<?php

namespace Tests\Feature\Security;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

abstract class SecurityTestCase extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        // Las migraciones de OCCRE usan ALTER TABLE ... ADD INDEX, no soportado
        // por sqlite. Se usa la base de pruebas aislada occre_test_db.
        foreach (['DB_CONNECTION' => 'mysql', 'DB_DATABASE' => 'occre_test_db'] as $key => $value) {
            putenv("{$key}={$value}");
            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
        }

        parent::setUp();
    }
}
