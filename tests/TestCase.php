<?php

namespace Weexduunx\AidaGateway\Tests;

use Orchestra\Testbench\TestCase as Orchestra;
use Weexduunx\AidaGateway\AidaGatewayServiceProvider;

class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();

        // Additional setup
    }

    protected function getPackageProviders($app)
    {
        return [
            AidaGatewayServiceProvider::class,
        ];
    }

    protected function getPackageAliases($app)
    {
        return [
            'Aida' => 'Weexduunx\AidaGateway\Facades\Aida',
        ];
    }

    protected function getEnvironmentSetUp($app)
    {
        // Setup default database to use sqlite :memory:
        $app['config']->set('database.default', 'testbench');
        $app['config']->set('database.connections.testbench', [
            'driver'   => 'sqlite',
            'database' => ':memory:',
            'prefix'   => '',
        ]);

        // Setup package configuration
        $app['config']->set('aida-gateway.default', 'orange_money');
        $app['config']->set('aida-gateway.gateways.orange_money', [
            'enabled' => true,
            'api_url' => 'https://api.test.com',
            'merchant_key' => 'test_key',
            'api_username' => 'test_user',
            'api_password' => 'test_pass',
            'currency' => 'XOF',
            'country_code' => 'SN',
        ]);
    }
}
