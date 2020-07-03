<p align="center">
  <img alt="logo" src="https://hsto.org/webt/0v/qb/0p/0vqb0pp6ntyyd8mbdkkj0wsllwo.png" width="70" height="70" />
</p>

# [Guzzle][guzzle_link] URLs mock handler

[![Version][badge_packagist_version]][link_packagist]
[![Version][badge_php_version]][link_packagist]
[![Build Status][badge_build_status]][link_build_status]
[![Coverage][badge_coverage]][link_coverage]
[![Downloads count][badge_downloads_count]][link_packagist]
[![License][badge_license]][link_license]

This package for easy mocking URLs _(fixed and regexps-based)_ using [Guzzle 6/Guzzle 7][guzzle_link].

## Install

Require this package with composer using the following command:

```shell
$ composer require tarampampam/guzzle-url-mock "^1.0"
```

> Installed `composer` is required ([how to install composer][getcomposer]).

> You need to fix the major version of package.

## Usage

Create Guzzle client instance with passing handler instance, setup it, and make request:

```php
<?php

use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Tarampampam\GuzzleUrlMock\UrlsMockHandler;

$handler = new UrlsMockHandler;
$client  = new Client([
    'handler' => HandlerStack::create($handler),
]);

$handler->onUriRequested('https://goo.gl', 'get', new Response(
    200, ['foo' => ['bar']], '<h1>All looks fine!</h1>'
));

$handler->onUriRegexpRequested('~https:\/\/goo\.gl\/.*~', 'post', new Response(
    404, [], 'Nothing found'
));

$client->request('get', 'https://goo.gl')->getBody()->getContents(); // '<h1>All looks fine!</h1>'
$client->request('post', 'https://goo.gl/foo', ['http_errors' => false])->getBody()->getContents(); // 'Nothing found'
```

Also you can use next handler methods:

Method name | Description
----------- | -----------
`getRequestsUriHistory()` | Get all requests URIs history
`getLastRequestedUri()` | Get last requested URI
`getLastRequest()` | Get last request instance
`getLastOptions()` | Get last request options

### Testing

For package testing we use `phpunit` framework. Just write into your terminal:

```shell
$ git clone git@github.com:tarampampam/guzzle-url-mock.git ./guzzle-url-mock && cd $_
$ composer install
$ composer test
```

## Changes log

[![Release date][badge_release_date]][link_releases]
[![Commits since latest release][badge_commits_since_release]][link_commits]

Changes log can be [found here][link_changes_log].

## Support

[![Issues][badge_issues]][link_issues]
[![Issues][badge_pulls]][link_pulls]

If you will find any package errors, please, [make an issue][link_create_issue] in current repository.

## License

This is open-sourced software licensed under the [MIT License][link_license].

[badge_packagist_version]:https://img.shields.io/packagist/v/tarampampam/guzzle-url-mock.svg?maxAge=180
[badge_php_version]:https://img.shields.io/packagist/php-v/tarampampam/guzzle-url-mock.svg?longCache=true
[badge_build_status]:https://travis-ci.com/tarampampam/guzzle-url-mock.svg?branch=master
[badge_coverage]:https://img.shields.io/codecov/c/github/tarampampam/guzzle-url-mock/master.svg?maxAge=60
[badge_downloads_count]:https://img.shields.io/packagist/dt/tarampampam/guzzle-url-mock.svg?maxAge=180
[badge_license]:https://img.shields.io/packagist/l/tarampampam/guzzle-url-mock.svg?longCache=true
[badge_release_date]:https://img.shields.io/github/release-date/tarampampam/guzzle-url-mock.svg?style=flat-square&maxAge=180
[badge_commits_since_release]:https://img.shields.io/github/commits-since/tarampampam/guzzle-url-mock/latest.svg?style=flat-square&maxAge=180
[badge_issues]:https://img.shields.io/github/issues/tarampampam/guzzle-url-mock.svg?style=flat-square&maxAge=180
[badge_pulls]:https://img.shields.io/github/issues-pr/tarampampam/guzzle-url-mock.svg?style=flat-square&maxAge=180
[link_releases]:https://github.com/tarampampam/guzzle-url-mock/releases
[link_packagist]:https://packagist.org/packages/tarampampam/guzzle-url-mock
[link_build_status]:https://travis-ci.com/tarampampam/guzzle-url-mock
[link_coverage]:https://codecov.io/gh/tarampampam/guzzle-url-mock/
[link_changes_log]:https://github.com/tarampampam/guzzle-url-mock/blob/master/CHANGELOG.md
[link_issues]:https://github.com/tarampampam/guzzle-url-mock/issues
[link_create_issue]:https://github.com/tarampampam/guzzle-url-mock/issues/new/choose
[link_commits]:https://github.com/tarampampam/guzzle-url-mock/commits
[link_pulls]:https://github.com/tarampampam/guzzle-url-mock/pulls
[link_license]:https://github.com/tarampampam/guzzle-url-mock/blob/master/LICENSE
[getcomposer]:https://getcomposer.org/download/
[guzzle_link]:https://github.com/guzzle/guzzle
