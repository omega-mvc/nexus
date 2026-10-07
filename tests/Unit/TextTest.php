<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

class TextTest extends TestCase
{
    public function testComposeWithVarargs(): void
    {
        $this->assertSame('Hello World', Text::compose('Hello %s', 'World'));
    }

    public function testComposeWithSingleArrayArgument(): void
    {
        $this->assertSame('Bob won', Text::compose('%s won', array('Bob')));
    }

    public function testComposeNumberInterpolation(): void
    {
        $this->assertSame('count: 42', Text::compose('count: %d', 42));
    }

    public function testComposeStringableObject(): void
    {
        $date = new Date('2020-01-01');
        $this->assertSame('date: 2020-01-01', Text::compose('date: %s', $date));
    }
}
