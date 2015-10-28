<?php

namespace Acilia\Bundle\VideoProviderBundle\Library\Interfaces;

interface ProviderInterface
{
    public static function getInstance();

    public function initialize($args);

    public function configure($args);

    public function getVideoInfo($videoId);

    public function getVideosFromFeed($feedId);

    public function getVideosFromAccount($data);
}
