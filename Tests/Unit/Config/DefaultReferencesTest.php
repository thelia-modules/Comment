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

namespace Comment\Tests\Unit\Config;

use Comment\Comment;
use Comment\Hook\Theme\CommentThemeHook;
use PHPUnit\Framework\TestCase;

/**
 * A shop must not be shipped with a setting that promises something the shop cannot show.
 *
 * The theme hook answers one place only. Every reference the default setting allows has to be
 * a reference a visitor can actually comment on, otherwise the merchant reads the setting as a
 * promise and finds nothing on his shop.
 */
final class DefaultReferencesTest extends TestCase
{
    public function testTheDefaultAllowsOnlyReferencesTheThemeHookRenders(): void
    {
        $hooked = $this->hookedReferences();
        $allowed = explode(',', Comment::CONFIG_REF_ALLOWED);

        foreach ($allowed as $reference) {
            self::assertContains(
                $reference,
                $hooked,
                \sprintf(
                    'The default setting allows comments on "%s", but no theme hook renders a comment '
                    .'block for it: the merchant would see the setting and nothing on the shop.',
                    $reference
                )
            );
        }
    }

    public function testTheProductStaysAllowedByDefault(): void
    {
        self::assertContains('product', explode(',', Comment::CONFIG_REF_ALLOWED));
    }

    /**
     * The references the theme hook actually answers, read from the hook itself.
     *
     * @return list<string>
     */
    private function hookedReferences(): array
    {
        $reflection = new \ReflectionClass(CommentThemeHook::class);
        $hookName = (string) $reflection->getConstant('HOOK_NAME');

        return [strstr($hookName, '.', true) ?: $hookName];
    }
}
