<?php

namespace Larastic\Tests\Payloads;

use Larastic\Payloads\IndexPayload;
use Larastic\Tests\AbstractTestCase;
use Larastic\Tests\Dependencies\IndexConfigurator;

class IndexPayloadTest extends AbstractTestCase
{
    use IndexConfigurator;

    public function testDefault()
    {
        $indexConfigurator = $this->mockIndexConfigurator();
        $payload = new IndexPayload($indexConfigurator);

        $this->assertEquals(
            ['index' => 'test'],
            $payload->get()
        );
    }

    public function testUseAlias()
    {
        $indexConfigurator = $this->mockIndexConfigurator([
            'name' => 'foo'
        ]);

        $payload = (new IndexPayload($indexConfigurator))
            ->useAlias('write');

        $this->assertEquals(
            ['index' => 'foo_write'],
            $payload->get()
        );
    }

    public function testSet()
    {
        $indexConfigurator = $this->mockIndexConfigurator([
            'name' => 'foo'
        ]);

        $payload = (new IndexPayload($indexConfigurator))
            ->set('index', 'bar')
            ->set('settings', ['key' => 'value']);

        $this->assertEquals(
            [
                'index' => 'foo',
                'settings' => ['key' => 'value']
            ],
            $payload->get()
        );
    }
}