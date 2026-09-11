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

namespace Comment\Tests\Unit\Template;

use PHPUnit\Framework\TestCase;

/**
 * What the comment block of a product page actually puts on the shop.
 *
 * The block is the module's own template, not the theme's, so nothing outside this module can
 * put back a mention it stops rendering. Two of them were computed and stored for a long time
 * without ever reaching a visitor: the verified purchase, which the back office alone showed,
 * and the abuse counter, which moderation read and no one could raise.
 *
 * Rendering the component needs a kernel, a theme and a database. These tests are the module's
 * own suite, which has none: they read the template instead, which is enough to catch the
 * regression that actually happened — the mention disappearing from the markup.
 */
final class CommentBlockTest extends TestCase
{
    private string $template;

    private string $component;

    private string $styles;

    protected function setUp(): void
    {
        $module = \dirname(__DIR__, 3);

        $this->template = (string) file_get_contents($module.'/templates/components/Comment.html.twig');
        $this->component = (string) file_get_contents($module.'/Twig/Comment.php');
        $this->styles = (string) file_get_contents($module.'/templates/frontOffice/default/assets/comment.css');
    }

    /**
     * The whole point of the verified flag: the shop vouches for the review in front of the
     * next buyer. Computed on the paid orders, shown to the moderator, and until now to no one
     * else.
     */
    public function testTheVerifiedPurchaseMentionReachesTheShop(): void
    {
        self::assertStringContainsString('comment.verified', $this->template);
        self::assertStringContainsString('labels.verified', $this->template);
        self::assertStringContainsString('.Comment-verified', $this->styles);
    }

    public function testTheVerifiedFlagIsHandedToTheTemplateAsABoolean(): void
    {
        self::assertStringContainsString("'verified' => (bool) \$comment->getVerified()", $this->component);
        self::assertStringContainsString("'verified' => \$this->translateFront('Verified')", $this->component);
    }

    /**
     * The report control, and the action behind it. The abuse column and the "Abused" status
     * existed from the start with no way for a visitor to raise either.
     */
    public function testAVisitorIsOfferedAWayToReportAComment(): void
    {
        self::assertStringContainsString('Comment-report', $this->template);
        self::assertStringContainsString("action: 'report'", $this->template);
        self::assertStringContainsString('commentId: comment.id', $this->template);
    }

    public function testTheReportActionIsWiredOnTheComponent(): void
    {
        self::assertMatchesRegularExpression(
            '/#\[LiveAction\]\s*public function report\(#\[LiveArg\] int \$commentId\): void/',
            $this->component
        );
    }

    /**
     * A control offered to someone who already used it is a control that lies: the row carries
     * what the visitor's session remembers, so the button gives way to a plain mention.
     */
    public function testAlreadyReportedIsShownRatherThanOfferedAgain(): void
    {
        self::assertStringContainsString('comment.reported', $this->template);
        self::assertStringContainsString('labels.reported', $this->template);
        self::assertStringContainsString('alreadyReported', $this->component);
    }
}
