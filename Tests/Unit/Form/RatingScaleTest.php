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

namespace Comment\Tests\Unit\Form;

use Comment\Form\Field\RatingType;
use PHPUnit\Framework\TestCase;

/**
 * The scale a shop rates on, read from `comment_max_rating`.
 *
 * A missing configuration row is the normal state of a shop that was installed before the
 * setting existed, and of any shop whose row was deleted. Read with a default of zero, that
 * shop draws a scale of zero stars: the block renders, the form offers nothing to click, and
 * nothing anywhere says why. So the scale has one rule, and the three places that need it
 * share it rather than each picking a default.
 */
final class RatingScaleTest extends TestCase
{
    public function testAShopWithoutTheSettingRatesOnTheDefaultScale(): void
    {
        self::assertSame(RatingType::DEFAULT_MAX, RatingType::scaleFrom(null));
        self::assertSame(RatingType::DEFAULT_MAX, RatingType::scaleFrom(''));
    }

    public function testAScaleOfZeroOrLessIsNotAScale(): void
    {
        self::assertSame(RatingType::DEFAULT_MAX, RatingType::scaleFrom('0'));
        self::assertSame(RatingType::DEFAULT_MAX, RatingType::scaleFrom('-3'));
    }

    public function testAShopKeepsTheScaleItConfigured(): void
    {
        self::assertSame(10, RatingType::scaleFrom('10'));
        self::assertSame(1, RatingType::scaleFrom('1'));
        self::assertSame(3, RatingType::scaleFrom(3));
    }

    public function testTextThatIsNotANumberFallsBackRatherThanRatingOnZero(): void
    {
        self::assertSame(RatingType::DEFAULT_MAX, RatingType::scaleFrom('five'));
    }

    /**
     * Three readers, one rule: the theme's form, the comment block of the shop, and the front
     * API operation that judges a posted rating against the scale.
     */
    public function testEveryReaderOfTheScaleGoesThroughTheSameRule(): void
    {
        $module = \dirname(__DIR__, 3);

        foreach ([
            '/Form/AddCommentForm.php',
            '/Twig/Comment.php',
            '/Api/State/CommentPostProcessor.php',
        ] as $file) {
            self::assertStringContainsString(
                'RatingType::scaleFrom(',
                (string) file_get_contents($module.$file),
                \sprintf('%s reads the scale with a default of its own', $file)
            );
        }
    }

    /**
     * A setting that governs what the shop shows and has no field is a setting only a database
     * client can change.
     */
    public function testTheScaleIsOfferedInTheConfigurationForm(): void
    {
        $module = \dirname(__DIR__, 3);

        self::assertStringContainsString(
            "'max_rating'",
            (string) file_get_contents($module.'/Form/ConfigurationForm.php')
        );
        self::assertStringContainsString(
            "ConfigQuery::write(\n                'comment_max_rating',",
            (string) file_get_contents($module.'/Controller/Back/CommentController.php')
        );
        self::assertStringContainsString(
            'form.max_rating',
            (string) file_get_contents($module.'/templates/backOffice/default-twig/Comment/module_configuration.html.twig')
        );
    }
}
