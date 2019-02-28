# Germania KG · ipstack client

**PHP client for the [ipstack API](https://ipstack.com/)**

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



## Usage

```php
<?php
use Germania\IpstackClient\IpstackClient;

$endpoint = "http://api.ipstack.com/";
$api_key  = "your_api_key";

$ipstack = new IpstackClient( $endpoint, $api_key);

$client_api = "8.8.8.8";

// array
$response = $ipstack->get( $client_ip );
```



## ipstack response

Whilst *ipstack* returns JSON, the *IpstackClient* converts it to an array. Here is a shortened example; For a full example see **ipstack's [documentation](https://ipstack.com/documentation#standard)** on Standard IP lookups: 

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

Copy **phpunit.xml.dist** to **phunit.xml** and adapt the ipstack-related globals. Endpoint and API key are self-explaining; the dummy IP is the IP address to check during test runs. 

```xml
	<php>
		<var name="IPSTACK_ENDPOINT"   value="http://api.ipstack.com/" />
		<var name="IPSTACK_APIKEY"     value="your_api_key" />
		<var name="IPSTACK_DUMMY_IP"   value="8.8.4.4" />
	</php>
```

**Run phpunit** using vendor binary or composer *test* script:

```bash
$ vendor/bin/phpunit
# or
$ composer test
```

