<?php

declare(strict_types=1);

namespace App\Tests;

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class AppBootTest extends KernelTestCase
{
    public function testKernelBootsWithApiPlatform(): void
    {
        self::bootKernel();

        self::assertTrue(static::getContainer()->has('api_platform.symfony.main_controller'));
    }
}
