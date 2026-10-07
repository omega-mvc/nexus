<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

class URLTest extends TestCase
{
    public function testMakeFriendlyDefaultDelimiter(): void
    {
        $this->assertSame('hello_world', URL::makeFriendly('Hello World!'));
    }

    public function testMakeFriendlyCustomDelimiterAndMaxLength(): void
    {
        $this->assertSame('hello-world', URL::makeFriendly('Hello World', NULL, '-'));
        $this->assertLessThanOrEqual(5, strlen(URL::makeFriendly('Hello World Foo Bar', 5)));
    }
}
