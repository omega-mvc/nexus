<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

class JSONTest extends TestCase
{
    public function testEncodeArray(): void
    {
        $this->assertSame('{"a":1}', JSON::encode(array('a' => 1)));
    }

    public function testDecodeToObject(): void
    {
        $decoded = JSON::decode('{"a":1}');
        $this->assertIsObject($decoded);
        $this->assertSame(1, $decoded->a);
    }

    public function testDecodeAssociative(): void
    {
        $this->assertSame(array(1, 2), JSON::decode('[1,2]', TRUE));
    }

    public function testDecodeInvalidReturnsNull(): void
    {
        $this->assertNull(JSON::decode('not json'));
        $this->assertNull(JSON::decode(''));
    }

    public function testEncodeResourceBecomesNull(): void
    {
        $handle = fopen('php://memory', 'r');
        $this->assertSame('null', JSON::encode($handle));
        fclose($handle);
    }

    public function testRoundTrip(): void
    {
        $data = array('name' => 'Flourish', 'nums' => array(1, 2, 3));
        $this->assertSame($data, JSON::decode(JSON::encode($data), TRUE));
    }
}
