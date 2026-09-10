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


namespace Comment\Events;

/**
 * Class CommentDefinitionEvent
 * @package Comment\Events
 * @author Julien Chanséaume <jchanseaume@openstudio.fr>
 */
class CommentDefinitionEvent extends CommentEvent
{
    /** @var string */
    protected $ref;

    /** @var int */
    protected $ref_id;

    /** @var array */
    protected $config = [];

    /** @var \Thelia\Model\Customer|null */
    protected $customer = null;

    /** @var bool */
    protected $verified = false;

    /** @var bool */
    protected $rating = false;

    /** @var bool */
    protected $valid = false;

    public function __construct()
    {
    }

    /**
     * @return string
     */
    public function getRef()
    {
        return $this->ref;
    }

    /**
     * @param string $ref
     */
    public function setRef($ref)
    {
        $this->ref = $ref;
        return $this;
    }

    /**
     * @return int
     */
    public function getRefId()
    {
        return $this->ref_id;
    }

    /**
     * @param int $ref_id
     */
    public function setRefId($ref_id)
    {
        $this->ref_id = $ref_id;
        return $this;
    }

    /**
     * @return array
     */
    public function getConfig()
    {
        return $this->config;
    }

    /**
     * @param array $config
     */
    public function setConfig($config)
    {
        $this->config = $config;
        return $this;
    }

    /**
     * @return \Thelia\Model\Customer|null
     */
    public function getCustomer()
    {
        return $this->customer;
    }

    /**
     * @param \Thelia\Model\Customer|null $customer
     */
    public function setCustomer($customer)
    {
        $this->customer = $customer;
        return $this;
    }

    /**
     * @return boolean
     */
    public function isVerified()
    {
        return $this->verified;
    }

    /**
     * @param boolean $verified
     */
    public function setVerified($verified)
    {
        $this->verified = $verified;
        return $this;
    }

    /**
     * @return boolean
     */
    public function hasRating()
    {
        return $this->rating;
    }

    /**
     * @param boolean $rating
     */
    public function setRating($rating)
    {
        $this->rating = $rating;
        return $this;
    }

    /**
     * @return boolean
     */
    public function isValid()
    {
        return $this->valid;
    }

    /**
     * @param boolean $valid
     */
    public function setValid($valid)
    {
        $this->valid = $valid;
        return $this;
    }
}
