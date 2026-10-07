<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

class DateTimeTest extends TestCase
{
    public function testDateFormat(): void
    {
        $date = new Date('2020-01-31');
        $this->assertSame('2020-01-31', $date->format('Y-m-d'));
    }

    public function testDateEq(): void
    {
        $a = new Date('2020-01-01');
        $b = new Date('2020-01-01');
        $c = new Date('2020-01-02');
        $this->assertTrue($a->eq($b));
        $this->assertTrue($a->eq('2020-01-01'));
        $this->assertFalse($a->eq($c));
    }

    public function testDateAdjust(): void
    {
        $date = new Date('2020-01-31');
        $this->assertSame('2020-02-01', $date->adjust('+1 day')->format('Y-m-d'));
    }

    public function testTimeFormatAndEq(): void
    {
        $time = new Time('13:45:00');
        $this->assertSame('13:45:00', $time->format('H:i:s'));
        $this->assertTrue($time->eq(new Time('13:45:00')));
        $this->assertFalse($time->eq(new Time('13:45:01')));
    }

    public function testTimestampFormatAndAdjust(): void
    {
        $ts = new Timestamp('2020-01-01 12:00:00');
        $this->assertSame('2020-01-01 12:00:00', $ts->format('Y-m-d H:i:s'));
        $this->assertTrue($ts->eq(new Timestamp('2020-01-01 12:00:00')));
        $this->assertSame(
            '2020-01-01 13:00:00',
            $ts->adjust('+1 hour')->format('Y-m-d H:i:s')
        );
    }
}
