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

namespace Comment\Service\BackOffice;

use Comment\Model\Comment;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * The four comment statuses with their label and their Bootstrap contextual class.
 *
 * Replaces templates/backOffice/default/commons.html, which built the same map in Smarty.
 */
final readonly class CommentStatusCatalog
{
    public const TRANSLATION_DOMAIN = 'comment.bo.default';

    public function __construct(
        private TranslatorInterface $translator,
    ) {
    }

    /**
     * @return array<int, array{value: int, label: string, css: string}>
     */
    public function all(): array
    {
        return [
            Comment::PENDING => $this->entry(Comment::PENDING, 'Pending', 'secondary'),
            Comment::ACCEPTED => $this->entry(Comment::ACCEPTED, 'Accepted', 'success'),
            Comment::REFUSED => $this->entry(Comment::REFUSED, 'Refused', 'danger'),
            Comment::ABUSED => $this->entry(Comment::ABUSED, 'Abused', 'warning'),
        ];
    }

    /**
     * @return array{value: int, label: string, css: string}
     */
    public function get(?int $status): array
    {
        return $this->all()[$status ?? Comment::PENDING] ?? $this->entry((int) $status, 'Unknown', 'light');
    }

    /**
     * @return array{value: int, label: string, css: string}
     */
    private function entry(int $value, string $label, string $css): array
    {
        return [
            'value' => $value,
            'label' => $this->translator->trans($label, [], self::TRANSLATION_DOMAIN),
            'css' => $css,
        ];
    }
}
