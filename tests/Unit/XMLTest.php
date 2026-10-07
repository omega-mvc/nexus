<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

class XMLTest extends TestCase
{
    public function testAttributeAccess(): void
    {
        $xml = new XML('<root id="7"><a>hello</a></root>');
        $this->assertSame('7', $xml['id']);
        $this->assertTrue(isset($xml['id']));
        $this->assertFalse(isset($xml['missing']));
    }

    public function testXpathReturnsMatches(): void
    {
        $xml = new XML('<root id="7"><a>hello</a><a>two</a></root>');
        $matches = $xml->xpath('a');
        $this->assertIsArray($matches);
        $this->assertCount(2, $matches);
    }

    public function testOffsetGetMissingAttributeReturnsNull(): void
    {
        $xml = new XML('<root id="7"/>');
        $this->assertNull($xml['missing']);
    }
}
