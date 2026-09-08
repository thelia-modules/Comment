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
use Comment\Form\Field\CommentIdType;
use Thelia\Form\BaseForm;

/**
 * Class CommentAbuseForm
 * @package Comment\Form
 * @author Julien Chanséaume <jchanseaume@openstudio.fr>
 */
class CommentAbuseForm extends BaseForm
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
                'id',
                CommentIdType::class
            );
    }


    /**
     * @return string the name of you form. This name must be unique
     */
    public static function getName(): string
    {
        return 'comment_abuse';
    }
}
