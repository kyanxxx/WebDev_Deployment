<?php

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class DASHBOARDControllerTest extends WebTestCase
{
    public function testIndex(): void
    {
        $client = static::createClient();
        $client->request('GET', '/d/a/s/h/b/o/a/r/d');

        self::assertResponseIsSuccessful();
    }
}
