<?php

namespace Optimus\Magento1\Codeception;

use Codeception\TestCase;
use Codeception\Lib\Framework;
use Codeception\Configuration;
use Optimus\Magento1\Codeception\Exceptions\OAuthAppCallbackException;
use Optimus\Magento1\Codeception\OAuth\Client as OAuthClient;

class Helper extends Framework
{
    protected $requiredFields = ['homeDir', 'baseUrl'];

    protected $tokenCredentials;
    protected $oauthClient;

    protected function getInternalDomains()
    {
        $baseUrl = trim($this->config['baseUrl']);
        if (strpos($baseUrl, ':')) {
            $baseUrl = explode(':', $baseUrl)[0];
        }
        return array_merge(parent::getInternalDomains(), [
            '~' . str_replace([':','.'], ['\\:','\\.'], $baseUrl . '~')
        ]);
    }

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
        $this->client->oauthAppCallback = $this->config['oauth_app_callback'];
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

    protected function getOauthClient()
    {
        if ($this->oauthClient) {
            return $this->oauthClient;
        }

        $this->oauthClient = new OAuthClient($this, [
            'identifier'   => $this->config['oauth_app_key'],
            'secret'       => $this->config['oauth_app_secret'],
            'callback_uri' => $this->config['oauth_app_callback'],
            'host'         => 'http://' . $this->config['baseUrl'],
            'admin'        => true
        ]);

        $tempCredentials = $this->oauthClient->getTemporaryCredentials();
        try
        {
            $this->oauthClient->authorize($tempCredentials);
        }
        catch (OAuthAppCallbackException $e) {
            $redirectUrl = $e->getRedirectValue();
            $result = parse_url($redirectUrl);
        }

        $query = \GuzzleHttp\Psr7\parse_query($result['query']);
        $temporaryIdentifier = $query['oauth_token'];
        $oauthVerifier       = $query['oauth_verifier'];

        $this->tokenCredentials = $this->oauthClient->getTokenCredentials(
            $tempCredentials,
            $temporaryIdentifier,
            $oauthVerifier
        );

        return $this->oauthClient;

    }

    public function doApiRequest($method, $url, $params = [])
    {
        $client = $this->getOauthClient();

        $this->headers = $client->getHeaders($this->tokenCredentials, $method, $url);
        $result = $this->client->request($method, $url, $params);

        return $result;
    }
}