<?php

namespace Tests\Unit;

use App\Support\BcMathHelper;
use DomainException;
use PHPUnit\Framework\TestCase;

class BcMathCapacityTest extends TestCase
{
    public function test_round_half_up_vs_truncation_mac(): void
    {
        // Round 8 verified example: bcdiv('102753.2766', '6.3333', 10) = 16224.2869594050... -> roundHalfUp(..., 6) = 16224.286959
        $raw = bcdiv('102753.2766', '6.3333', 10);
        $this->assertSame('16224.2869594050', $raw);
        $this->assertSame('16224.286959', BcMathHelper::roundHalfUp($raw, 6));
        // Half-up tie check
        $this->assertSame('10.000001', BcMathHelper::roundHalfUp('10.0000005000', 6));
    }

    public function test_decimal_capacity_guard_prevents_silent_overflow(): void
    {
        BcMathHelper::assertFitsDecimalCapacity('999999999999.9999', 16, 4, 'quantity');
        BcMathHelper::assertFitsDecimalCapacity('999999999999999999999999.9999', 28, 4, 'total_cost');

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('DECIMAL_CAPACITY_EXCEEDED');
        BcMathHelper::assertFitsDecimalCapacity('1000000000000.0000', 16, 4, 'quantity');
    }
}
