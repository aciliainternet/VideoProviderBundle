<?php

namespace Acilia\Bundle\VideoProviderBundle\Service;

use Acilia\Bundle\VideoProviderBundle\Library\Exceptions\VideoProviderConnectionException;
use Acilia\Bundle\VideoProviderBundle\Library\Exceptions\VideoProviderInterfaceException;
use Acilia\Bundle\VideoProviderBundle\Library\Exceptions\VideoProviderMethodNotFoundException;
use Acilia\Bundle\VideoProviderBundle\Library\Exceptions\VideoProviderNotFoundProviderException;

class VideoProviderService
{
    /**
     * Video provider API class.
     *
     * @var ProviderInterface
     */
    private $provider;

    /**
     * Video provider credentials.
     *
     * @var array
     */
    private $credentials;

    /**
     * __construct.
     *
     * @param string $provider
     * @param array  $credentials
     */
    public function __construct($provider, $credentials)
    {
        $provider = 'Acilia\Bundle\VideoProviderBundle\Library\Providers\\'.$provider.'Provider';

        if (!class_exists($provider)) {
            throw new VideoProviderNotFoundProviderException('Provider "'.$provider.'" not found');
        }

        if (!in_array('Acilia\Bundle\VideoProviderBundle\Library\Interfaces\ProviderInterface', class_implements($provider))) {
            throw new VideoProviderInterfaceException('Interface "ProviderInterface" not implemented');
        }

        $this->provider = call_user_func(array($provider, 'getInstance'));

        try {
            $this->provider->setCredentials($credentials);
            $this->provider->authenticate();
        } catch (\Exception $e) {
            throw new VideoProviderConnectionException('Error connecting to video provider', 0, $e);
        }
    }

    /**
     * Call methods not covered by the interface.
     *
     * @param string $method
     * @param array  $arguments [description]
     *
     * @return mixed
     */
    public function call($method, $arguments)
    {
        if (!method_exists($this->provider, $method)) {
            throw new VideoProviderMethodNotFoundException('Method "'.$method.'" not found');
        }

        return call_user_func_array(array($this->provider, $method), $arguments);
    }

    /**
     * Get video information of a given ID.
     *
     * @param string $id
     *
     * @return array
     */
    public function getVideoInfo($id)
    {
        return $this->provider->getVideoInfo($id);
    }

    /**
     * Get videos list of a feed ID given.
     *
     * @param string $feed
     *
     * @return array
     */
    public function getVideosFromFeed($feed)
    {
        return $this->provider->getVideosFromFeed($feed);
    }
}
