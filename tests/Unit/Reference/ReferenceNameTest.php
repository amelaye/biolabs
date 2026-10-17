<?php
/**
 * Tests of the reference name normalisation
 * Freely inspired by Plasmi::db 0.9.1 (Mark Brooks) and BioPHP's project biophp.org
 * Created 17 October 2026
 * Last modified 17 October 2026
 */
declare(strict_types=1);

namespace App\Tests\Unit\Reference;

use App\Reference\Service\ReferenceName;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * @author Amélie DUVERNET aka Amelaye <amelieonline@gmail.com>
 */
final class ReferenceNameTest extends TestCase
{
    /**
     * Tests that the display form trims, collapses whitespace and keeps the case.
     *
     * @return void
     */
    public function testDisplayTrimsAndCollapsesWhitespaceButKeepsCase(): void
    {
        self::assertSame('Rosetta 2 (DE3)', ReferenceName::display("  Rosetta \t 2   (DE3)\n"));
    }

    /**
     * Tests that decomposed Unicode is composed (NFC) and gives the same key as the composed form.
     *
     * @return void
     */
    public function testDisplayComposesUnicodeToNfc(): void
    {
        $decomposed = "Cafe\u{0301}"; // e + combining acute
        self::assertSame("Caf\u{00E9}", ReferenceName::display($decomposed));
        self::assertSame(ReferenceName::key($decomposed), ReferenceName::key("Caf\u{00E9}"));
    }

    /**
     * Tests that two names differing only by case have the same key.
     *
     * @return void
     */
    public function testKeyIgnoresCase(): void
    {
        self::assertSame(ReferenceName::key('jm109'), ReferenceName::key('JM109'));
    }

    /**
     * Tests that accents, parentheses and Greek letters make names distinct.
     *
     * @param string $a First name
     * @param string $b Second name, expected to have a different key
     * @return void
     */
    #[DataProvider('distinctNames')]
    public function testKeyKeepsAccentsParenthesesAndGreekLetters(string $a, string $b): void
    {
        self::assertNotSame(ReferenceName::key($a), ReferenceName::key($b));
    }

    /**
     * Data provider: pairs of names that must stay distinct.
     *
     * @return iterable<string, array{string, string}> Two names by case name
     */
    public static function distinctNames(): iterable
    {
        yield 'greek alpha' => ['DH5α', 'DH5a'];
        yield 'parentheses' => ['BL21(DE3)', 'BL21 DE3'];
        yield 'accent' => ["Caf\u{00E9}", 'Cafe'];
    }

    /**
     * Tests that the loose key ignores punctuation and spaces but not letters.
     *
     * @return void
     */
    public function testLooseKeyIgnoresPunctuationAndSpaces(): void
    {
        self::assertSame(ReferenceName::looseKey('BL21(DE3)'), ReferenceName::looseKey('BL21 DE3'));
        self::assertNotSame(ReferenceName::looseKey('DH5α'), ReferenceName::looseKey('DH5a'));
    }

    /**
     * Tests that a null or blank name gives an empty display form and key.
     *
     * @return void
     */
    public function testNullIsEmpty(): void
    {
        self::assertSame('', ReferenceName::display(null));
        self::assertSame('', ReferenceName::key('   '));
    }
}
