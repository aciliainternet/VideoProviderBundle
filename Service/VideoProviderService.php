<?php
namespace Acilia\Bundle\VideoProviderBundle\Service;

use Acilia\Bundle\VideoProviderBundle\Library\Exceptions\VideoProviderInitializationException;
use Acilia\Bundle\VideoProviderBundle\Library\Exceptions\VideoProviderInterfaceException;
use Acilia\Bundle\VideoProviderBundle\Library\Exceptions\VideoProviderNotFoundProviderException;
use Acilia\Bundle\VideoProviderBundle\Library\Exceptions\VideoProviderConfigurationException;
use Acilia\Bundle\VideoProviderBundle\Library\Interfaces\ProviderInterface;
use Exception;

class VideoProviderService
{
    /**
     * Video provider API class.
     *
     * @var ProviderInterface
     */
    private $provider;

    /**
     * __construct.
     *
     * @param string $provider
     * @param $args
     * @throws VideoProviderInitializationException
     * @throws VideoProviderInterfaceException
     * @throws VideoProviderNotFoundProviderException
     */
    public function __construct($provider, $args)
    {
        $provider = 'Acilia\Bundle\VideoProviderBundle\Library\Providers\\'.$provider.'Provider';

        if (!class_exists($provider)) {
            throw new VideoProviderNotFoundProviderException(sprintf('Provider "%s" not found', $provider));
        }

        try {
            $this->provider = $provider::getInstance();
            $this->provider->initialize($args);
        } catch (Exception $e) {
            throw new VideoProviderInitializationException('Error connecting to video provider', 1, $e);
        }
    }

    /**
     * Call provider configure method.
     *
     * @param array $args
     * @throws VideoProviderConfigurationException
     * @return mixed
     */
    public function configure($args)
    {
        try {
            return $this->provider->configure($args);
        } catch (Exception $e) {
            throw new VideoProviderConfigurationException('Error configuring video provider', 1, $e);
        }
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
