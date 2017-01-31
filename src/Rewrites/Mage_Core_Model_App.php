<?php

namespace Optimus\Magento1\Codeception\Rewrites;

class Mage_Core_Model_App extends \Mage_Core_Model_App
{
    /**
     * Retrieve response object
     *
     * @return \Zend_Controller_Response_Http
     */
    public function getResponse()
    {
        if (empty($this->_response)) {
            $this->_response = new \Optimus\Magento1\Codeception\Rewrites\Mage_Core_Controller_Response_Http();
            $this->_response->headersSentThrowsException = \Mage::$headersSentThrowsException;
            $this->_response->setHeader("Content-Type", "text/html; charset=UTF-8");
        }
        return $this->_response;
    }
}