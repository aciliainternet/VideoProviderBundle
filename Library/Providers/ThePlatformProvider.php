<?php
namespace Acilia\Bundle\VideoProviderBundle\Library\Providers;

use Acilia\Bundle\VideoProviderBundle\Library\Interfaces\ProviderInterface;
use Acilia\Bundle\VideoProviderBundle\Library\Processor\ThePlatform\DefaultProcessor;
use Exception;

class ThePlatformProvider implements ProviderInterface
{
    const BASE_US = 'theplatform.com';
    const BASE_EU = 'theplatform.eu';

    private static $_instance = null;

    private $_auth = null;
    private $_user;
    private $_password;
    private $_account = null;
    private $_signedIn = false;
    private $_base = 'eu';

    /**
     * @return ThePlatformProvider
     */
    public static function getInstance()
    {
        if (self::$_instance == null) {
            self::$_instance = new self();
        }

        return self::$_instance;
    }

    /**
     * Destruct provider (logout if needed)
     */
    public function __destruct()
    {
        $this->signOut();
    }

    /**
     * Initialize provider.
     *
     * @param array $args
     * @throws Exception
     * @return ThePlatformProvider
     */
    public function initialize($args = array())
    {
        if (empty($args['user']) || empty($args['password'])) {
            throw new Exception('Bad credentials', 1);
        }

        $this->setCredentials($args['user'], $args['password']);
        $this->authenticate();
    }

    /**
     * Configure provider.
     *
     * @param array $args
     * @throws Exception
     */
    public function configure($args)
    {
        // set account for provider
        if (empty($args['account'])) {
            throw new Exception('Bad configuration arguments.', 1);
        }
        $this->setAccount($args['account']);

        // set output processor
        if (! isset($args['processor'])) {
            $args['processor'] = 'default';
        }

        if ($args['processor'] == 'default') {
            $this->_processor = new DefaultProcessor();
        }
    }

    private function _request($url, $post = false, $options = array())
    {
        // Init Curl
        $curl = curl_init();

        // Set Options
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_AUTOREFERER, true);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);

        // Is Post
        if ($post !== false) {
            curl_setopt($curl, CURLOPT_POST, true);
            curl_setopt($curl, CURLOPT_HTTPHEADER, array('Content-Type: application/json; charset=UTF-8'));
            curl_setopt($curl, CURLOPT_POSTFIELDS, $post);
        }

        // Set Extra Options
        foreach ($options as $optionName => $optionValue) {
            curl_setopt($curl, $optionName, $optionValue);
        }

        $response = curl_exec($curl);
        curl_close($curl);

        return $response;
    }

    public function setBase($base)
    {
        $this->_base = $base;
    }

    public function getBaseUrl()
    {
        switch ($this->_base) {
            case 'eu':
                return self::BASE_EU;
                break;

            case 'us':
            default:
                return self::BASE_US;
                break;
        }
    }

    public function setCredentials($user, $password)
    {
        $this->_user = $user;
        $this->_password = $password;
    }

    public function authenticate()
    {
        $url = 'https://identity.auth.theplatform.com/idm/web/Authentication/signIn?schema=1.0&form=json&_duration=8640000&_idleTimeout=360000';
        $options = array(
            CURLOPT_USERPWD => 'mpx/'.$this->_user.':'.$this->_password,
        );

        $response = $this->_request($url, false, $options);
        $json = json_decode($response, true);

        if (!isset($json['signInResponse']) && isset($json['isException'])) {
            throw new Exception($json['description']);
        }

        $this->_auth = $json['signInResponse'];
        $this->_signedIn = true;
    }

    public function signOut()
    {
        if ($this->_signedIn) {
            $url = 'https://identity.auth.theplatform.com/idm/web/Authentication?schema=1.0&form=json';

            $encodedData = '{ "signOut": { "token": "'.$this->_auth['token'].'" } }';

            $response = $this->_request($url, $encodedData);
        }
    }

    public function setAccount($account)
    {
        $this->_account = urlencode($account);
    }

    public function getVideoInfo($videoId)
    {
        $token = $this->_auth['token'];
        $url = 'http://data.media.'.$this->getBaseUrl().'/media/data/Media/'.$videoId.'?'
            .'schema=1.4.0&form=json&'
            .'token='.$token.'&account='.$this->_account;

        $response = $this->_request($url);
        $json = json_decode($response, true);

        return $json;
    }

    public function getVideosFromFeed($feedId)
    {
        $videos = [];

        $token = $this->_auth['token'];
        $url = 'http://data.feed.'.$this->getBaseUrl().'/feed/data/FeedConfig?'
            .'byPid='.$feedId.'&'
            .'schema=1.3.0&form=json&'
            .'token='.$token.'&account='.$this->_account;

        $response = $this->_request($url);
        $json = json_decode($response, true);

        if (($json != false) && ($json['entryCount'] > 0)) {
            foreach ($json['entries'] as $entry) {
                if (isset($entry['plfeed$mediaIds'])) {
                    foreach ($entry['plfeed$mediaIds'] as $videoId) {
                        $videos[] = $this->getVideoInfo($videoId);
                    }
                }
            }
        }

        return $videos;
    }

    public function getVideosFromFeedUrl($feedUrl)
    {
        $videos = false;

        $response = $this->_request($feedUrl);
        $json = json_decode($response, true);

        if (($json != false) && ($json['entryCount'] > 0)) {
            $videos = [
                'entries' => $json['entries'],
                'startIndex' => $json['startIndex'],
                'itemsPerPage' => $json['itemsPerPage'],
                'entryCount' => $json['entryCount'],
            ];
        }

        return $videos;
    }

    public function getVideosFromAccount($data)
    {
        $videos = [];

        $token = $this->_auth['token'];
        $url = 'http://data.media.' . $this->getBaseUrl() . '/media/data/Media?'
            . 'schema=1.8.0&form=json&'
            . 'token='.$token.'&account='.$this->_account;

        // set date filtering params
        if (isset($data['startTime']) && isset($data['endTime'])) {
            $url .= sprintf('&byAdded=%s~%s', $data['startTime'], $data['endTime']);
        }

        // set country filtering params
        if (isset($data['country'])) {
            $url .= sprintf('&byCustomValue={fox:countryOrigin}{%s}', strtolower($data['country']));
        }

        $response = $this->_request($url);
        $json = json_decode($response, true);

        if (($json != false) && ($json['entryCount'] > 0)) {
            foreach ($json['entries'] as $entry) {
                $videoId = array_reverse(explode('/', $entry['id']))[0];
                $videos[] = $this->_processor->processVideoInfo($this->getVideoInfo($videoId));
            }
        }

        return $videos;
    }

    /**
     * Bulk update of a properties of a video
     * @param $videoData is an associative array with the id of the video in the key "id", the rest of entries are couples property_to_update => value
     * @return null
     */
    public function updateVideosProperties($videoData)
    {
        $token = $this->_auth['token'];
        $url = 'http://data.media.' . $this->getBaseUrl() .'/media/data/Media/list?'
            . 'schema=1.8.0&form=json&method=put&'
            . 'token=' . $token . '&account=' . $this->_account;

        $data = array(
            '$xmlns' => array('fox' => 'http://xml.fox.com/fields'),
            'entries' => $videoData
        );
        $encodedData = json_encode($data);

        $response = $this->_request($url, $encodedData);
        return true;
    }
}
