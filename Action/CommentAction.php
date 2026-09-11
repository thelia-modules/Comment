<?php

declare(strict_types=1);

/*
 * This file is part of the Thelia package.
 * http://www.thelia.net
 *
 * (c) OpenStudio <info@thelia.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/*      Copyright (c) OpenStudio */
/*      email : info@thelia.net */
/*      web : http://www.thelia.net */

/*      This program is free software; you can redistribute it and/or modify */
/*      it under the terms of the GNU General Public License as published by */
/*      the Free Software Foundation; either version 3 of the License */

/*      This program is distributed in the hope that it will be useful, */
/*      but WITHOUT ANY WARRANTY; without even the implied warranty of */
/*      MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the */
/*      GNU General Public License for more details. */

/*      You should have received a copy of the GNU General Public License */
/*	    along with this program. If not, see <http://www.gnu.org/licenses/>. */

namespace Comment\Action;

use Comment\Comment as CommentModule;
use Comment\Events\CommentAbuseEvent;
use Comment\Events\CommentChangeStatusEvent;
use Comment\Events\CommentCheckOrderEvent;
use Comment\Events\CommentComputeRatingEvent;
use Comment\Events\CommentCreateEvent;
use Comment\Events\CommentDefinitionEvent;
use Comment\Events\CommentDeleteEvent;
use Comment\Events\CommentEvents;
use Comment\Events\CommentReferenceGetterEvent;
use Comment\Events\CommentUpdateEvent;
use Comment\Exception\InvalidDefinitionException;
use Comment\Model\Comment;
use Comment\Model\CommentQuery;
use Comment\Repository\CommentStorageInterface;
use Comment\Repository\RatingMetaStorageInterface;
use Propel\Runtime\ActiveQuery\Criteria;
use Propel\Runtime\ActiveQuery\Join;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Thelia\Core\Template\ParserInterface;
use Thelia\Core\Translation\Translator;
use Thelia\Log\Tlog;
use Thelia\Mailer\MailerFactory;
use Thelia\Model\ConfigQuery;
use Thelia\Model\ContentQuery;
use Thelia\Model\CustomerQuery;
use Thelia\Model\LangQuery;
use Thelia\Model\Map\OrderProductTableMap;
use Thelia\Model\Map\OrderTableMap;
use Thelia\Model\Map\ProductSaleElementsTableMap;
use Thelia\Model\MessageQuery;
use Thelia\Model\MetaData;
use Thelia\Model\MetaDataQuery;
use Thelia\Model\OrderProductQuery;
use Thelia\Model\ProductQuery;
use Thelia\Tools\URL;

/**
 * CommentAction class where all actions are managed.
 *
 * Class CommentAction
 *
 * @author Michaël Espeche <michael.espeche@gmail.com>
 */
class CommentAction implements EventSubscriberInterface
{
    /** @var TranslatorInterface|null */
    protected $translator;

    /** @var ParserInterface|null */
    protected $parser;

    /** @var MailerFactory|null */
    protected $mailer;

    /** @var EventDispatcherInterface|null */
    protected $dispatcher;

    /** @var CommentStorageInterface|null */
    protected $commentRepository;

    /** @var RatingMetaStorageInterface|null */
    protected $ratingMeta;

    public function __construct(TranslatorInterface $translator, ParserInterface $parser, MailerFactory $mailer, EventDispatcherInterface $dispatcher, CommentStorageInterface $commentRepository, RatingMetaStorageInterface $ratingMeta)
    {
        $this->translator = $translator;
        $this->parser = $parser;
        $this->mailer = $mailer;
        $this->dispatcher = $dispatcher;
        $this->commentRepository = $commentRepository;
        $this->ratingMeta = $ratingMeta;
    }

    /**
     * Normalize a value on its way into the comment table.
     *
     * `verified`, `status`, `rating` and `abuse` are TINYINT / INTEGER columns, so Propel
     * generates `?int` setters. The events carry booleans (`Twig\Comment::save()` and
     * `Controller\Back\CommentController::publishAction()` both pass `true`), which
     * `declare(strict_types=1)` rejects outright instead of coercing. Convert here, at the
     * boundary, and keep null as null: the columns are nullable and carry no default.
     */
    private static function toColumnInt(bool|int|string|null $value): ?int
    {
        return null === $value ? null : (int) $value;
    }

    public function create(CommentCreateEvent $event): void
    {
        $customerId = null === $event->getCustomerId() ? null : (int) $event->getCustomerId();

        // One comment per customer and per element. A customer posting again on the same
        // product is editing what they already said, so the row is rewritten in place: two
        // rows would both weigh in the average and the shop would show the same buyer twice.
        // The status comes from the event, which carries the moderation rule in force, so an
        // edited comment goes back through moderation instead of staying published.
        $comment = null === $customerId
            ? null
            : $this->commentRepository->findOneByCustomerAndReference(
                $customerId,
                (string) $event->getRef(),
                (int) $event->getRefId()
            );

        $previousStatus = $comment?->getStatus();

        $comment ??= new Comment();

        $comment
            ->setRef($event->getRef())
            ->setRefId($event->getRefId())
            ->setCustomerId($event->getCustomerId())
            ->setUsername($event->getUsername())
            ->setEmail($event->getEmail())
            ->setLocale($event->getLocale())
            ->setTitle($event->getTitle())
            ->setContent($event->getContent())
            ->setStatus(self::toColumnInt($event->getStatus()))
            ->setVerified(self::toColumnInt($event->isVerified()))
            ->setRating(self::toColumnInt($event->getRating()))
            // An edit must not wipe the abuse reports the comment already collected: the
            // front never sends that counter.
            ->setAbuse(self::toColumnInt($event->getAbuse()) ?? $comment->getAbuse());

        $this->commentRepository->save($comment);

        $event->setComment($comment);

        // Recompute when the comment is live, and also when a published one has just gone
        // back to moderation: the average has to lose it.
        if (Comment::ACCEPTED === $comment->getStatus() || Comment::ACCEPTED === $previousStatus) {
            $this->dispatchRatingCompute(
                $comment->getRef(),
                $comment->getRefId()
            );
        }
    }

    public function update(CommentUpdateEvent $event): void
    {
        $comment = $this->commentRepository->findById((int) $event->getId());

        if (null === $comment) {
            return;
        }

        $comment
            ->setRef($event->getRef())
            ->setRefId($event->getRefId())
            ->setCustomerId($event->getCustomerId())
            ->setUsername($event->getUsername())
            ->setEmail($event->getEmail())
            ->setLocale($event->getLocale())
            ->setTitle($event->getTitle())
            ->setContent($event->getContent())
            ->setStatus(self::toColumnInt($event->getStatus()))
            ->setVerified(self::toColumnInt($event->isVerified()))
            ->setRating(self::toColumnInt($event->getRating()))
            ->setAbuse(self::toColumnInt($event->getAbuse()));

        $this->commentRepository->save($comment);

        $event->setComment($comment);

        $this->dispatchRatingCompute(
            $comment->getRef(),
            $comment->getRefId()
        );
    }

    public function delete(CommentDeleteEvent $event): void
    {
        if (null !== $comment = CommentQuery::create()->findPk($event->getId())) {
            $comment->delete();

            $event->setComment($comment);

            if (Comment::ACCEPTED === $comment->getStatus()) {
                $this->dispatchRatingCompute(
                    $comment->getRef(),
                    $comment->getRefId()
                );
            }
        }
    }

    public function abuse(CommentAbuseEvent $event): void
    {
        if (null !== $comment = CommentQuery::create()->findPk($event->getId())) {
            $comment->setAbuse($comment->getAbuse() + 1);
            $comment->save();

            $event->setComment($comment);
        }
    }

    public function statusChange(CommentChangeStatusEvent $event): void
    {
        $comment = $this->commentRepository->findById((int) $event->getId());

        // The caller answers the browser with $event->getComment(): a comment that is gone has
        // to be said out loud, not left as a null for the caller to dereference.
        if (null === $comment) {
            throw new \InvalidArgumentException('Comment '.$event->getId().' does not exist');
        }

        // Always carried, even when there is nothing to change: moderating the same row twice
        // is a double click, not an error.
        $event->setComment($comment);

        if ($comment->getStatus() === $event->getNewStatus()) {
            return;
        }

        $comment->setStatus($event->getNewStatus());
        $this->commentRepository->save($comment);

        $this->dispatchRatingCompute(
            $comment->getRef(),
            $comment->getRefId()
        );
    }

    public function productRatingCompute(CommentComputeRatingEvent $event): void
    {
        // The literal, not MetaData::PRODUCT_KEY: reading a constant off a Propel model class
        // loads its generated base, which only exists once the model tree has been built.
        if ('product' !== $event->getRef()) {
            return;
        }

        $ref = (string) $event->getRef();
        $refId = (int) $event->getRefId();

        $aggregate = $this->commentRepository->acceptedRatingAggregate($ref, $refId);

        // No accepted comment carries a rating any more: what was stored has to go, or the
        // product page keeps showing an average built from comments nobody can read.
        if (null === $aggregate['average']) {
            $event->setRating(null);

            $this->ratingMeta->clear($ref, $refId);

            return;
        }

        $average = round($aggregate['average'], 2);

        $event->setRating($average);

        $this->ratingMeta->store($ref, $refId, $average, $aggregate['count']);
    }

    /**
     * Dispatch an event to compute an average rating.
     *
     * @param string $ref
     * @param int    $refId
     */
    protected function dispatchRatingCompute($ref, $refId): void
    {
        $ratingEvent = new CommentComputeRatingEvent();

        $ratingEvent
            ->setRef($ref)
            ->setRefId($refId);

        $this->dispatcher->dispatch(
            $ratingEvent,
            CommentEvents::COMMENT_RATING_COMPUTE
        );
    }

    public function getRefrence(CommentReferenceGetterEvent $event): void
    {
        if ('product' === $event->getRef()) {
            $product = ProductQuery::create()->findPk($event->getRefId());
            if (null !== $product) {
                $event->setTypeTitle($this->translator->trans('Product', [], 'core', $event->getLocale()));
                $event->setTitle($product->setLocale($event->getLocale())->getTitle());
                $event->setViewUrl($product->getUrl($event->getLocale()));
                $event->setEditUrl(
                    URL::getInstance()->absoluteUrl(
                        '/admin/products/update',
                        ['product_id' => $product->getId()]
                    )
                );
                $event->setObject($product);
            }
        } elseif ('content' === $event->getRef()) {
            $content = ContentQuery::create()->findPk($event->getRefId());
            if (null !== $content) {
                $event->setTypeTitle($this->translator->trans('Content', [], 'core', $event->getLocale()));
                $event->setTitle($content->setLocale($event->getLocale())->getTitle());
                $event->setViewUrl($content->getUrl($event->getLocale()));
                $event->setEditUrl(
                    URL::getInstance()->absoluteUrl(
                        '/admin/contents/update',
                        ['product_id' => $content->getId()]
                    )
                );
                $event->setObject($content);
            }
        }
    }

    public function getDefinition(CommentDefinitionEvent $event): void
    {
        $config = $event->getConfig();

        if (!\in_array($event->getRef(), $config['ref_allowed'])) {
            throw new InvalidDefinitionException($this->translator->trans('Reference %ref is not allowed', ['%ref' => $event->getRef()], CommentModule::MESSAGE_DOMAIN));
        }

        $eventName = CommentEvents::COMMENT_GET_DEFINITION.'.'.$event->getRef();
        $this->dispatcher->dispatch($event, $eventName);

        // is only customer is authorized to publish
        if ($config['only_customer'] && null === $event->getCustomer()) {
            throw new InvalidDefinitionException($this->translator->trans('Only customer are allowed to publish comment', [], CommentModule::MESSAGE_DOMAIN), false);
        }

        if (null !== $event->getCustomer()) {
            // is customer already have published something
            $comment = CommentQuery::create()
                ->filterByCustomerId($event->getCustomer()->getId())
                ->filterByRef($event->getRef())
                ->filterByRefId($event->getRefId())
                ->findOne();

            if (null !== $comment) {
                $event->setComment($comment);
            }
        }
    }

    public function getProductDefinition(CommentDefinitionEvent $event): void
    {
        $config = $event->getConfig();

        $event->setRating(true);

        $product = ProductQuery::create()->findPk($event->getRefId());
        if (null === $product) {
            throw new InvalidDefinitionException($this->translator->trans('Product %id does not exist', ['%ref' => $event->getRef()], CommentModule::MESSAGE_DOMAIN));
        }

        // is comment is authorized on this product
        $commentProductActivated = MetaDataQuery::getVal(
            Comment::META_KEY_ACTIVATED,
            MetaData::PRODUCT_KEY,
            $product->getId()
        );

        // not defined, get the global config
        if ('1' !== $commentProductActivated) {
            if ('0' === $commentProductActivated || false === $config['activated']) {
                throw new InvalidDefinitionException($this->translator->trans('Comment not activated on this element.', ['%ref' => $event->getRef()], CommentModule::MESSAGE_DOMAIN));
            }
        }

        $verified = false;
        if (null !== $event->getCustomer()) {
            // customer has bought the product
            $productBoughtCount = OrderProductQuery::getSaleStats(
                $product->getRef(),
                null,
                null,
                [2, 3, 4],
                $event->getCustomer()->getId()
            );

            if ($config['only_verified']) {
                if (0 === $productBoughtCount) {
                    throw new InvalidDefinitionException($this->translator->trans('Only customers who have bought this product can publish comment', [], CommentModule::MESSAGE_DOMAIN), false);
                }
            }

            $verified = 0 !== $productBoughtCount;
        } else {
            $verified = false;
        }

        $event->setVerified($verified);
    }

    public function getContentDefinition(CommentDefinitionEvent $event): void
    {
        $config = $event->getConfig();

        $event->setVerified(true);
        $event->setRating(false);

        // is comment is authorized on this product
        $commentProductActivated = MetaDataQuery::getVal(
            Comment::META_KEY_ACTIVATED,
            MetaData::CONTENT_KEY,
            $event->getRefId()
        );

        // not defined, get the global config
        if ('1' !== $commentProductActivated) {
            if ('0' === $commentProductActivated || false === $config['activated']) {
                throw new InvalidDefinitionException($this->translator->trans('Comment not activated on this element.', ['%ref' => $event->getRef()], CommentModule::MESSAGE_DOMAIN));
            }
        }
    }

    public function requestCustomerDemand(CommentCheckOrderEvent $event): void
    {
        $config = CommentModule::getConfig();
        $nbDays = $config['request_customer_ttl'];

        if (0 !== $nbDays) {
            $endDate = new \DateTime('NOW');
            $endDate->setTime(0, 0, 0);
            $endDate->sub(new \DateInterval('P'.$nbDays.'D'));

            $startDate = clone $endDate;
            $startDate->sub(new \DateInterval('P1D'));

            $pseJoin = new Join(
                OrderProductTableMap::COL_PRODUCT_SALE_ELEMENTS_ID,
                ProductSaleElementsTableMap::COL_ID,
                Criteria::INNER_JOIN
            );

            $products = OrderProductQuery::create()
                ->useOrderQuery()
                ->filterByInvoiceDate($startDate, Criteria::GREATER_EQUAL)
                ->filterByInvoiceDate($endDate, Criteria::LESS_THAN)
                ->addAsColumn('customerId', OrderTableMap::COL_CUSTOMER_ID)
                ->addAsColumn('orderId', OrderTableMap::COL_ID)
                ->endUse()
                ->addJoinObject($pseJoin)
                ->addAsColumn('pseId', OrderProductTableMap::COL_PRODUCT_SALE_ELEMENTS_ID)
                ->addAsColumn('productId', ProductSaleElementsTableMap::COL_PRODUCT_ID)
                ->select(
                    [
                        'customerId',
                        'orderId',
                        'pseId',
                        'productId',
                    ]
                )
                ->find()
                ->toArray();

            if (empty($products)) {
                return;
            }

            $customerProducts = array_reduce(
                $products,
                static function ($result, $item) {
                    if (!\array_key_exists($item['customerId'], $result)) {
                        $result[$item['customerId']] = [];
                    }
                    if (!\in_array($item['productId'], $result[$item['customerId']])) {
                        $result[$item['customerId']][] = $item['productId'];
                    }

                    return $result;
                },
                []
            );

            $customerIds = array_keys($customerProducts);

            // Who already said what they think, so they are not asked twice.
            $customerComments = $this->commentRepository->findCommentedProductIdsByCustomer(
                array_map('intval', $customerIds)
            );

            foreach ($customerIds as $customerId) {
                $send = false;

                if (!\array_key_exists($customerId, $customerComments)) {
                    $send = true;
                } else {
                    $noCommentsPosted = array_intersect(
                        $customerComments[$customerId],
                        $customerProducts[$customerId]
                    );

                    if (empty($noCommentsPosted)) {
                        $send = true;
                    }
                }

                if ($send) {
                    try {
                        $this->sendCommentRequestCustomerMail($customerId, $customerProducts[$customerId]);
                    } catch (\Exception $ex) {
                        Tlog::getInstance()->error($ex->getMessage());
                    }
                }
            }
        }
    }

    /**
     * Translate an email's strings up front, in the module's own catalogue.
     *
     * Twig's |trans reaches the Symfony translator, which does not carry a module's I18n/*.php
     * files: on the front office it answers with the key, so an email posted by a visitor would
     * go out in English whatever the locale. The back-office decorator only routes module
     * domains on an admin request, so it cannot be relied on either. Thelia's own translator,
     * injected here, is the one that reads those catalogues.
     *
     * @param array<string, array{0: string, 1: array<string, mixed>}> $strings id and parameters, by label key
     *
     * @return array<string, string>
     */
    protected function emailLabels(array $strings, ?string $locale): array
    {
        $labels = [];

        foreach ($strings as $key => [$id, $parameters]) {
            $labels[$key] = $this->translator->trans(
                $id,
                $parameters,
                CommentModule::MESSAGE_DOMAIN_EMAIL,
                $locale
            );
        }

        return $labels;
    }

    protected function sendCommentRequestCustomerMail($customerId, array $productIds): void
    {
        $contact_email = ConfigQuery::getStoreEmail();

        if ($contact_email) {
            $message = MessageQuery::create()
                ->filterByName('comment_request_customer')
                ->findOne();

            if (null === $message) {
                throw new \Exception("Failed to load message 'comment_request_customer'.");
            }

            $customer = CustomerQuery::create()->findPk($customerId);

            if (null === $customer) {
                throw new \Exception(\sprintf("Failed to load customer '%s'.", $customerId));
            }

            $locale = $customer->getCustomerLang()->getLocale();

            $message->setLocale($locale);

            $this->mailer->sendEmailToCustomer(
                $message->getName(),
                $customer,
                [
                    'customer_firstname' => $customer->getFirstname(),
                    'customer_lastname' => $customer->getLastname(),
                    'product_ids' => array_map('intval', $productIds),
                    'lang_id' => $customer->getCustomerLang()->getId(),
                    'labels' => $this->emailLabels(
                        [
                            'subject' => ['Share your opinion on your recent order', []],
                            'heading' => ['Your opinion matters', []],
                            'dear' => ['Dear', []],
                            'thanks' => [
                                'Thank you for your order on our online store %store_name',
                                ['%store_name' => ConfigQuery::getStoreName()],
                            ],
                            'invite' => ['It would be great to share your thoughts on products with other customers.', []],
                            'products' => ['You can post comments on this products: ', []],
                            'contact' => ['Feel free to contact us for any further information', []],
                            'regards' => ['Best Regards.', []],
                        ],
                        $locale
                    ),
                ]
            );

            Tlog::getInstance()->debug(
                'Message sent to customer '.$customer->getEmail().' to ask for comments'
            );
        }
    }

    /**
     * Notify shop managers of a new comment.
     */
    public function notifyAdminOfNewComment(CommentCreateEvent $event): void
    {
        $config = CommentModule::getConfig();
        if (!$config['notify_admin_new_comment']) {
            return;
        }

        $comment = $event->getComment();
        if ($comment === null) {
            return;
        }

        // get the default shop locale
        $shopLang = LangQuery::create()->findOneByByDefault(true);
        if ($shopLang !== null) {
            $shopLocale = $shopLang->getLocale();
        } else {
            $shopLocale = null;
        }

        $getCommentRefEvent = new CommentReferenceGetterEvent(
            $comment->getRef(),
            $comment->getRefId(),
            $shopLocale
        );
        $this->dispatcher->dispatch($getCommentRefEvent, CommentEvents::COMMENT_REFERENCE_GETTER);

        // Read back through the repository rather than trusting whatever state the event's
        // model is in, and hand the template plain values: the {loop type="comment"} it used
        // to call no longer exists.
        $posted = $this->commentRepository->findById((int) $comment->getId()) ?? $comment;

        $adminUrl = URL::getInstance()->absoluteUrl('/admin/module/comment/update/'.$posted->getId());

        // A mail that cannot be built must never take the visitor's comment down with it: this
        // listener runs inside the COMMENT_CREATE dispatch, which Twig\Comment::save() does not
        // guard. Message::getMessageBody() renders the layout without catching, so a shop whose
        // email template set has no layout would otherwise 500 on every post.
        try {
            $this->mailer->sendEmailToShopManagers(
                'new_comment_notification_admin',
                [
                    'comment_id' => $posted->getId(),
                    'comment' => [
                        'id' => $posted->getId(),
                        'title' => $posted->getTitle(),
                        'content' => $posted->getContent(),
                        'rating' => $posted->getRating(),
                        'adminUrl' => $adminUrl,
                    ],
                    'ref_title' => $getCommentRefEvent->getTitle(),
                    'ref_type_title' => $getCommentRefEvent->getTypeTitle(),
                    'labels' => $this->emailLabels(
                        [
                            'subject' => [
                                'New comment on %ref_type_title "%ref_title"',
                                [
                                    '%ref_type_title' => mb_strtolower((string) $getCommentRefEvent->getTypeTitle()),
                                    '%ref_title' => $getCommentRefEvent->getTitle(),
                                ],
                            ],
                            'heading' => ['New customer comment', []],
                            'intro' => [
                                'We inform you that a new customer comment has been posted for the %ref_type_title "%ref_title"',
                                [
                                    '%ref_type_title' => mb_strtolower((string) $getCommentRefEvent->getTypeTitle()),
                                    '%ref_title' => $getCommentRefEvent->getTitle(),
                                ],
                            ],
                            'rating' => ['Rating: ', []],
                            'title' => ['Title: ', []],
                            'content' => ['Content: ', []],
                            'link' => ['You can now activate this comment by going to the comment management interface.', []],
                            'linkWithUrl' => [
                                'You can now activate this comment by going to the comment management interface: %comment_management_link',
                                ['%comment_management_link' => $adminUrl],
                            ],
                        ],
                        $shopLocale
                    ),
                ]
            );
        } catch (\Exception $ex) {
            Tlog::getInstance()->error(
                'Failed to notify shop managers of comment '.$posted->getId().': '.$ex->getMessage()
            );
        }
    }

    /**
     * Returns an array of event names this subscriber wants to listen to.
     *
     * The array keys are event names and the value can be:
     *
     *  * The method name to call (priority defaults to 0)
     *  * An array composed of the method name to call and the priority
     *  * An array of arrays composed of the method names to call and respective
     *    priorities, or 0 if unset
     *
     * For instance:
     *
     *  * array('eventName' => 'methodName')
     *  * array('eventName' => array('methodName', $priority))
     *  * array('eventName' => array(array('methodName1', $priority), array('methodName2'))
     *
     * @return array The event names to listen to
     *
     * @api
     */
    public static function getSubscribedEvents()
    {
        return [
            CommentEvents::COMMENT_CREATE => [
                ['create', 128],
                ['notifyAdminOfNewComment', 64],
            ],
            CommentEvents::COMMENT_DELETE => ['delete', 128],
            CommentEvents::COMMENT_UPDATE => ['update', 128],
            CommentEvents::COMMENT_ABUSE => ['abuse', 128],
            CommentEvents::COMMENT_STATUS_UPDATE => ['statusChange', 128],
            CommentEvents::COMMENT_RATING_COMPUTE => ['productRatingCompute', 128],
            CommentEvents::COMMENT_REFERENCE_GETTER => ['getRefrence', 128],
            CommentEvents::COMMENT_CUSTOMER_DEMAND => ['requestCustomerDemand', 128],
            CommentEvents::COMMENT_GET_DEFINITION => ['getDefinition', 128],
            CommentEvents::COMMENT_GET_DEFINITION_PRODUCT => ['getProductDefinition', 128],
            CommentEvents::COMMENT_GET_DEFINITION_CONTENT => ['getContentDefinition', 128],
        ];
    }
}
