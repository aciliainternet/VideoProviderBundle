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
Add the following parameters to your parameters.yml:

```yaml
# parameters.yml

video_provider_bundle.user: account@provider.com
video_provider_bundle.password: *****
```

## Configuration (Optional)
Configure video provider:

```yaml
# parameters.yml

video_provider_bundle.provider: ThePlatform
```

Allowed providers:
- ThePlatform (Default)

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
