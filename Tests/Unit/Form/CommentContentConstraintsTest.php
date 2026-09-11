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

use Comment\Form\CommentContentConstraints;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Validation;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * What a comment's body has to be to be accepted.
 *
 * The field was optional and unbounded on a CLOB column: an empty comment went online, and
 * nothing stopped a body of any size from being written to the database.
 */
final class CommentContentConstraintsTest extends TestCase
{
    private ValidatorInterface $validator;

    protected function setUp(): void
    {
        $this->validator = Validation::createValidator();
    }

    public function testAnEmptyCommentIsRefused(): void
    {
        self::assertCount(1, $this->violations(''));
        self::assertCount(1, $this->violations(null));
    }

    public function testACommentOfNothingButSpacesIsRefused(): void
    {
        self::assertCount(1, $this->violations("   \n\t "));
    }

    public function testACommentLongerThanTheLimitIsRefused(): void
    {
        $limit = CommentContentConstraints::MAX_LENGTH;

        self::assertCount(0, $this->violations(str_repeat('a', $limit)));
        self::assertCount(1, $this->violations(str_repeat('a', $limit + 1)));
    }

    public function testAnOrdinaryCommentPasses(): void
    {
        self::assertCount(0, $this->violations('Produit conforme a la description, livraison rapide.'));
    }

    private function violations(?string $content): \Countable
    {
        return $this->validator->validate($content, CommentContentConstraints::all());
    }
}
