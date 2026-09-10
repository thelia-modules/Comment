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
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Validator\Constraints\NotBlank;

/**
 * Class CommentModificationForm
 * @package Comment\Form
 * @author Julien Chanséaume <jchanseaume@openstudio.fr>
 */
class CommentModificationForm extends CommentCreationForm
{
    protected function trans($id, array $parameters = [])
    {
        return $this->translator->trans($id, $parameters, Comment::MESSAGE_DOMAIN);
    }

    protected function buildForm()
    {
        parent::buildForm();

        $this
            ->formBuilder
            ->add(
                'id',
                IntegerType::class,
                [
                    'constraints' => [
                        new NotBlank()
                    ],
                    'label' => $this->trans('Id'),
                    'label_attr' => [
                        'for' => 'id'
                    ]
                ]
            );
    }


    /**
     * @return string the name of you form. This name must be unique
     */
    public static function getName(): string
    {
        return 'admin_comment_modification';
    }
}
