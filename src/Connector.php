<?php

namespace Optimus\Magento1\Codecepttion;

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
        include($this->homeDir . DIRECTORY_SEPARATOR . 'index.php');
        $content = ob_get_clean();

        // catch "location" header and display it in debug, otherwise it would be handled
        // by symfony browser-kit and not displayed.
        if (isset($this->headers['location'])) {
            Debug::debug("[Headers] " . json_encode($this->headers));
        }

//        $cookies = $response->headers->getCookies();
//        foreach ($cookies as $cookie) {
//            /** @var Cookie $cookie */
//
//            $this->getCookieJar()->set(
//                new \Symfony\Component\BrowserKit\Cookie(
//                    $cookie->getName(),
//                    $cookie->getValue(),
//                    $cookie->getExpiresTime(),
//                    $cookie->getPath(),
//                    $cookie->getDomain(),
//                    $cookie->isSecure(),
//                    $cookie->isHttpOnly()
//                )
//            );
//        }


        return new Response(
            $content, 200, [] /*
            $response->getContent(),
            $response->getStatusCode(),
            $response->headers->all() */
        );
    }
}