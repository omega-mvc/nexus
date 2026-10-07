<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

class GrammarTest extends TestCase
{
    public function testCamelize(): void
    {
        $this->assertSame('fooBarBaz', Grammar::camelize('foo bar_baz', FALSE));
        $this->assertSame('FooBarBaz', Grammar::camelize('foo bar_baz', TRUE));
    }

    public function testSingularize(): void
    {
        $this->assertSame('cat', Grammar::singularize('cats'));
        // No plural marker: with $return_error=TRUE returns FALSE (no exception)
        $this->assertFalse(Grammar::singularize('cat', TRUE));
    }

    public function testPluralize(): void
    {
        $this->assertSame('cats', Grammar::pluralize('cat'));
    }
}
