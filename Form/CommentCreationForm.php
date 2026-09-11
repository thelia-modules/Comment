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
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\GreaterThanOrEqual;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\LessThanOrEqual;
use Symfony\Component\Validator\Constraints\NotBlank;
use Thelia\Form\BaseForm;

/**
 * Class CommentCreationForm
 * @package Comment\Form
 * @author Julien Chanséaume <jchanseaume@openstudio.fr>
 */
class CommentCreationForm extends BaseForm
{

    protected function trans($id, array $parameters = [])
    {
        return $this->translator->trans($id, $parameters, Comment::MESSAGE_DOMAIN);
    }

    protected function buildForm()
    {
        $this
            ->formBuilder
            ->add(
                'customer_id',
                IntegerType::class,
                [
                    'required' => false,
                    'label' => $this->trans('Customer'),
                    'label_attr' => [
                        'for' => 'customer_id'
                    ]
                ]
            )
            ->add('username', TextType::class, [
                'required' => false,
                'constraints' => [
                    new Length(['min' => 2])
                ],
                'label' => $this->trans('Username'),
                'label_attr' => [
                    'for' => 'comment_username'
                ]
            ])
            ->add('email', EmailType::class, [
                'required' => false,
                'constraints' => [
                    new Email()
                ],
                'label' => $this->trans('Email'),
                'label_attr' => [
                    'for' => 'comment_mail'
                ]
            ])
            ->add(
                'locale',
                TextType::class,
                [
                    'label' => $this->trans('Locale'),
                    'label_attr' => [
                        'for' => 'locale'
                    ]
                ]
            )
            ->add('title', TextType::class, [
                'constraints' => [
                    new NotBlank()
                ],
                'label' => $this->trans('Title'),
                'label_attr' => [
                    'for' => 'title'
                ]
            ])
            ->add('content', TextType::class, [
                'constraints' => CommentContentConstraints::all(),
                'label' => $this->trans('Content'),
                'label_attr' => [
                    'for' => 'content'
                ]
            ])
            ->add('ref', TextType::class, [
                'constraints' => [
                    new NotBlank()
                ],
                'label' => $this->trans('Ref'),
                'label_attr' => [
                    'for' => 'ref'
                ]
            ])
            ->add('ref_id', IntegerType::class, [
                'constraints' => [
                    new GreaterThanOrEqual(['value' => 0])
                ],
                'label' => $this->trans('Ref Id'),
                'label_attr' => [
                    'for' => 'ref_id'
                ]
            ])
            ->add('rating', IntegerType::class, [
                'required' => false,
                'constraints' => [
                    new GreaterThanOrEqual(['value' => 0]),
                    new LessThanOrEqual(['value' => 5])
                ],
                'label' => $this->trans('Rating'),
                'label_attr' => [
                    'for' => 'rating'
                ]
            ])
            ->add(
                'status',
                IntegerType::class,
                [
                    'label' => $this->trans('Status'),
                    'label_attr' => [
                        'for' => 'status'
                    ]
                ]
            )
            ->add(
                'verified',
                IntegerType::class,
                [
                    'label' => $this->trans('Verified'),
                    'label_attr' => [
                        'for' => 'verified'
                    ]
                ]
            )
            ->add(
                'abuse',
                IntegerType::class,
                [
                    'label' => $this->trans('Abuse'),
                    'label_attr' => [
                        'for' => 'abuse'
                    ]
                ]
            );
    }

    /**
     * @return string the name of you form. This name must be unique
     */
    public static function getName(): string
    {
        return 'admin_comment_creation';
    }
}
