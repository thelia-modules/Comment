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
 * put back a mention it leaves out. The verified purchase is one it left out: computed on the
 * paid orders and stored on every comment, shown to the moderator, and to no one else.
 *
 * Rendering the component needs a kernel, a theme and a database. These tests are the module's
 * own suite, which has none: they read the template instead, which is enough to catch the
 * regression that actually happened — the mention never being in the markup at all.
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
}
