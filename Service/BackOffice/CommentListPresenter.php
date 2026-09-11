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
use Comment\Repository\CommentRepository;
use Thelia\Model\CustomerQuery;
use Thelia\Tools\URL;

/**
 * Shapes the comment rows the Twig back-office renders: status, author, reference and the
 * admin URLs. The templates read arrays, never a Propel object.
 */
final readonly class CommentListPresenter
{
    public function __construct(
        private CommentRepository $commentRepository,
        private CommentStatusCatalog $statusCatalog,
        private CommentOrderCatalog $orderCatalog,
        private CommentReferenceResolver $referenceResolver,
    ) {
    }

    /**
     * @return array{
     *     rows: list<array<string, mixed>>,
     *     statuses: array<int, array{value: int, label: string, css: string}>,
     *     orders: array<string, string>,
     *     counts: array<int, int>,
     *     filters: CommentListFilters,
     *     total: int,
     *     pageCount: int,
     * }
     */
    public function present(CommentListFilters $filters, string $locale): array
    {
        $result = $this->commentRepository->search(
            ref: $filters->ref,
            refId: $filters->refId,
            status: $filters->status,
            order: $filters->order,
            page: $filters->page,
            limit: $filters->limit,
        );

        return [
            'rows' => array_map(
                fn (Comment $comment): array => $this->row($comment, $locale),
                $result['items'],
            ),
            'statuses' => $this->statusCatalog->all(),
            'orders' => $this->orderCatalog->all(),
            'counts' => $this->commentRepository->countByStatus($filters->ref, $filters->refId),
            'filters' => $filters,
            'total' => $result['total'],
            'pageCount' => (int) ceil($result['total'] / $filters->limit),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function row(Comment $comment, string $locale): array
    {
        return [
            'id' => $comment->getId(),
            'title' => $comment->getTitle(),
            'content' => $comment->getContent(),
            'rating' => $comment->getRating(),
            'verified' => (bool) $comment->getVerified(),
            'abuse' => $comment->getAbuse(),
            'locale' => $comment->getLocale(),
            'status' => $this->statusCatalog->get($comment->getStatus()),
            'createdAt' => $comment->getCreatedAt(),
            'updatedAt' => $comment->getUpdatedAt(),
            'author' => $this->author($comment),
            'reference' => $this->reference($comment, $locale),
            'editUrl' => URL::getInstance()->absoluteUrl('/admin/module/comment/update/'.$comment->getId()),
        ];
    }

    /**
     * @return array{name: ?string, email: ?string, customerId: ?int, customerUrl: ?string, isCustomer: bool}
     */
    private function author(Comment $comment): array
    {
        $customerId = $comment->getCustomerId();

        if (null === $customerId) {
            return [
                'name' => $comment->getUsername(),
                'email' => $comment->getEmail(),
                'customerId' => null,
                'customerUrl' => null,
                'isCustomer' => false,
            ];
        }

        $customer = CustomerQuery::create()->findPk($customerId);

        return [
            'name' => null === $customer
                ? $comment->getUsername()
                : trim($customer->getFirstname().' '.$customer->getLastname()),
            'email' => $customer?->getEmail() ?? $comment->getEmail(),
            'customerId' => $customerId,
            'customerUrl' => URL::getInstance()->absoluteUrl('/admin/customer/update', ['customer_id' => $customerId]),
            'isCustomer' => null !== $customer,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function reference(Comment $comment, string $locale): array
    {
        $reference = $this->referenceResolver->resolve($comment->getRef(), $comment->getRefId(), $locale);

        return [
            'ref' => $comment->getRef(),
            'refId' => $comment->getRefId(),
            'title' => $reference['title'],
            'typeTitle' => $reference['typeTitle'],
            'editUrl' => $reference['editUrl'],
            'viewUrl' => $reference['viewUrl'],
        ];
    }
}
