<?php
namespace Acilia\Bundle\VideoProviderBundle\Library\Processor\ThePlatform;

use Acilia\Bundle\VideoProviderBundle\Library\Interfaces\ProcessorInterface;
use DateTime;

class DefaultProcessor implements ProcessorInterface
{
    public function processVideoInfo(array $videoInfo)
    {
        $streamReference = null;
        $duration = null;
        $result = false;
        $_lastWidth = 0;
        $videoThumbnail = null;

        // get basic stream information
        foreach ($videoInfo['media$content'] as $content) {
            if ($content['plfile$contentType'] == 'video') {
                if ($_lastWidth < $content['plfile$width']) {
                    foreach ($content['plfile$releases'] as $release) {
                        $streamReference = $release['plrelease$pid'];
                        $_lastWidth = $content['plfile$width'];
                        $duration = round($content['plfile$duration']);
                        $result = true;
                    }
                }
            }
        }

        // get video id
        $videoId = array_reverse(explode('/', $videoInfo['id']))[0];

        // get image thumbnail url
        if (isset($videoInfo['plmedia$defaultThumbnailUrl'])) {
            $videoThumbnail = $videoInfo['plmedia$defaultThumbnailUrl'];
        } elseif (isset($videoInfo['media$thumbnails'])) {
            foreach ($videoInfo['media$thumbnails'] as $content) {
                if (isset($content['plfile$contentType']) && $content['plfile$contentType'] == 'image') {
                    if (isset($content['plfile$streamingUrl'])) {
                        $videoThumbnail = $content['plfile$streamingUrl'];
                        break;
                    }
                }
            }
        }

        $expired = null;
        if (isset($videoInfo['media$expirationDate'])) {
            $expired = $videoInfo['media$expirationDate'] / 1000;
            $expired = new DateTime(date('Y-m-d H:i:s', $expired));
        }

        // get custom data
        $customCountry = isset($videoInfo['fox$countryOrigin']) ? $videoInfo['fox$countryOrigin']: '';
        $customChannel = isset($videoInfo['fox$channel']) ? $videoInfo['fox$channel']: '';
        $customEpisodeNumber = isset($videoInfo['fox$episodeNumber']) ? $videoInfo['fox$episodeNumber']: '';
        $customEpisodeSeasonNumber = isset($videoInfo['fox$seasonNumber']) ? $videoInfo['fox$seasonNumber']: '';
        $customProgramName = isset($videoInfo['fox$programName']) ? $videoInfo['fox$programName']: '';
        $customProgramId = isset($videoInfo['fox$programmeId']) ? $videoInfo['fox$programmeId']: '';

        $customOriginalShowTitle = isset($videoInfo['fox$originalShowTitle']) ? $videoInfo['fox$originalShowTitle']: null;
        $customOriginalEpisodeTitle = isset($videoInfo['fox$originalEpisodeTitle']) ? $videoInfo['fox$originalEpisodeTitle']: null;

        $videoInfoProcessed = [
            'result' => $result,
            'mpx_uri_id' => $videoInfo['id'],
            'guid' => $videoInfo['guid'],
            'video_id' => $videoId,
            'stream_reference' => $streamReference,
            'stream_duration'  => round($duration),
            'thumbnail'  => $videoThumbnail,
            'title'     => $videoInfo['title'],
            'description' => $videoInfo['description'],
            'customCountry' => $customCountry,
            'customChannel' => $customChannel,
            'customEpisodeNumber' => $customEpisodeNumber,
            'customEpisodeSeasonNumber' => $customEpisodeSeasonNumber,
            'customProgramName' => $customProgramName,
            'customProgramId' => $customProgramId,
            'customOriginalShowTitle' => $customOriginalShowTitle,
            'customOriginalEpisodeTitle' => $customOriginalEpisodeTitle,
            'expired' => $expired
        ];

        return $videoInfoProcessed;
    }
}
