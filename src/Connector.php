<?php

namespace Optimus\Magento1\Codeception;

use Optimus\Magento1\Codeception\Exceptions\OAuthAppCallbackException;
use Symfony\Component\BrowserKit\Client;
use Symfony\Component\BrowserKit\Response;
use Codeception\Lib\Connector\Shared\PhpSuperGlobalsConverter;
use Codeception\Util\Debug;
use Optimus\Magento1\Codeception\Interfaces\CollectedHeadersInterface;

class Connector extends Client
{
    use PhpSuperGlobalsConverter;

    /**
     * @var string
     */
    public $homeDir;

    /**
     * @var string
     */
    public $oauthAppCallback;

    /**
     * @var array
     */
    public $headers;

    /**
     * @var string
     */
    public $statusCode;

    /**
     * @var bool
     */
    public $https;

    /**
     * @var bool
     */
    private $_isBootstrapped = false;

    /**
     * @return void
     * @throws \Exception
     */
    protected function bootstrapMagento()
    {
        if ($this->_isBootstrapped) {
            return;
        }

        $homeDir = realpath($this->homeDir);
        if (!$homeDir) {
            throw new \Exception(
                "Can't resolve real path for the home directory.\n".
                "`homeDir` value is: {$this->homeDir}"
            );
        }

        /**
         * Compilation includes configuration file
         */
        if (!defined('MAGENTO_ROOT')) {
            define('MAGENTO_ROOT', $homeDir);
        }

        $compilerConfig = MAGENTO_ROOT . '/includes/config.php';
        if (file_exists($compilerConfig)) {
            include $compilerConfig;
        }

//        $maintenanceFile = 'maintenance.flag';
//
//        if (file_exists($maintenanceFile)) {
//            include_once dirname(__FILE__) . '/errors/503.php';
//            exit;
//        }

        // require_once MAGENTO_ROOT . '/app/bootstrap.php';
        // the following replaces bootstrap.php including
        require_once MAGENTO_ROOT . '/vendor/autoload.php';
        require_once __DIR__ . '/Rewrites/Mage.php';
        require_once MAGENTO_ROOT . '/app/Mage.bootstrap.php';

        $this->_isBootstrapped = true;
    }

    /**
     * @return mixed
     */
    protected function doRequestOnIndexEntry()
    {

        umask(0);

        /* Store or website code */
        $mageRunCode = isset($_SERVER['MAGE_RUN_CODE']) ? $_SERVER['MAGE_RUN_CODE'] : '';

        /* Run store or run website */
        $mageRunType = isset($_SERVER['MAGE_RUN_TYPE']) ? $_SERVER['MAGE_RUN_TYPE'] : 'store';

        $result = \Mage::run($mageRunCode, $mageRunType);
        if (is_object($result) && $result instanceof CollectedHeadersInterface) {
            return $result;
        }

        return \Mage::app()->getResponse();
    }

    /**
     * @return mixed
     */
    protected function doRequestOnApiEntry()
    {
        \Mage::$headersSentThrowsException = false;
        \Mage::init('admin');
        \Mage::app()->loadAreaPart(\Mage_Core_Model_App_Area::AREA_GLOBAL, \Mage_Core_Model_App_Area::PART_EVENTS);
        \Mage::app()->loadAreaPart(\Mage_Core_Model_App_Area::AREA_ADMINHTML, \Mage_Core_Model_App_Area::PART_EVENTS);

        // query parameter "type" is set by .htaccess rewrite rule
        $apiAlias = \Mage::app()->getRequest()->getParam('type');

        // check request could be processed by API2
        if (in_array($apiAlias, \Mage_Api2_Model_Server::getApiTypes())) {
            // emulate index.php entry point for correct URLs generation in API
            \Mage::register('custom_entry_point', true);
            /** @var $server Mage_Api2_Model_Server */
            $server = \Mage::getSingleton('api2/server');

            $server->run();

            return \Mage::getSingleton('api2/response');

        } else {
            /* @var $server \Mage_Api_Model_Server */
            $server = \Mage::getSingleton('api/server');
            if (!$apiAlias) {
                $adapterCode = 'default';
            } else {
                $adapterCode = $server->getAdapterCodeByAlias($apiAlias);
            }
            // if no adapters found in aliases - find it by default, by code
            if (null === $adapterCode) {
                $adapterCode = $apiAlias;
            }

            $server->initialize($adapterCode);
            // emulate index.php entry point for correct URLs generation in API
            \Mage::register('custom_entry_point', true);
            $server->run();

            return \Mage::app()->getResponse();
        }
    }

    /**
     * @param \Symfony\Component\BrowserKit\Request $request
     * @return \Symfony\Component\BrowserKit\Response
     * @throws OAuthAppCallbackException
     */
    public function doRequest($request)
    {
        $_COOKIE  = $request->getCookies();
        $_SERVER  = $request->getServer();
        $_FILES   = $this->remapFiles($request->getFiles());
        $_REQUEST = $this->remapRequestParameters($request->getParameters());
        $_POST    = $_GET = array();

        if (strtoupper($request->getMethod()) == 'GET') {
            $_GET = $_REQUEST;
        } else {
            $_POST = $_REQUEST;
        }

        $uri = $request->getUri();

        $pathString                = parse_url($uri, PHP_URL_PATH);
        $queryString               = parse_url($uri, PHP_URL_QUERY);
        $_SERVER['REQUEST_URI']    = $queryString === null ? $pathString : $pathString . '?' . $queryString;
        $_SERVER['REQUEST_METHOD'] = strtoupper($request->getMethod());

        if ($this->https) {
            $_SERVER['HTTPS'] = true;
        }

        parse_str($queryString, $params);
        foreach ($params as $k => $v) {
            $_GET[$k] = $v;
        }


        $this->headers    = [];
        $this->statusCode = null;

        ob_start();
        $this->bootstrapMagento();
        \Mage::reset();

        if (isset($_SERVER['MAGE_IS_DEVELOPER_MODE'])) {
            \Mage::setIsDeveloperMode(true);
        }

        if (preg_match('#^/api/([^/]+)/#', $pathString, $match)) {
            $_GET['type']     = $match[1];
            $_REQUEST['type'] = $match[1];
            $_SERVER['SCRIPT_NAME'] = $_SERVER['SCRIPT_FILENAME'] = 'api.php';
            $response = $this->doRequestOnApiEntry();
        } else {
            $_SERVER['SCRIPT_NAME'] = $_SERVER['SCRIPT_FILENAME'] = 'index.php';
            $response = $this->doRequestOnIndexEntry();
        }

        ob_get_clean();

        if (!$response || !is_object($response) || !($response instanceof CollectedHeadersInterface)) {
            throw new \RuntimeException("Invalid response received");
        }

        if ($this->oauthAppCallback && $response) {
            $headers = $response->getCollectedHeaders();
            foreach ($headers as $name => $value) {
                if ($name === 'Location' && strpos($value, $this->oauthAppCallback) === 0) {
                    $e = new OAuthAppCallbackException();
                    $e->setResponse($response);
                    throw $e;
                }
            }
        }


        // catch "location" header and display it in debug, otherwise it would be handled
        // by symfony browser-kit and not displayed.
        if (isset($this->headers['location'])) {
            Debug::debug("[Headers] " . json_encode($this->headers));
        }

        $cookies = \Mage::getSingleton('core/cookie')->getCollectedValues();

        foreach ($cookies as $cookie) {
            /** @var Cookie $cookie */

            $this->getCookieJar()->set(
                new \Symfony\Component\BrowserKit\Cookie(
                    $cookie['name'],
                    $cookie['value'],
                    $cookie['expire'],
                    $cookie['path'],
                    $cookie['domain'],
                    $cookie['secure'],
                    $cookie['httponly']
                )
            );
        }

        return new Response(
            $response->getBody(),
            $response->getHttpResponseCode(),
            $response->getCollectedHeaders()
        );
    }
}