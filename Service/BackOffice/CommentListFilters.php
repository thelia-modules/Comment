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

use Comment\Repository\CommentRepository;
use Symfony\Component\HttpFoundation\Request;

/**
 * What the comment list is filtered and paginated by, read from the query string.
 *
 * Keeps the same parameter names the Smarty back-office used (loop_status, loop_order,
 * loop_limit, page), so bookmarked admin URLs still work.
 */
final readonly class CommentListFilters
{
    public const DEFAULT_LIMIT = 20;

    public function __construct(
        public ?string $ref = null,
        public ?int $refId = null,
        public ?int $status = null,
        public string $order = CommentRepository::DEFAULT_ORDER,
        public int $page = 1,
        public int $limit = self::DEFAULT_LIMIT,
    ) {
    }

    public static function fromRequest(Request $request, ?string $ref = null, ?int $refId = null): self
    {
        $status = $request->query->get('loop_status');
        $limit = (int) $request->query->get('loop_limit', (string) self::DEFAULT_LIMIT);

        return new self(
            ref: $ref,
            refId: $refId,
            status: ('' === $status || null === $status) ? null : (int) $status,
            order: (string) $request->query->get('loop_order', CommentRepository::DEFAULT_ORDER),
            page: max(1, (int) $request->query->get('page', '1')),
            limit: $limit > 0 ? $limit : self::DEFAULT_LIMIT,
        );
    }

    public function withPage(int $page): self
    {
        return new self($this->ref, $this->refId, $this->status, $this->order, $page, $this->limit);
    }

    /**
     * @return array<string, int|string>
     */
    public function toQueryParams(): array
    {
        $params = [
            'loop_order' => $this->order,
            'loop_limit' => $this->limit,
            'page' => $this->page,
        ];

        if (null !== $this->status) {
            $params['loop_status'] = $this->status;
        }

        return $params;
    }
}
