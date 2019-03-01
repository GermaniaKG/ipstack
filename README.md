# Germania KG · ipstack client

**PHP client for the [ipstack API](https://ipstack.com/) with PSR-6 cache support and Slim3 middleware**

[![Packagist](https://img.shields.io/packagist/v/germania-kg/ipstack.svg?style=flat)](https://packagist.org/packages/germania-kg/ipstack)
[![PHP version](https://img.shields.io/packagist/php-v/germania-kg/ipstack.svg)](https://packagist.org/packages/germania-kg/ipstack)
[![Build Status](https://img.shields.io/travis/GermaniaKG/ipstack.svg?label=Travis%20CI)](https://travis-ci.org/GermaniaKG/ipstack)
[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/GermaniaKG/ipstack/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/GermaniaKG/ipstack/?branch=master)
[![Code Coverage](https://scrutinizer-ci.com/g/GermaniaKG/ipstack/badges/coverage.png?b=master)](https://scrutinizer-ci.com/g/GermaniaKG/ipstack/?branch=master)
[![Build Status](https://scrutinizer-ci.com/g/GermaniaKG/ipstack/badges/build.png?b=master)](https://scrutinizer-ci.com/g/GermaniaKG/ipstack/build-status/master)

## Installation

```bash
$ composer require germania-kg/ipstack
```



## Using ipstack 

```php
<?php
use Germania\IpstackClient\IpstackClient;

// Setup the Client
$endpoint  = "http://api.ipstack.com/";
$api_key   = "your_api_key";
$ipstack   = new IpstackClient( $endpoint, $api_key);

// Ask ipstack
$client_ip = "8.8.8.8";
$response  = $ipstack->get( $client_ip );
```



### Response example

The *IpstackClient* internally works with array and thus asks *ipstack* to return JSON. Here is a shortened example; For a full example see **ipstack's [documentation](https://ipstack.com/documentation#standard)** on Standard IP lookups: 

```
Array ()
	[ip] => 8.8.8.8
  [type] => ipv4
  [continent_code] => EU
  [continent_name] => Europe
  [country_code] => DE
  [country_name] => Germany
  [region_code] => SH
  [region_name] => Schleswig-Holstein
  [latitude] => 54.3667
  [longitude] => 10.2
  ...
)
```



### Customizing the response

You can customize the ipstack response by adding certain fields to the underlying request, as explained in the ipstack docs on [“Specify Response Fields”](https://ipstack.com/documentation#fields). Just pass an array with query fields which will be added to the GET request:

```php
$response = $ipstack->get( "8.8.8.8", array(
  'language' => "de",
	'fields'   => "ip,country_code,country_name,latitude,longitude,region_name"
));
```



## Caching the ipstack response

If you are using a ipstack [*free plan*](https://ipstack.com/plan) limited to with 10.000 requests a month, you may want to save requests by saving the lookup results to a PSR-6 Cache. The **IpstackClientPsr6CacheDecorator** implements the **IpstackClientInterface** as well and thus can transparently be used in place of the *IpstackClient*.

Its constructor accepts your **IpstackClient** instance and a **PSR-6 CacheItemPool** instance. This example uses the cache implementation from [Stash](http://www.stashphp.com/):

```php
<?php
use Germania\IpstackClient\IpstackClientPsr6CacheDecorator;
use Germania\IpstackClient\IpstackClient;
use Stash\Pool as CacheItemPool;

// Setup your client as shown above
$ipstack = new IpstackClient( $endpoint, $api_key);

// Example cache 
$cache   = new \Stash\Pool(
  new \Stash\Driver\Sqlite( array('path' => "/tmp" ))
);

// Setup the decorator
$caching_ipstack = new IpstackClientPsr6CacheDecorator($ipstack, $cache);

// Use as usual
$response = $caching_ipstack->get( "8.8.8.8" );
```





## Slim Middleware

The **IpstackMiddleware** uses the *IpstackClient* and injects a **ipstack** attribute to the *Request* object that carries the *IpstackClient's* response. 

```php
<?php
use Germania\IpstackClient\IpstackMiddleware;

// Setup your client as shown above
$ipstack = new IpstackClient( $endpoint, $api_key);

// The middleware
$ipstack_middleware = new IpstackMiddleware( $ipstack );

// Setup Slim app
$app = new \Slim\App;
$app->add( $ipstack_middleware );

```

In your Controller, you then simply grab the ipsatck information from the *Request* object:

```php
$ipstack_attr = $request->getAttribute( "ipstack" );

echo $ipstack_attr['ip'];
echo $ipstack_attr['country_code'];
echo $ipstack_attr['country_name'];
```



### A word on IP addresses

The IP address used for the ipstack request is per default determined from `$_SERVER['REMOTE_ADDR']`. It is recommended to use **Rob Allen** aka **akrabat's [Client IP address middleware](https://github.com/akrabat/ip-address-middleware)** that determines the IP address more safely.

**Pitfall 1:** Akrabats middleware must be used BEFORE the *IpstackMiddleware*. It must be added *second* to the Slim app.

**Pitfall 2:** Akrabats middleware allows customizing the request attribute name for the client IP address. From the docs:

> By default, the name of the attribute is '`ip_address`'. This can be changed by the third constructor parameter.

This means, the IP may be stored in a custom request attribute. The *IpstackMiddleware* therefore must know this attribute name. 

Simply pass the IP address attribute name as second constructor, be it default or custom. Remember, if you leave this parameter out,  `$_SERVER['REMOTE_ADDR']` will be used as fallback.

```php
<?php
use RKA\Middleware\IpAddress as IpAddressMiddleware;
use Germania\IpstackClient\IpstackMiddleware;

// Setup Slim app
$app = new \Slim\App;

// Executed second
$ipstack_middleware = new IpstackMiddleware( $ipstack, "ip_address" );
$app->add( $ipstack_middleware );

// Executed first
$checkProxyHeaders = true; // Note: Never trust the IP address for security processes!
$trustedProxies = ['10.0.0.1', '10.0.0.2']; // example
$akrabats_middleware = new IpAddressMiddleware($checkProxyHeaders, $trustedProxies);
$app->add( $akrabats_middleware );
```



## Exceptions

The *IpstackClient* checks for *Guzzle Exceptions* during request and evaluate *ipstack error responses.* Both will be abstracted to **IpstackRequestException,** or **IpstackResponseException** respectively, both of them  implementing the **IpstackExceptionInterface.**

```php
<?php
use Germania\IpstackClient\IpstackExceptionInterface;
use Germania\IpstackClient\IpstackRequestException;
use Germania\IpstackClient\IpstackResponseException;

try {
  $ipstack = new IpstackClient( $endpoint, $api_key);
  $response = $ipstack->get( $client_ip );
}
catch( IpstackExceptionInterface $e ) {

  // a) IpstackResponseException
  // b) IpstackRequestException 
  echo $e->getMessage();
  echo $e->getCode();  
  
  // to get Guzzle's original exception:
  $original_guzzle_exception = $e->getPrevious();
}
```





## Development

```bash
$ git clone https://github.com/GermaniaKG/ipstack.git
$ cd ipstack
$ composer install
```



## Unit testing

Copy **phpunit.xml.dist** to **phunit.xml** and adapt the ipstack-related globals. Endpoint and API key are self-explaining; the dummy **IP4** and **IP6** are IP addresses to check during test runs. The IP examples used here are [Google's DNS servers](https://developers.google.com/speed/public-dns/).

```xml
<php>
  <var name="IPSTACK_ENDPOINT"   value="http://api.ipstack.com/" />
  <var name="IPSTACK_APIKEY"     value="your_api_key" />
  <var name="IPSTACK_DUMMY_IP4"  value="8.8.4.4" />
  <var name="IPSTACK_DUMMY_IP6"  value="2001:4860:4860::8888" />
</php>
```

**Run phpunit** using vendor binary or composer *test* script:

```bash
$ vendor/bin/phpunit
# or
$ composer test
```

