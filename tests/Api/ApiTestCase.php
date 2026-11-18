<?php

declare(strict_types=1);

namespace App\Tests\Api;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/** Testy dotykające kernela z ODM: wymagają ext-mongodb (docker compose: php ma pecl mongodb). */
abstract class ApiTestCase extends WebTestCase
{
    protected function setUp(): void
    {
        if (!\extension_loaded('mongodb')) {
            self::markTestSkipped('Wymaga ext-mongodb — uruchom w docker compose (docker compose run --rm php vendor/bin/phpunit)');
        }
    }
}
