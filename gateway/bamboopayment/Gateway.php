<?php

namespace Omnipay\BambooPayment;

use Omnipay\Common\AbstractGateway;
use Omnipay\Common\ItemBag;

class Gateway extends AbstractGateway
{
    public function getName()
    {
        return 'BambooPayment';
    }

    public function getDefaultParameters()
    {
        
        return array(
            'public_account_key' => '',
            'private_account_key' => '',
            'testMode' => true,
            'access_token' => '',
            'unique_id' => '',
        );
    }

    public function getPublicAccountKey()
    {
        return $this->getParameter('public_account_key');
    }

    public function setPublicAccountKey($value)
    {
        return $this->setParameter('public_account_key', $value);
    }

    public function getPrivateAccountKey()
    {
        return $this->getParameter('private_account_key');
    }

    public function setPrivateAccountKey($value)
    {
        return $this->setParameter('private_account_key', $value);
    }

    public function getUniqueId()
    {
        return $this->getParameter('unique_id');
    }

    public function setUniqueId($value)
    {
        return $this->setParameter('unique_id', $value);
    }

    public function getTestMode()
    {
        return $this->getParameter('testMode');
    }

    public function setTestMode($value)
    {
        return $this->setParameter('testMode', $value);
    }

    public function purchase(array $parameters = array())
    {
        return $this->createRequest('\Omnipay\BambooPayment\Requests\PurchaseRequest', $parameters);
    }

    /**
     * @param  array  $parameters
     * @return \Omnipay\BambooPayment\Message\CompletePurchaseRequest
     */
    public function completePurchase(array $parameters = array())
    {
        return $this->createRequest('\Omnipay\BambooPayment\Requests\CompletePurchaseRequest', $parameters);
    }

}

?>