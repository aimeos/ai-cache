<a href="http://aimeos.org/">
    <img src="http://aimeos.org/fileadmin/template/icons/logo.png" alt="Aimeos logo" title="Aimeos" align="right" height="60" />
</a>

Aimeos cache extension
======================
[![Build Status](https://travis-ci.org/aimeos/ai-cache.svg?branch=master)](https://travis-ci.org/aimeos/ai-cache)
[![Coverage Status](https://coveralls.io/repos/aimeos/ai-cache/badge.svg?branch=master)](https://coveralls.io/r/aimeos/ai-cache?branch=master)
[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/aimeos/ai-cache/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/aimeos/ai-cache/?branch=master)
[![HHVM Status](http://hhvm.h4cc.de/badge/aimeos/ai-cache.svg)](http://hhvm.h4cc.de/package/aimeos/ai-cache)

The Aimeos cache extension contains alternative cache implementations to the database caching of the core. They can be used by Aimeos web shops to offload generated HTML web site parts to other services like specialized key/value stores.

## Table of contents

- [Installation](#installation)
- [Configuration](#configuration)
  - [Redis](#redis)
- [License](#license)
- [Links](#links)

## Installation

As every Aimeos extension, the easiest way is to install it via [composer](https://getcomposer.org/). If you don't have composer installed yet, you can execute this string on the command line to download it:
```
php -r "readfile('https://getcomposer.org/installer');" | php -- --filename=composer
```

Add the cache extension name to the "require" section of your ```composer.json``` (or your ```composer.aimeos.json```, depending on what is available) file:
```
"require": [
    "aimeos/ai-cache": "dev-master",
    ...
],
```

Afterwards you only need to execute the composer update command on the command line:
```
composer update
```

If your composer file is named "aimeos.composer.json", you must use this:
```
COMPOSER=composer.aimeos.json composer update
```

These commands will install the Aimeos extension into the extension directory and it will be available immediately.

## Configuration

The ways of adding the required resource configuration depends on the software you are using because all have their own means to do that. Here are some examples:

**Symfony** (in app/config/config.yml):
```
aimeos_shop:
    resource:
        cache:
            <name>:
                ...
```

**TYPO3** (via TypoScript in the setup template):
```
plugin.tx_aimeos.settings.resource.cache {
    <name>: {
        ...
    }
}
```

### Redis

[Redis](http://www.redis.io/) is an in-memory caching server known for its speed and advanced features. It supports not only plain key/value pairs but also lists for values used by Aimeos for tagging cached entries. This allows a fine control of removing outdated HTML parts.

After you've set up a Redis server, you need to tell your Aimeos shop installation how to connect to this server. The cache extension is using the [Predis library](https://github.com/nrk/predis) and supports all configuration options available. The resource configuration consists of the name "redis" (<name> in the introduction to the config section) and the list of configuration key/value pairs, e.g.

**Symfony**:
```
aimeos_shop:
    resource:
        cache:
            redis:
                scheme: tcp
                host: 10.0.0.1
                port: 6379
```

**TYPO3**:
```
plugin.tx_aimeos.settings.resource.cache {
    redis: {
        scheme = tcp
        host = 10.0.0.1
        port = 6379
    }
}
```

Please have a look at the fine [Predis readme](https://github.com/nrk/predis) for all available options.

## License

The Aimeos cache extension is licensed under the terms of the LGPLv3 Open Source license and is available for free.

## Links

* [Web site](http://aimeos.org/)
* [Documentation](http://docs.aimeos.org/)
* [Help](http://help.aimeos.org/)
* [Issue tracker](https://github.com/aimeos/ai-cache/issues)
* [Source code](https://github.com/aimeos/ai-cache)
