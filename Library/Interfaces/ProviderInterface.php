<?php

namespace Acilia\Bundle\VideoProviderBundle\Library\Interfaces;

interface ProviderInterface
{
    public static function getInstance();

    public function setCredentials($credentials);

    public function authenticate();

    public function getVideosFromFeed($feedPublicId);

    public function getVideoInfo($videoId);
}
