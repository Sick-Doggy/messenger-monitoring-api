<?php

declare(strict_types=1);

namespace App\Tests;

use App\Tests\Api\ApiTestCase;

final class AppBootTest extends ApiTestCase
{
    public function testKernelBootsWithApiPlatform(): void
    {
        self::bootKernel();

        self::assertTrue(static::getContainer()->has('api_platform.symfony.main_controller'));
    }
}
