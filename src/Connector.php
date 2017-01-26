<?php

namespace Optimus\Magento1\Codeception;

use Symfony\Component\BrowserKit\Client;
use Symfony\Component\BrowserKit\Response;
use Codeception\Lib\Connector\Shared\PhpSuperGlobalsConverter;
use Codeception\Util\Debug;

class Connector extends Client
{
    use PhpSuperGlobalsConverter;

    /**
     * @var string
     */
    public $homeDir;

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
        define('MAGENTO_ROOT', $homeDir);

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
        require_once MAGENTO_ROOT . '/../vendor/autoload.php';
        require_once __DIR__ . '/Rewrites/Mage.php';
        require_once MAGENTO_ROOT . '/Mage.bootstrap.php';

        $this->_isBootstrapped = true;
    }

    /**
     * @return void
     */
    protected function doRequestOnIndexEntry(\Mage_Core_Controller_Response_Http $response)
    {

        $this->bootstrapMagento();

        if (isset($_SERVER['MAGE_IS_DEVELOPER_MODE'])) {
            \Mage::setIsDeveloperMode(true);
        }

        umask(0);

        /* Store or website code */
        $mageRunCode = isset($_SERVER['MAGE_RUN_CODE']) ? $_SERVER['MAGE_RUN_CODE'] : '';

        /* Run store or run website */
        $mageRunType = isset($_SERVER['MAGE_RUN_TYPE']) ? $_SERVER['MAGE_RUN_TYPE'] : 'store';

        try
        {
            \Mage::run($mageRunCode, $mageRunType, ['options' => $response]);
        } catch (ExitException $e) {

        }
    }

    /**
     * @param \Symfony\Component\BrowserKit\Request $request
     * @return \Symfony\Component\BrowserKit\Response
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

        parse_str($queryString, $params);
        foreach ($params as $k => $v) {
            $_GET[$k] = $v;
        }

        //$this->configParameter

        $this->headers    = [];
        $this->statusCode = null;


        // intercept response here

        ob_start();
        $response = new \Optimus\Magento1\Codeception\Rewrites\Mage_Core_Controller_Response_Http();
        $this->doRequestOnIndexEntry($response);
        ob_get_clean();

        // catch "location" header and display it in debug, otherwise it would be handled
        // by symfony browser-kit and not displayed.
        if (isset($this->headers['location'])) {
            Debug::debug("[Headers] " . json_encode($this->headers));
        }

        $cookies = Mage::getSingleton('core/cookie')->getCollectedValues();

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

        \Mage::reset();
        return new Response(
            $response->getBody(),
            $response->getHttpResponseCode(),
            $response->getCollectedHeaders()
        );
    }
}