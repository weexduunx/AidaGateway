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
            'api_secret' => 'test_secret',
            'currency' => 'XOF',
            'country_code' => 'SN',
        ]);

        $app['config']->set('aida-gateway.gateways.wave', [
            'enabled' => true,
            'api_url' => 'https://api.wave.com',
            'api_key' => 'test_api_key',
            'api_secret' => 'test_secret',
            'currency' => 'XOF',
        ]);

        $app['config']->set('aida-gateway.gateways.free_money', [
            'enabled' => true,
            'api_url' => 'https://api.free.sn',
            'merchant_id' => 'test_merchant',
            'api_key' => 'test_api_key',
            'api_secret' => 'test_secret',
            'currency' => 'XOF',
        ]);

        $app['config']->set('aida-gateway.gateways.emoney', [
            'enabled' => true,
            'api_url' => 'https://api.emoney.sn',
            'merchant_code' => 'test_merchant',
            'api_key' => 'test_api_key',
            'api_secret' => 'test_secret',
            'currency' => 'XOF',
        ]);

        $app['config']->set('aida-gateway.webhook.route_prefix', 'aida/webhooks');
        $app['config']->set('aida-gateway.logging.enabled', true);
        $app['config']->set('aida-gateway.logging.channel', 'stack');
    }
}
