<?php
namespace Optimus\Magento1\Codeception\Rewrites;

class Mage_Core_Model_Cookie extends \Mage_Core_Model_Cookie
{
    protected $cookies = [];

    /**
     * Set cookie
     *
     * @param string $name The cookie name
     * @param string $value The cookie value
     * @param int $period Lifetime period
     * @param string $path
     * @param string $domain
     * @param int|bool $secure
     * @param bool $httponly
     * @param string $sameSite
     * @return Mage_Core_Model_Cookie
     */
    public function set($name, $value, $period = null, $path = null, $domain = null, $secure = null, $httponly = null, $sameSite = null)
    {
        /**
         * Check headers sent
         */
        if (!$this->_getResponse()->canSendHeaders(false)) {
            return $this;
        }

        if ($period === true) {
            $period = 3600 * 24 * 365;
        } elseif (is_null($period)) {
            $period = $this->getLifetime();
        }

        if ($period == 0) {
            $expire = 0;
        }
        else {
            $expire = time() + $period;
        }
        if (is_null($path)) {
            $path = $this->getPath();
        }
        if (is_null($domain)) {
            $domain = $this->getDomain();
        }
        if (is_null($secure)) {
            $secure = $this->isSecure();
        }
        if (is_null($httponly)) {
            $httponly = $this->getHttponly();
        }
        if (is_null($sameSite)) {
            $sameSite = $this->getSameSite();
        }

        if ($sameSite === 'None') {
            // Enforce specification SameSite None requires secure
            $secure = true;
        }

        $this->cookies[] = array(
            'name'     => $name,
            'value'    => $value,
            'expire'   => $expire,
            'path'     => $path,
            'domain'   => $domain,
            'secure'   => $secure,
            'httponly' => $httponly,
            'samesite' => $sameSite
        );

        return $this;
    }

    /**
     * Delete cookie
     *
     * @param string $name
     * @param string $path
     * @param string $domain
     * @param int|bool $secure
     * @param int|bool $httponly
     * @param string $sameSite
     * @return Mage_Core_Model_Cookie
     */
    public function delete($name, $path = null, $domain = null, $secure = null, $httponly = null, $sameSite = null)
    {
        /**
         * Check headers sent
         */
        if (!$this->_getResponse()->canSendHeaders(false)) {
            return $this;
        }

        if (is_null($path)) {
            $path = $this->getPath();
        }
        if (is_null($domain)) {
            $domain = $this->getDomain();
        }

        $newCookies = [];
        foreach ($this->cookies as $cookie) {
            if ($cookie['name'] === $name &&
                $cookie['path'] === $path &&
                $cookie['domain'] === $domain) {
                continue;
            }

            $newCookies[] = $cookie;
        }

        $this->cookies = $newCookies;

        return $this;
    }

    /**
     * @return array
     */
    public function getCollectedValues()
    {
        return $this->cookies;
    }
}