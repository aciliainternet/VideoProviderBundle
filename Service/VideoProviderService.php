<?php

namespace Acilia\Bundle\VideoProviderBundle\Service;

use Acilia\Bundle\VideoProviderBundle\Library\Exceptions\VideoProviderConnectionException;
use Acilia\Bundle\VideoProviderBundle\Library\Exceptions\VideoProviderInterfaceException;

class VideoProviderService
{
    /**
     * Video provider API class.
     *
     * @var ProviderInterface
     */
    private $api;

    /**
     * Video provider credentials.
     *
     * @var array
     */
    private $credentials;

    /**
     * __construct.
     *
     * @param ProviderInterface $api
     * @param array             $credentials
     */
    public function __construct($api, $credentials)
    {
        if (!in_array('Acilia\Bundle\VideoProviderBundle\Library\Interfaces\ProviderInterface', class_implements(get_class($api)))) {
            throw new VideoProviderInterfaceException('Interface "ProviderInterface" not implemented');
        }

        try {
            $this->api = $api::getInstance();
            $this->api->setCredentials($credentials);
            $this->api->authenticate();
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
        if (!method_exists($this->api, $method)) {
            throw new VideoProviderMethodNotFoundException('Method "'.$method.'" not found');
        }

        return call_user_func_array(array($this->api, $method), $arguments);
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
        return $this->api->getVideoInfo($id);
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
        return $this->api->getVideosFromFeed($feed);
    }
}
