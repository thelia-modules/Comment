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
/*      file that was distributed with this source code.                             */
/*************************************************************************************/

namespace Comment\Hook;

use Comment\Comment;
use Comment\Model\Comment as CommentModel;
use Comment\Service\BackOffice\CommentListFilters;
use Comment\Service\BackOffice\CommentListPresenter;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Thelia\Core\Event\Hook\HookRenderBlockEvent;
use Thelia\Core\Event\Hook\HookRenderEvent;
use Thelia\Core\Hook\BaseHook;
use Thelia\Core\Template\Parser\ParserResolver;
use Thelia\Model\MetaDataQuery;
use Thelia\Tools\URL;

/**
 * Back-office rendering, in the default-twig admin.
 *
 * The JavaScript hooks the Smarty back-office declared (main.footer-js, product.edit-js,
 * content.edit-js, which injected assets/js/comment.js) are gone: the Twig templates carry
 * the few lines of script they need.
 *
 * @author Julien Chanséaume <jchanseaume@openstudio.fr>
 */
class BackHook extends BaseHook
{
    private const TAB_COMMENT_LIMIT = 10;

    public function __construct(
        private readonly CommentListPresenter $commentListPresenter,
        private readonly RequestStack $requestStack,
        ?EventDispatcherInterface $dispatcher = null,
        ?ParserResolver $parserResolver = null,
    ) {
        parent::__construct($dispatcher, $parserResolver);
    }

    public static function getSubscribedHooks(): array
    {
        return [
            'module.configuration' => [
                ['type' => 'back', 'method' => 'onModuleConfiguration'],
            ],
            'main.top-menu-tools' => [
                ['type' => 'back', 'method' => 'onMainTopMenuTools'],
            ],
            'product.tab-content' => [
                ['type' => 'back', 'method' => 'onProductTabContent'],
            ],
            'content.tab-content' => [
                ['type' => 'back', 'method' => 'onContentTabContent'],
            ],
        ];
    }

    public function onModuleConfiguration(HookRenderEvent $event): void
    {
        $event->add($this->render('Comment/module_configuration.html.twig'));
    }

    /**
     * Adds an entry in the admin tools menu.
     */
    public function onMainTopMenuTools(HookRenderBlockEvent $event): void
    {
        $event->add(
            [
                'id' => 'tools_menu_comment',
                'class' => '',
                'url' => URL::getInstance()->absoluteUrl('/admin/module/comments'),
                'title' => $this->trans('Comments', [], Comment::MESSAGE_DOMAIN),
            ]
        );
    }

    public function onProductTabContent(HookRenderEvent $event): void
    {
        $this->onTabContent($event, 'product');
    }

    public function onContentTabContent(HookRenderEvent $event): void
    {
        $this->onTabContent($event, 'content');
    }

    protected function onTabContent(HookRenderEvent $event, string $ref): void
    {
        $refId = $this->resolveRefId($event, $ref);
        $locale = $this->requestStack->getCurrentRequest()?->getLocale() ?? 'en_US';

        $filters = new CommentListFilters(ref: $ref, refId: $refId, limit: self::TAB_COMMENT_LIMIT);

        $event->add($this->render(
            'Comment/tab-content.html.twig',
            array_merge(
                $this->commentListPresenter->present($filters, $locale),
                [
                    'ref' => $ref,
                    'id' => $refId,
                    'activated' => (string) MetaDataQuery::getVal(
                        CommentModel::META_KEY_ACTIVATED,
                        $ref,
                        $refId,
                        '-1'
                    ),
                ]
            )
        ));
    }

    /**
     * The Twig back-office names the hook argument after the entity: product.tab-content
     * passes {product: id}, content.tab-content passes {content: id, content_id: id}. The
     * Smarty back-office passed {id: id}, which is kept as a last resort.
     */
    private function resolveRefId(HookRenderEvent $event, string $ref): int
    {
        foreach ([$ref, $ref.'_id', 'id'] as $key) {
            $value = $event->getArgument($key);

            if (null !== $value && '' !== $value) {
                return (int) $value;
            }
        }

        return 0;
    }
}
