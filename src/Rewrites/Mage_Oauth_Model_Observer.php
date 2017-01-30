<?php

namespace Optimus\Magento1\Codeception\Rewrites;

class Mage_Oauth_Model_Observer extends \Mage_Oauth_Model_Observer
{
    public function afterAdminLogin(\Varien_Event_Observer $observer)
    {
        if (null !== $this->_getOauthToken()) {
            $userType = \Mage_Oauth_Model_Token::USER_TYPE_ADMIN;
            $url = \Mage::helper('oauth')->getAuthorizeUrl($userType);
            $response = \Mage::app()->getResponse();

            $response->setRedirect($url)
                ->sendHeaders()
                ->sendResponse();

            $e = new \Optimus\Magento1\Codeception\Exceptions\ExitException();
            $e->setResponse($response);
            throw $e;
        }
    }
}