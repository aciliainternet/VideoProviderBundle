<?php

namespace Acilia\Bundle\VideoProviderBundle\Library\Interfaces;

interface ProviderInterface
{
    public static function initialize();

    public function getVideosFromFeed($feedPublicId);

    public function getVideoInfo($videoId);
}
