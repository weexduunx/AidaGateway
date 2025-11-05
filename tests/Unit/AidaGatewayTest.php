<?php

namespace Weexduunx\AidaGateway\Tests\Unit;

use Weexduunx\AidaGateway\Tests\TestCase;
use Weexduunx\AidaGateway\AidaGateway;
use Weexduunx\AidaGateway\Facades\Aida;
use Weexduunx\AidaGateway\Exceptions\GatewayNotFoundException;

class AidaGatewayTest extends TestCase
{
    /** @test */
    public function it_can_get_supported_gateways()
    {
        $gateways = Aida::getSupportedGateways();

        $this->assertIsArray($gateways);
        $this->assertArrayHasKey('orange_money', $gateways);
        $this->assertArrayHasKey('wave', $gateways);
        $this->assertArrayHasKey('free_money', $gateways);
        $this->assertArrayHasKey('emoney', $gateways);
    }

    /** @test */
    public function it_can_check_if_gateway_is_supported()
    {
        $this->assertTrue(Aida::isGatewaySupported('orange_money'));
        $this->assertTrue(Aida::isGatewaySupported('wave'));
        $this->assertFalse(Aida::isGatewaySupported('invalid_gateway'));
    }

    /** @test */
    public function it_throws_exception_for_unsupported_gateway()
    {
        $this->expectException(GatewayNotFoundException::class);

        Aida::gateway('invalid_gateway');
    }

    /** @test */
    public function it_can_switch_between_gateways()
    {
        $aida = new AidaGateway();

        $this->assertInstanceOf(AidaGateway::class, $aida->gateway('orange_money'));
    }
}
