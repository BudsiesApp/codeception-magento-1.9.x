<?php

namespace Optimus\Magento1\Codeception;

use Codeception\TestCase;
use Codeception\Lib\Framework;
use Codeception\Configuration;

class Helper extends Framework
{
    protected $requiredFields = ['homeDir', 'baseUrl'];

    public function _initialize()
    {
        //.........
    }

    public function _before(TestCase $test)
    {
        $this->client = new Connector();
        if (isset($this->config['baseUrl'])) {
            $baseUrl = trim($this->config['baseUrl']);

            if (preg_match('~^https?~', $baseUrl)) {
                throw new \InvalidArgumentException(
                    "Please, define baseUrl parameter without protocol part: localhost or localhost:8000"
                );
            }

            $this->headers['HOST'] = $baseUrl;
        }

        $this->client->homeDir = Configuration::projectDir() . DIRECTORY_SEPARATOR . $this->config['homeDir'];
    }

    public function _after(TestCase $test)
    {
        $_SESSION = [];
        $_FILES = [];
        $_GET = [];
        $_POST = [];
        $_COOKIE = [];
        $_REQUEST = [];
        parent::_after($test);
    }
}