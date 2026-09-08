<?php
/*************************************************************************************/
/*                                                                                   */
/*      Thelia	                                                                     */
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
/*	    along with this program. If not, see <http://www.gnu.org/licenses/>.         */
/*                                                                                   */
/*************************************************************************************/
namespace Comment\Form;

use Comment\Comment;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\GreaterThan;
use Symfony\Component\Validator\Constraints\GreaterThanOrEqual;
use Symfony\Component\Validator\Constraints\LessThanOrEqual;
use Symfony\Component\Validator\Constraints\NotBlank;
use Thelia\Form\BaseForm;

class AddCommentForm extends BaseForm
{
    protected function trans($id, array $parameters = [])
    {
        return $this->translator->trans($id, $parameters, Comment::MESSAGE_DOMAIN);
    }

    protected function buildForm()
    {
        $this->formBuilder
            ->add('username', TextType::class, [
                'constraints' => [
                    new NotBlank(['groups' => ['anonymous']])
                ],
                'label' => $this->trans('Username'),
                'label_attr' => [
                    'for' => 'comment_username'
                ]
            ])
            ->add('email', EmailType::class, [
                'constraints' => [
                    new Email(['groups' => ['anonymous']])
                ],
                'required' => false,
                'label' => $this->trans('Email'),
                'label_attr' => [
                    'for' => 'comment_mail'
                ]
            ])
            ->add('title', TextType::class, [
                'required' => false,
                'label' => $this->trans('Title'),
                'label_attr' => [
                    'for' => 'title'
                ]
            ])
            ->add('content', TextType::class, [
                'label' => $this->trans('Content'),
                'required' => false,
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
            ->add('ref_id', TextType::class, [
                'constraints' => [
                    new GreaterThan(['value' => 0])
                ],
                'label' => $this->trans('Ref Id'),
                'label_attr' => [
                    'for' => 'ref_id'
                ]
            ])
            ->add('rating', TextType::class, [
                'constraints' => [
                    new GreaterThanOrEqual(['value' => 0, 'groups' => ['rating']]),
                    new LessThanOrEqual(['value' => 5, 'groups' => ['rating']])
                ],
                'required' => false,
                'label' => $this->trans('Rating'),
                'label_attr' => [
                    'for' => 'rating'
                ]
            ]);
    }

    /*
    protected function getDefinition() {
        $this->form->get('success_url');
    }
*/
    public static function getName(): string
    {
        return 'admin_add_comment';
    }
}
