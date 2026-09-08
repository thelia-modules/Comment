<?php
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


namespace Comment\Form;

use Comment\Comment;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Validator\Constraints\NotBlank;
use Thelia\Form\BaseForm;

/**
 * Class ConfigurationForm
 * @package Comment\Form
 * @author Julien Chanséaume <jchanseaume@openstudio.fr>
 */
class ConfigurationForm extends BaseForm
{
    protected function trans($id, array $parameters = [])
    {
        return $this->translator->trans($id, $parameters, Comment::MESSAGE_DOMAIN);
    }

    protected function buildForm()
    {
        $form = $this->formBuilder;

        $config = Comment::getConfig();

        $form
            ->add(
                "activated",
                CheckboxType::class,
                [
                    'data' => $config['activated'],
                    'label' => $this->trans("Activated"),
                    'label_attr' => [
                        'for' => "activated",
                        'help' => $this->trans(
                            "Global activation of comments. You can control activation by product, content."
                        )
                    ],
                ]
            )
            ->add(
                "moderate",
                CheckboxType::class,
                [
                    'data' => $config['moderate'],
                    'label' => $this->trans("Moderate"),
                    'label_attr' => [
                        'for' => "moderate",
                        'help' => $this->trans("Comments are not validated automatically.")
                    ],
                ]
            )
            ->add(
                "ref_allowed",
                TextType::class,
                [
                    'constraints' => [
                        new NotBlank()
                    ],
                    'data' => implode(',', $config['ref_allowed']),
                    'label' => $this->trans("Allowed references"),
                    'label_attr' => [
                        'for' => "back_office_path",
                        'help' => $this->trans("which elements could use comments")
                    ],
                ]
            )
            ->add(
                "only_customer",
                CheckboxType::class,
                [
                    'data' => $config['only_customer'],
                    'label' => $this->trans("Only customer"),
                    'label_attr' => [
                        'for' => "only_customer",
                        'help' => $this->trans("Only registered customers can post comments.")
                    ],
                ]
            )
            ->add(
                "only_verified",
                CheckboxType::class,
                [
                    'data' => $config['only_verified'],
                    'label' => $this->trans("Only verified"),
                    'label_attr' => [
                        'for' => "only_verified",
                        'help' => $this->trans(
                            "For product comments. Only customers that bought the product can post comments."
                        )
                    ],
                ]
            )
            ->add(
                "request_customer_ttl",
                NumberType::class,
                [
                    'data' => $config['request_customer_ttl'],
                    'label' => $this->trans("Request customer"),
                    'label_attr' => [
                        'for' => "request_customer_ttl",
                        'help' => $this->trans(
                            "Send an email to request customer comments, x days after a paid order (0 = no request sent)."
                        )
                    ],
                ]
            )
            ->add(
                "notify_admin_new_comment",
                CheckboxType::class,
                [
                    'data' => $config['notify_admin_new_comment'],
                    'label' => $this->trans("Notify store managers on new comment"),
                    'label_attr' => [
                        'for' => "notify_admin_new_comment",
                        'help' => $this->trans(
                            "Send an email to the store managers when a new comment is posted."
                        )
                    ],
                ]
            );
    }

    /**
     * @return string the name of you form. This name must be unique
     */
    public static function getName(): string
    {
        return "comment-configuration-form";
    }
}
