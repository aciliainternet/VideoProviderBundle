<?php

namespace Acilia\Bundle\VideoProviderBundle\Library\Interfaces;

interface ProviderInterface
{
    public function initialize($args);

    public function configure($args);

    public function getVideoInfo($videoId, array $extraData = []);

    public function getVideoInfoProcessed($videoId, array $extraData = []);

    public function getVideosFromFeed($feedId);

    public function getVideosFromAccount($data);

    public function getVideosFromFeedUrl($feedUrl);

    public function getVideoUrl($videoId);

    public function updateVideosProperties($videoData);
}
