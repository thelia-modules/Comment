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


namespace Comment\Form\Field;

use Comment\Model\CommentQuery;
use Propel\Runtime\ActiveQuery\ModelCriteria;
use Thelia\Core\Form\Type\Field\AbstractIdType;

/**
 * Class CommentIdType
 * @package Comment\Form\Field
 * @author Julien Chanséaume <jchanseaume@openstudio.fr>
 */
class CommentIdType extends AbstractIdType
{
    /**
     * @return \Propel\Runtime\ActiveQuery\ModelCriteria
     *
     * Get the model query to check
     */
    protected function getQuery(): ModelCriteria
    {
        return new CommentQuery();
    }

    /**
     * Returns the name of this type.
     *
     * @return string The name of this type
     */
    public function getName()
    {
        return "comment_id";
    }
}
