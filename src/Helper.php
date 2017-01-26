<?php

namespace Optimus\Magento1\Codecepttion;

use Codeception\TestCase;
use Codeception\Lib\Framework;
use Codeception\Configuration;

class MagentoHelper extends Framework
{
    protected $requiredFields = ['config'];

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
        $this->client->configParameter = Configuration::projectDir() . $this->config['config'];
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