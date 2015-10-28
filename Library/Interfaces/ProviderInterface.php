<?php

namespace Acilia\Bundle\VideoProviderBundle\Library\Interfaces;

interface ProviderInterface
{
    public function initialize($args);

    public function getVideosFromFeed($feedId);

    public function getVideoInfo($videoId);
}
