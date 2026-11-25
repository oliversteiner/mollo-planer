<?php

namespace Tests\Unit\Billing;

use App\Support\Billing\Iban;
use PHPUnit\Framework\TestCase;

class IbanTest extends TestCase
{
    public function test_check_digits_of_swiss_and_liechtenstein_ibans(): void
    {
        $this->assertTrue(Iban::hasValidChecksum('CH93 0076 2011 6238 5295 7'));
        $this->assertTrue(Iban::hasValidChecksum('LI21 0881 0000 2324 013A A'));
        $this->assertFalse(Iban::hasValidChecksum('CH93 0076 2011 6238 5295 8'));
        $this->assertFalse(Iban::hasValidChecksum('CH39 0076 2011 6238 5295 7'));
        $this->assertFalse(Iban::hasValidChecksum('not an iban'));
    }

    public function test_only_ch_and_li_with_21_characters_count_as_swiss(): void
    {
        $this->assertTrue(Iban::isSwiss('ch9300762011623852957'));
        $this->assertTrue(Iban::isSwiss('LI21 0881 0000 2324 013A A'));
        $this->assertFalse(Iban::isSwiss('DE89 3704 0044 0532 0130 00'));
        $this->assertFalse(Iban::isSwiss('CH93 0076 2011 6238 5295'));
    }

    public function test_a_qr_iban_is_recognised_by_its_institution_id(): void
    {
        $this->assertTrue(Iban::isQrIban('CH44 3199 9123 0008 8901 2'));
        $this->assertFalse(Iban::isQrIban('CH93 0076 2011 6238 5295 7'));
    }

    public function test_normalize_and_format(): void
    {
        $this->assertSame('CH9300762011623852957', Iban::normalize(' ch93 0076 2011 6238 5295 7 '));
        $this->assertSame('CH93 0076 2011 6238 5295 7', Iban::format('CH9300762011623852957'));
    }
}
