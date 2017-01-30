<?php

namespace Optimus\Magento1\Codeception\OAuth\Signature;

class PlainTextSignature extends Signature implements SignatureInterface
{
    /**
     * {@inheritDoc}
     */
    public function method()
    {
        return 'PLAINTEXT';
    }

    /**
     * {@inheritDoc}
     */
    public function sign($uri, array $parameters = array(), $method = 'POST')
    {
        return $this->key();
    }
}
