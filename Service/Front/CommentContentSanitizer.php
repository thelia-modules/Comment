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

namespace Comment\Service\Front;

/**
 * Cleans a posted comment on its way to the front office.
 *
 * A comment is plain text a visitor typed, so anything that looks like a tag is either an
 * attempt at markup or noise, and neither belongs on the product page. The core carries no
 * HTML sanitizer and symfony/html-sanitizer is not among its dependencies, so the work is
 * done here, on the narrow shape the column actually holds.
 *
 * Only the front office goes through this: the back office shows a moderator the text as it
 * was stored, which is what they have to judge.
 *
 * Tags are matched on a real tag name rather than with strip_tags(), which reads everything
 * between a "<" and the next ">" as a tag and would turn "5 < 6 et 7 > 3" into "5  3".
 */
final readonly class CommentContentSanitizer
{
    private const TAG = '#</?[a-zA-Z][a-zA-Z0-9:-]*(\s[^<>]*)?/?>#';

    /**
     * C0 controls except tab and newline, DEL, then the zero-width and direction-override
     * characters: they are invisible and make the text read differently from what is stored.
     */
    private const INVISIBLE = '#[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]|\x{200B}|\x{200C}|\x{200D}|\x{200E}|\x{200F}|\x{202A}|\x{202B}|\x{202C}|\x{202D}|\x{202E}|\x{2066}|\x{2067}|\x{2068}|\x{2069}|\x{FEFF}#u';

    public function sanitize(?string $text): string
    {
        $clean = (string) $text;

        // Encoded markup is markup. Decoding once and stripping once is not enough: a doubly
        // encoded tag would come back as a tag on the next decode, so this runs until the
        // text stops changing.
        for ($pass = 0; $pass < 4; ++$pass) {
            $before = $clean;

            $clean = (string) preg_replace(
                self::TAG,
                '',
                html_entity_decode($clean, \ENT_QUOTES | \ENT_HTML5, 'UTF-8')
            );

            if ($before === $clean) {
                break;
            }
        }

        $clean = (string) preg_replace(self::INVISIBLE, '', $clean);

        return trim($clean);
    }
}
