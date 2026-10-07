<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

class HTMLEncodeTest extends TestCase
{
    public function testEncodeSpecialCharacters(): void
    {
        $this->assertSame(
            '&lt;b&gt;&amp;&quot;x&quot;&lt;/b&gt;',
            HTML::encode('<b>&"x"</b>')
        );
    }

    public function testEncodeLeavesSafeTextAlone(): void
    {
        $this->assertSame('plain text', HTML::encode('plain text'));
    }
}
