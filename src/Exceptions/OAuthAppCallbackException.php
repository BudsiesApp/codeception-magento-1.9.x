<?php

namespace Optimus\Magento1\Codeception\Exceptions;


class OAuthAppCallbackException extends ExitException
{

    /**
     * @return string|null
     */
    public function getRedirectValue()
    {
        foreach ($this->getResponse()->getCollectedHeaders() as $name => $value) {
            if ($name === 'Location') {
                return $value;
            }
        }

        return null;
    }


}