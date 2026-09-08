<?php
/*************************************************************************************/
/*                                                                                   */
/*      Thelia                                                                       */
/*                                                                                   */
/*      Copyright (c) OpenStudio                                                     */
/*      email : info@thelia.net                                                      */
/*      web : http://www.thelia.net                                                  */
/*                                                                                   */
/*      This program is free software; you can redistribute it and/or modify         */
/*      it under the terms of the GNU General Public License as published by         */
/*      the Free Software Foundation; either version 3 of the License                */
/*                                                                                   */
/*      This program is distributed in the hope that it will be useful,              */
/*      but WITHOUT ANY WARRANTY; without even the implied warranty of               */
/*      MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the                */
/*      GNU General Public License for more details.                                 */
/*                                                                                   */
/*      You should have received a copy of the GNU General Public License            */
/*      along with this program. If not, see <http://www.gnu.org/licenses/>.         */
/*                                                                                   */
/*************************************************************************************/

namespace Comment\Controller\Front;

use Comment\Comment;
use Comment\Events\CommentAbuseEvent;
use Comment\Events\CommentCreateEvent;
use Comment\Events\CommentDefinitionEvent;
use Comment\Events\CommentDeleteEvent;
use Comment\Events\CommentEvents;
use Comment\Exception\InvalidDefinitionException;
use Comment\Form\AddCommentForm;
use Comment\Form\CommentAbuseForm;
use Comment\Model\CommentQuery;
use Exception;
use ReCaptcha\Event\ReCaptchaCheckEvent;
use ReCaptcha\Event\ReCaptchaEvents;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\HttpFoundation\RequestStack;
use Thelia\Controller\Front\BaseFrontController;
use Thelia\Core\Security\SecurityContext;
use Thelia\Core\Translation\Translator;
use Symfony\Component\Routing\Annotation\Route;
use Thelia\Model\ModuleQuery;

/**
 * @Route("/comment", name="comment")
 * Class CommentController
 * @package Comment\Controller\Admin
 * @author Michaël Espeche <michael.espeche@gmail.com>
 * @author Julien Chanséaume <jchanseaume@openstudio.fr>
 */
class CommentController extends BaseFrontController
{
    const DEFAULT_VISIBLE = 0;

    protected bool $useFallbackTemplate = true;

    /**
     * @Route("/get", name="_get", methods="GET")
     */
    public function getAction(RequestStack $requestStack, SecurityContext $securityContext, EventDispatcherInterface $dispatcher)
    {
        // only ajax
        $this->checkXmlHttpRequest();

        $definition = null;
        $request = $requestStack->getCurrentRequest();

        try {
            $definition = $this->getDefinition(
                $request->get('ref', null),
                $request->get('ref_id', null),
                $securityContext,
                $dispatcher
            );
        } catch (InvalidDefinitionException $ex) {
            if ($ex->isSilent()) {
                // Comment not authorized on this resource
                $this->accessDenied();
            }
        }

        return $this->render(
            "ajax-comments",
            [
                'ref' => $request->get('ref'),
                'ref_id' => $request->get('ref_id'),
                'start' => $request->get('start', 0),
                'count' => $request->get('count', 10),
            ]
        );
    }

    /**
     * @Route("/abuse", name="_abuse", methods="POST")
     */
    public function abuseAction(EventDispatcherInterface $dispatcher)
    {
        // only ajax
        $this->checkXmlHttpRequest();

        $abuseForm = $this->createForm(CommentAbuseForm::getName());

        $messageData = [
            "success" => false
        ];

        try {
            $form = $this->validateForm($abuseForm);

            $comment_id = $form->get("id")->getData();

            $event = new CommentAbuseEvent();
            $event->setId($comment_id);

            $dispatcher->dispatch($event, CommentEvents::COMMENT_ABUSE);

            $messageData["success"] = true;
            $messageData["message"] = Translator::getInstance()->trans(
                "Your request has been registered. Thank you.",
                [],
                Comment::MESSAGE_DOMAIN
            );
        } catch (\Exception $ex) {
            // all errors
            $messageData["message"] = Translator::getInstance()->trans(
                "Your request could not be validated. Try it later",
                [],
                Comment::MESSAGE_DOMAIN
            );
        }

        return $this->jsonResponse(json_encode($messageData));
    }


    /**
     * @Route("/add", name="_add", methods="POST")
     */
    public function createAction(RequestStack $requestStack, EventDispatcherInterface $dispatcher, SecurityContext $securityContext)
    {
        // only ajax
        $this->checkXmlHttpRequest();

        $responseData = [];
        /** @var CommentDefinitionEvent $definition */
        $definition = null;
        $request = $requestStack->getCurrentRequest();
        try {
            $params = $request->get('admin_add_comment');
            $definition = $this->getDefinition(
                $params['ref'],
                $params['ref_id'],
                $securityContext,
                $dispatcher
            );
        } catch (InvalidDefinitionException $ex) {
            if ($ex->isSilent()) {
                // Comment not authorized on this resource
                $this->accessDenied();
            } else {
                // The customer does not have minimum requirement to post comment
                $responseData = [
                    "success" => false,
                    "messages" => [$ex->getMessage()]
                ];
                return $this->jsonResponse(json_encode($responseData));
            }
        }

        $customer = $definition->getCustomer();

        $validationGroups = [
            'Default'
        ];

        if (null === $customer) {
            $validationGroups[] = 'anonymous';
        }
        if (!$definition->hasRating()) {
            $validationGroups[] = 'rating';
        }

        $commentForm = $this->createForm(
            AddCommentForm::getName(),
            FormType::class,
            [],
            ['validation_groups' => $validationGroups]
        );

        try {
            if (null !== ModuleQuery::create()->filterByCode("ReCaptcha")->filterByActivate(1)->findOne()) {
                $checkCaptchaEvent = new ReCaptchaCheckEvent();
                $dispatcher->dispatch($checkCaptchaEvent, ReCaptchaEvents::CHECK_CAPTCHA_EVENT);
                if ($checkCaptchaEvent->isHuman() == false) {
                    throw new \Exception('Invalid captcha');
                }
            }

            $form = $this->validateForm($commentForm);

            $event = new CommentCreateEvent();
            $event->bindForm($form);

            $event->setVerified($definition->isVerified());

            if (null !== $customer) {
                $event->setCustomerId($customer->getId());
            }

            if (!$definition->getConfig()['moderate']) {
                $event->setStatus(\Comment\Model\Comment::ACCEPTED);
            } else {
                $event->setStatus(\Comment\Model\Comment::PENDING);
            }

            $event->setLocale($request->getLocale());

            $dispatcher->dispatch($event, CommentEvents::COMMENT_CREATE);

            if (null !== $event->getComment()) {
                $responseData = [
                    "success" => true,
                    "messages" => [
                        Translator::getInstance()->trans(
                            "Thank you for submitting your comment.",
                            [],
                            Comment::MESSAGE_DOMAIN
                        ),
                    ]
                ];
                if ($definition->getConfig()['moderate']) {
                    $responseData['messages'][] = $this->getTranslator()->trans(
                        "Your comment will be put online once verified.",
                        [],
                        Comment::MESSAGE_DOMAIN
                    );
                }
            } else {
                $responseData = [
                    "success" => false,
                    "messages" => [
                        Translator::getInstance()->trans(
                            "Sorry, an unknown error occurred. Please try again.",
                            [],
                            Comment::MESSAGE_DOMAIN
                        )
                    ]
                ];
            }
        } catch (Exception $ex) {
            $responseData = [
                "success" => false,
                "messages" => [$ex->getMessage()]
            ];
        }

        return $this->jsonResponse(json_encode($responseData));
    }

    protected function getDefinition($ref, $refId, SecurityContext $securityContext, EventDispatcherInterface $dispatcher)
    {
        $eventDefinition = new CommentDefinitionEvent();
        $eventDefinition
            ->setRef($ref)
            ->setRefId($refId)
            ->setCustomer($securityContext->getCustomerUser())
            ->setConfig(Comment::getConfig());

        $dispatcher->dispatch(
            $eventDefinition,
            CommentEvents::COMMENT_GET_DEFINITION
        );

        return $eventDefinition;
    }

    /**
     * @Route("/delete/{commentId}", name="_delete", methods="GET")
     */
    public function deleteAction($commentId, SecurityContext $securityContext, EventDispatcherInterface $dispatcher)
    {
        // only ajax
        $this->checkXmlHttpRequest();

        $messageData = [
            "success" => false
        ];

        try {
            $customer = $securityContext->getCustomerUser();

            // find the comment
            $comment = CommentQuery::create()->findPk($commentId);

            if (null !== $comment) {
                if ($comment->getCustomerId() === $customer->getId()) {
                    $event = new CommentDeleteEvent();
                    $event->setId($commentId);

                    $dispatcher->dispatch($event, CommentEvents::COMMENT_DELETE);

                    if (null !== $event->getComment()) {
                        $messageData["success"] = true;
                        $messageData["message"] = Translator::getInstance()->trans(
                            "Your comment has been deleted.",
                            [],
                            Comment::MESSAGE_DOMAIN
                        );
                    }
                }
            }
        } catch (\Exception $ex) {
            ;
        }

        if (false === $messageData["success"]) {
            $messageData["message"] = $this->getTranslator()->trans(
                "Comment could not be removed. Please try later.",
                [],
                Comment::MESSAGE_DOMAIN
            );
        }

        return $this->jsonResponse(json_encode($messageData));
    }
}
