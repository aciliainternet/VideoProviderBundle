# README
## Installation
Require the bundle in your composer.json file:

```
$ composer require aciliainternet/video-provider-bundle --no-update
```

Register the bundle:

```php
// app/AppKernel.php

public function registerBundles()
{
    return array(
        new Acilia\Bundle\VideoProviderBundle\AciliaVideoProviderBundle(),
        // ...
    );
}
```

Install the bundle:

```
$ composer update aciliainternet/video-provider-bundle
```

## Configuration
Add the following parameters to our parameters.yml:

```yaml
# parameters.yml

video_provider.user: account@provider.com
video_provider.password: *****
```

Config our video provider in services.yml:

```yaml
# services.yml

my_video_provider:
    class: Our\Bundle\Path\VideoProvider\OurProviderApi
```

Implements video provider bundle interface to our provider API:

```php
// Our/Bundle/Path/VideoProvider/OurProviderApi.php

namespace Our\Bundle\Path\VideoProvider;

use Acilia\Bundle\VideoProviderBundle\Library\Interfaces\ProviderInterface;

class OurProviderApi implements ProviderInterface
```

Create the following interface methods in our provider API:

```php
// Our/Bundle/Path/VideoProvider/OurProviderApi.php

class OurProviderApi implements ProviderInterface
{
    public static function getInstance();

    public function setCredentials($credentials);

    public function authenticate();

    public function getVideosFromFeed($feedPublicId);

    public function getVideoInfo($videoId);

    // ...
}
```

## Usage

```php
// Get the service
$videoProvider = $this->get('acilia.video_provider');

// Call methods not covered by the interface
$videoProvider->call('setAccount', ['FIC Fox Play PT LF']);

// Get a video by ID
$video = $videoProvider->getVideoInfo('22070341236');

// Get videos from feed
$videos = $videoProvider->getVideosFromFeed('40xNZWrzTq0v');
```
