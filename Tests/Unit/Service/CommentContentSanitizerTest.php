<?php

declare(strict_types=1);

/*************************************************************************************/
/*      This file is part of the Thelia package.                                     */
/*                                                                                   */
/*      Copyright (c) OpenStudio                                                     */
/*      email : dev@thelia.net                                                       */
/*      web : http://www.thelia.net                                                  */
/*                                                                                   */
/*      For the full copyright and license information, please view the LICENSE.txt  */
/*************************************************************************************/

namespace Comment\Tests\Unit\Service;

use Comment\Service\Front\CommentContentSanitizer;
use PHPUnit\Framework\TestCase;

/**
 * What the shop shows of what a visitor wrote.
 *
 * Nothing used to clean a posted comment. The theme escapes what it prints, so markup did
 * not run, but it was shown as markup, and a body could carry direction overrides or control
 * characters that make the displayed text read differently from the stored one.
 */
final class CommentContentSanitizerTest extends TestCase
{
    private CommentContentSanitizer $sanitizer;

    protected function setUp(): void
    {
        $this->sanitizer = new CommentContentSanitizer();
    }

    public function testOrdinaryTextIsLeftAlone(): void
    {
        $text = "Produit conforme.\nLivraison en 48h, emballage correct.";

        self::assertSame($text, $this->sanitizer->sanitize($text));
    }

    public function testMarkupIsRemovedAndItsTextKept(): void
    {
        self::assertSame('alert(1)', $this->sanitizer->sanitize('<script>alert(1)</script>'));
        self::assertSame('gras', $this->sanitizer->sanitize('<b>gras</b>'));
        self::assertSame('', $this->sanitizer->sanitize('<img src=x onerror="alert(1)">'));
    }

    public function testEncodedMarkupIsRemovedToo(): void
    {
        self::assertSame('alert(1)', $this->sanitizer->sanitize('&lt;script&gt;alert(1)&lt;/script&gt;'));
        self::assertSame('alert(1)', $this->sanitizer->sanitize('&amp;lt;script&amp;gt;alert(1)&amp;lt;/script&amp;gt;'));
    }

    public function testComparisonsInPlainTextSurvive(): void
    {
        self::assertSame('5 < 6 et 7 > 3', $this->sanitizer->sanitize('5 < 6 et 7 > 3'));
    }

    public function testControlAndDirectionOverrideCharactersAreRemoved(): void
    {
        self::assertSame('parfait', $this->sanitizer->sanitize("par\x07fait"));
        self::assertSame('parfait', $this->sanitizer->sanitize("par\u{202E}fait"));
        self::assertSame('parfait', $this->sanitizer->sanitize("par\u{200B}fait"));
    }

    public function testNothingBecomesAnEmptyString(): void
    {
        self::assertSame('', $this->sanitizer->sanitize(null));
        self::assertSame('', $this->sanitizer->sanitize("  \n "));
    }
}
