<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

class MoneyTest extends TestCase
{
    public function testConstructWithFloatAmount(): void
    {
        // Regression: float passed to str_replace() was a TypeError on PHP 8
        $money = new Money(12.5, 'USD');
        $this->assertSame('12.50', (string) $money);
    }

    public function testConstructWithNumericString(): void
    {
        $money = new Money('99.99', 'USD');
        $this->assertSame('99.99', (string) $money);
    }

    public function testInvalidCurrencyThrows(): void
    {
        $this->expectException(ProgrammerException::class);
        $this->expectExceptionMessageMatches('/NOPE/');
        new Money(1, 'NOPE');
    }

    public function testNoCurrencyAndNoDefaultThrows(): void
    {
        $this->expectException(ProgrammerException::class);
        new Money(1);
    }
}
