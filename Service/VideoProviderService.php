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
            $this->provider = new $provider();
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
     * Call provider signout method.
     *
     * @throws VideoProviderConfigurationException
     */
    public function signout()
    {
        try {
            $this->provider->signout();
        } catch (Exception $e) {
            throw new VideoProviderConfigurationException('Error signing out video provider', 1, $e);
        }
    }

    /**
     * Get video information of a given ID.
     *
     * @param string $id
     * @param array $extraData Extra Data for the provider
     *
     * @return array
     */
    public function getVideoInfo($id, array $extraData = [])
    {
        return $this->provider->getVideoInfo($id, $extraData);
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

    /**
     * Get videos list of a feed Url given.
     *
     * @param string $feedUrl
     *
     * @return array
     */
    public function getVideosFromFeedUrl($feedUrl)
    {
        return $this->provider->getVideosFromFeedUrl($feedUrl);
    }

    /**
     * Get video list from the account
     *
     * @param array $data filtering for videos
     *
     * @return array
     */
    public function getVideosFromAccount($data)
    {
        return $this->provider->getVideosFromAccount($data);
    }

    /**
     * Returns the base url of the videos in the provider
     * @return string
     */
    public function getVideoUrl($videoId)
    {
        return $this->provider->getVideoUrl($videoId);
    }

    /**
     * Bulk update of a properties of a video
     * @return boolean
     */

    public function updateVideosProperties($videoData)
    {
        return $this->provider->updateVideosProperties($videoData);
    }
}
