<?php

namespace Tests\Unit;

use App\Support\Phone;
use PHPUnit\Framework\TestCase;

class PhoneTest extends TestCase
{
    public function test_normalizes_us_numbers_to_e164()
    {
        $this->assertSame('+13853501234', Phone::toE164('(385) 350-1234'));
        $this->assertSame('+13853501234', Phone::toE164('385.350.1234'));
        $this->assertSame('+13853501234', Phone::toE164('385 350 1234'));
        $this->assertSame('+13853501234', Phone::toE164('13853501234'));
        $this->assertSame('+13853501234', Phone::toE164('+1 (385) 350-1234'));
        $this->assertSame('+13853501234', Phone::toE164('+13853501234'));
    }

    public function test_rejects_garbage()
    {
        $this->assertNull(Phone::toE164(null));
        $this->assertNull(Phone::toE164(''));
        $this->assertNull(Phone::toE164('call the front desk'));
        $this->assertNull(Phone::toE164('12345'));
        $this->assertNull(Phone::toE164('23853501234')); // 11 digits, not US-prefixed
    }
}
