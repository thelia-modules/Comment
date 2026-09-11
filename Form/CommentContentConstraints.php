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

namespace Comment\Form;

use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

/**
 * What a comment's body has to be, for the front form and for the back office alike.
 *
 * The column is a CLOB, so nothing in the database bounds what is written to it, and an
 * empty body used to go online with the rest of the comment.
 */
final class CommentContentConstraints
{
    /**
     * Long enough for anything a customer has to say about a product, short enough that a
     * single row cannot be used to fill the table.
     */
    public const MAX_LENGTH = 5000;

    /**
     * @return list<NotBlank|Length>
     */
    public static function all(): array
    {
        return [
            // Trimmed first: a body of nothing but spaces is an empty body.
            new NotBlank(['normalizer' => 'trim']),
            new Length(['max' => self::MAX_LENGTH]),
        ];
    }
}
