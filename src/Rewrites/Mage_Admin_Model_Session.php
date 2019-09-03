<?php

namespace Optimus\Magento1\Codeception\Rewrites;

class Mage_Admin_Model_Session extends \Mage_Admin_Model_Session
{
    public function __construct(array $parameters = [])
    {
        if (!isset($parameters['response']) || !$parameters['response']) {
            $parameters['response'] = new Mage_Core_Controller_Response_Http();
        }

        parent::__construct($parameters);
    }

    public function login($username, $password, $request = null)
    {
        if (empty($username) || empty($password)) {
            return;
        }

        try {
            /** @var $user Mage_Admin_Model_User */
            $user = $this->_factory->getModel('admin/user');
            $user->login($username, $password);
            if ($user->getId()) {
                $this->renewSession();

                if (\Mage::getSingleton('adminhtml/url')->useSecretKey()) {
                    \Mage::getSingleton('adminhtml/url')->renewSecretUrls();
                }
                $this->setIsFirstPageAfterLogin(true);
                $this->setUser($user);
                $this->setAcl(\Mage::getResourceModel('admin/acl')->loadAcl());

                $alternativeUrl = $this->_getRequestUri($request);
                $redirectUrl = $this->_urlPolicy->getRedirectUrl($user, $request, $alternativeUrl);
                if ($redirectUrl) {
                    \Mage::dispatchEvent('admin_session_user_login_success', array('user' => $user));
                    $this->_response->clearHeaders()
                        ->setRedirect($redirectUrl)
                        ->sendHeadersAndExit();
                }
            } else {
                \Mage::throwException(\Mage::helper('adminhtml')->__('Invalid User Name or Password.'));
            }
        } catch (\Mage_Core_Exception $e) {
            $e->setMessage(
                \Mage::helper('adminhtml')->__('You did not sign in correctly or your account is temporarily disabled.')
            );
            $this->_loginFailed($e, $request, $username, $e->getMessage());
        } catch (\Optimus\Magento1\Codeception\Exceptions\ExitException $e) {
            throw $e;
        } catch (\Exception $e) {
            $message = \Mage::helper('adminhtml')->__('An error occurred while logging in.');
            $this->_loginFailed($e, $request, $username, $message);
        }

        return isset($user) ? $user : null;
    }
}
