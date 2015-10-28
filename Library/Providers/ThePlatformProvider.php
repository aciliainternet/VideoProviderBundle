<?php

namespace Acilia\Bundle\VideoProviderBundle\Library\Providers;

use Exception;
use Acilia\Bundle\VideoProviderBundle\Library\Interfaces\ProviderInterface;

class ThePlatformProvider implements ProviderInterface
{
    private static $_instance = null;

    private $_region = null;
    private $_regionTimezone = null;
    private $_auth = null;
    private $_account = null;
    private $_accounts = array();
    private $_downloadUrl = null;
    private $_publishProfile = null;
    private $_signedIn = false;

    /**
     * @return ThePlatform_API
     */
    public static function getInstance()
    {
        if (self::$_instance == null) {
            self::$_instance = new self();
        }

        return self::$_instance;
    }

    /**
     * Initialize provider.
     *
     * @param array $args
     *
     * @throws Exception
     *
     * @return ThePlatformProvider
     */
    public static function initialize($args = array())
    {
        if (empty($args['user']) || empty($args['password'])) {
            throw new Exception('Bad credentials', 1);
        }
        $instance = self::getInstance();
        $instance->setCredentials($args['user'], $args['password']);
        $instance->authenticate();

        return $instance;
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

    public function setRegion($region)
    {
        $this->_region = $region;
    }

    public function setRegionTimezone($regionTimezone)
    {
        $this->_regionTimezone = $regionTimezone;
    }

    public function setDownloadUrl($url)
    {
        $this->_downloadUrl = $url;
    }

    public function setPublishProfile($publishProfile)
    {
        $this->_publishProfile = $publishProfile;
    }

    public function registerAccount($accountId, $account)
    {
        $this->_accounts[$accountId] = $account;
    }

    public function switchAccount($accountId)
    {
        $this->setAccount($this->_accounts[$accountId]);
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
        $url = 'http://data.media.theplatform.eu/media/data/Media/'.$videoId.'?'
            .'schema=1.4.0&form=json&'
            .'token='.$token.'&account='.$this->_account;

        $response = $this->_request($url);
        $json = json_decode($response, true);

        $streamProvider = null;
        $widevineProvider = null;
        $duration = null;
        $mpegdashFormat = null;
        $result = false;
        $_lastWidth = 0;
        $_lastWidthW = 0;
        $guid = $json['guid'];

        // Get duration of fox$duration index [FP-708]
        list($hours, $minutes, $seconds) = preg_split('/:/', $json['fox$duration']);
        $duration = (($hours * 60) * 60) + ($minutes * 60) + $seconds;

        foreach ($json['media$content'] as $content) {
            if ($content['plfile$contentType'] == 'video') {
                if ($mpegdashFormat != 'MPEG-DASH') {
                    $mpegdashFormat = $content['plfile$format'];
                }

                if (in_array($content['plfile$format'], ['ISM', 'MPEG-DASH'])) {
                    if (count($content['plfile$releases']) == 1) {
                        $release = $content['plfile$releases'][0];
                        $streamProvider = $release['plrelease$pid'];
                        $_lastWidth = $content['plfile$width'];
                        $result = true;
                    } else {
                        if ($_lastWidth < $content['plfile$width']) {
                            foreach ($content['plfile$releases'] as $release) {
                                $streamProvider = $release['plrelease$pid'];
                                $_lastWidth = $content['plfile$width'];
                                $result = true;
                            }
                        }
                    }
                } elseif ($content['plfile$format'] == 'Widevine') {
                    if (count($content['plfile$releases']) == 1) {
                        $release = $content['plfile$releases'][0];
                        $widevineProvider = $release['plrelease$pid'];
                    } else {
                        if ($_lastWidthW < $content['plfile$width']) {
                            foreach ($content['plfile$releases'] as $release) {
                                $widevineProvider = $release['plrelease$pid'];
                                $_lastWidthW = $content['plfile$width'];
                            }
                        }
                    }
                }
            }
        }

        $alternativeThumbs = [];
        if (isset($json['media$thumbnails'])) {
            foreach ($json['media$thumbnails'] as $content) {
                if (isset($content['plfile$contentType']) && $content['plfile$contentType'] == 'image') {
                    if (isset($content['plfile$streamingUrl'])) {
                        $alternativeThumbs[] = $content['plfile$streamingUrl'];
                    }
                }
            }
        }

        $streamInfo = [
            'result' => $result,
            'stream_id' => $videoId,
            'stream_guid' => $guid,
            'stream_reference' => $streamProvider,
            'stream_widevine' => $widevineProvider,
            'stream_duration' => round($duration),
            'stream_format' => $mpegdashFormat,
            'alt_thumbs' => $alternativeThumbs,
            'feed_masterId' => $json['fox$masterId'],
        ];

        // not needed for now
        //$streamInfo['region'] = (isset($json['fox$region'])) ? $json['fox$region'] : false;
        //$streamInfo['incPromos'] = (isset($json['fox$incPromos'])) ? $json['fox$incPromos'] : false;
        //$streamInfo['year'] = (isset($json['fox$year'])) ? $json['fox$year'] : false;
        //$streamInfo['noOfAudioTracks'] = (isset($json['fox$noOfAudioTracks'])) ? $json['fox$noOfAudioTracks'] : false;
        //$streamInfo['promoBreaks'] = (isset($json['fox$promoBreaks'])) ? $json['fox$promoBreaks'][0] : false;
        //$streamInfo['vodType'] = (isset($json['fox$vodType'])) ? $json['fox$vodType'] : false;

        // read type, channel and source
        $streamInfo['type'] = (isset($json['fox$type'])) ? strtolower($json['fox$type']) : false;
        $streamInfo['channel'] = (isset($json['fox$channel'])) ? $json['fox$channel'] : false;
        $streamInfo['source'] = (isset($json['fox$source'])) ? $json['fox$source'] : false;

        // read show information
        $streamInfo['showTitle'] = (isset($json['fox$showTitle'])) ? $json['fox$showTitle'] : false;
        $streamInfo['originalShowTitle'] = (isset($json['fox$originalShowTitle'])) ? $json['fox$originalShowTitle'] : false;
        $streamInfo['showSynopsis'] = (isset($json['fox$localShowSynopsis'])) ? $json['fox$localShowSynopsis'] : false;
        $streamInfo['originalShowSynopsis'] = (isset($json['fox$originalShowSynopsis'])) ? $json['fox$originalShowSynopsis'] : false;

        // read episode information
        $streamInfo['episodeTitle'] = (isset($json['fox$episodeTitle'])) ? $json['fox$episodeTitle'] : false;
        $streamInfo['originalEpisodeTitle'] = (isset($json['fox$originalEpisodeTitle'])) ? $json['fox$originalEpisodeTitle'] : false;
        $streamInfo['episodeSeasonNumber'] = (isset($json['fox$seasonNumber'])) ? $json['fox$seasonNumber'] : false;
        $streamInfo['episodeNumber'] = (isset($json['fox$episodeNumber'])) ? $json['fox$episodeNumber'] : false;
        $streamInfo['episodeSynopsis'] = (isset($json['fox$localEpisodeSynopsis'])) ? $json['fox$localEpisodeSynopsis'] : false;
        $streamInfo['originalEpisodeSynopsis'] = (isset($json['fox$originalEpisodeSynopsis'])) ? $json['fox$originalEpisodeSynopsis'] : false;

        // read casting information information
        $streamInfo['cast'] = (isset($json['fox$actors'])) ? implode(',', $json['fox$actors']) : false;
        $streamInfo['director'] = (isset($json['fox$director'])) ? $json['fox$director'] : false;

        // read producer information
        $streamInfo['countryOrigin'] = (isset($json['fox$countryOrigin'])) ? $json['fox$countryOrigin'] : false;
        $streamInfo['releaseYear'] = (isset($json['fox$releaseYear'])) ? $json['fox$releaseYear'] : false;

        $streamInfo['genre'] = (isset($json['fox$genre'])) ? $json['fox$genre'] : false;
        $streamInfo['pinProtected'] = (isset($json['fox$pinProtected'])) ? $json['fox$pinProtected'] : false;
        $streamInfo['awards'] = (isset($json['fox$awards'])) ? implode(',', $json['fox$awards']) : false;

        // read pg rating and product placement
        $pgRating = (isset($json['fox$pgRating'])) ? $json['fox$pgRating'][0] : false;  // get first one
        $pgRating = preg_replace('|/contentcontainsproductplacement|i', '', $pgRating, -1, $productplacement);
        if ($this->_region == 'fi') {
            $pgRatingData = preg_split('|/|', $pgRating);
            $streamInfo['rating'] = array_shift($pgRatingData);
            $streamInfo['ratingText'] = implode('/', $pgRatingData);
            $streamInfo['productPlacement'] = ($productplacement == 1) ? true : false;
        } else {
            $streamInfo['rating'] = $pgRating;
            $streamInfo['ratingText'] = null;
            $streamInfo['productPlacement'] = false;
        }

        // read audio language fields
        $streamInfo['audioLanguage'] = (isset($json['fox$audioLanguage'])) ? implode(',', $json['fox$audioLanguage']) : false;

        // read expiration dates
        $streamInfo['originalAirDate'] = false;
        if (isset($json['fox$originalAirdate'])) {
            $originalAirdate = $json['fox$originalAirDate'] / 1000;

            $dateTimezone = new \DateTime();
            if (!is_null($this->_regionTimezone)) {
                $dateTimezone->setTimezone(new \DateTimeZone($this->_regionTimezone));
            }
            $dateTimezone->setTimestamp($originalAirdate / 1000);
            $streamInfo['originalAirDate'] = $dateTimezone->format('Y-m-d H:i:s');
        }

        $streamInfo['expirationDate'] = false;
        if ($json['media$expirationDate'] > 0) {
            $expires = $json['media$expirationDate'] / 1000;

            $dateTimezone = new \DateTime();
            if (!is_null($this->_regionTimezone)) {
                $dateTimezone->setTimezone(new \DateTimeZone($this->_regionTimezone));
            }
            $dateTimezone->setTimestamp($expires);
            $streamInfo['expirationDate'] = $dateTimezone->format('Y-m-d H:i:s');
        }

        $streamInfo['availableDate'] = false;
        if ($json['media$availableDate'] > 0) {
            $availableDate = $json['media$availableDate'] / 1000;

            $dateTimezone = new \DateTime();
            if (!is_null($this->_regionTimezone)) {
                $dateTimezone->setTimezone(new \DateTimeZone($this->_regionTimezone));
            }
            $dateTimezone->setTimestamp($availableDate);
            $streamInfo['availableDate'] = $dateTimezone->format('Y-m-d H:i:s');
        }

        // read licensed devices
        $streamInfo['webLicensed'] = (isset($json['fox$webLicensed'])) ? $json['fox$webLicensed'] : false;
        $streamInfo['mobileLicensed'] = (isset($json['fox$mobileLicensed'])) ? $json['fox$mobileLicensed'] : false;
        $streamInfo['liveSIMLicensed'] = (isset($json['fox$liveSIMLicensed'])) ? $json['fox$liveSIMLicensed'] : false;
        $streamInfo['gameConsoleLicensed'] = (isset($json['fox$gameConsoleLicensed'])) ? $json['fox$gameConsoleLicensed'] : false;
        $streamInfo['connectedTVLicensed'] = (isset($json['fox$connectedTVLicensed'])) ? $json['fox$connectedTVLicensed'] : false;

        // read default image
        $streamInfo['defaultThumbnailUrl'] = (!empty($json['plmedia$defaultThumbnailUrl'])) ? $json['plmedia$defaultThumbnailUrl'] : false;
        if (!$streamInfo['defaultThumbnailUrl']) {
            if (count($alternativeThumbs) > 0) {
                $streamInfo['defaultThumbnailUrl'] = $alternativeThumbs[0];
            }
        }

        // read tests executed
        $streamInfo['approved'] = (isset($json['plfile$approved'])) ? $json['plfile$approved'] : false;
        $streamInfo['widevineQC'] = (isset($json['fox$WidevineQCPassed'])) ? $json['fox$WidevineQCPassed'] : false;
        $streamInfo['playreadyQC'] = (isset($json['fox$PlayreadyQCPassed'])) ? $json['fox$PlayreadyQCPassed'] : false;
        $streamInfo['thumbnailQC'] = (isset($json['fox$ThumbnailQCPassed'])) ? $json['fox$ThumbnailQCPassed'] : false;

        return $streamInfo;
    }

    public function getVideosFromFeed($feedPublicId)
    {
        $videos = [];

        $token = $this->_auth['token'];
        $url = 'http://data.feed.theplatform.eu/feed/data/FeedConfig?'
             .'byPid='.$feedPublicId.'&'
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

    public function getVideosByDate($startDatetime, $endDatetime, $start = 1)
    {
        $videos = [];

        $token = $this->_auth['token'];
        $url = 'http://data.media.theplatform.eu/media/data/Media?'
            .'schema=1.4.0&form=json&count=true&startIndex='.$start.'&'
            .'token='.$token.'&account='.$this->_account.'&'
            .'byUpdated='.$startDatetime.'~'.$endDatetime;

        $response = $this->_request($url);
        $json = json_decode($response, true);

        if (($json != false) && ($json['entryCount'] > 0)) {
            foreach ($json['entries'] as $entry) {
                $videoId = explode('/', $entry['id']);
                $videoId = array_pop($videoId);

                $videos[] = $this->getVideoInfo($videoId);
            }
        }

        return $videos;
    }
}
