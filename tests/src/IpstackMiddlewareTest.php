<?php
namespace tests;

use Germania\IpstackClient\IpstackMiddleware;
use Germania\IpstackClient\IpstackClient;
use Germania\IpstackClient\IpstackClientInterface;

use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\ServerRequest;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

use Prophecy\Argument;

class IpstackMiddlewareTest extends \PHPUnit\Framework\TestCase
{

	use CredentialsTrait;


	/**
	 * @dataProvider provideValidCredentials
	 */
	public function testInvokation( $client, $endpoint, $apikey, $client_ip)
	{
		$ipstack_client = $this->prophesize( IpstackClientInterface::class );
		$sut = new IpstackMiddleware( $ipstack_client->reveal() );

		$this->assertTrue( is_callable( $sut ));

	}



	/**
	 * @dataProvider provideResponsesAndIpStuff
	 */
	public function testWithAndWithoutIpstackAttributes( $ipstack_response_mock, $ip_address_attribute, $client_ip)
	{
		$ipstack_client = $this->prophesize( IpstackClientInterface::class );
		$ipstack_client->get( Argument::type("string"), Argument::any())->willReturn( $ipstack_response_mock );
		$sut = new IpstackMiddleware( $ipstack_client->reveal(), $ip_address_attribute );

		$server_params = array('REMOTE_ADDR' => $client_ip);
		$request  = new ServerRequest('GET', 'http://httpbin.org/get', [], null, null, $server_params);

		if ($ip_address_attribute):
			$request = $request->withAttribute($ip_address_attribute, $client_ip);
		endif;

		$response = new Response;

		$next = function($request, $response) {
			return $response;
		};

		// Invoke middleware as Slim would do
		$result = $sut($request, $response, $next);
		$this->assertInstanceOf( ResponseInterface::class, $result );
	}


	public function provideResponsesAndIpStuff()
	{
		$client_ip = "8.8.4.4"; 
		$ipstack_response_mock = [
			'ip' => "8.8.4.4",
			'country_code' => null,
			'country_name' => null,
		];
		
		return array(
			[ $ipstack_response_mock, "ip_address", $client_ip],
			[ $ipstack_response_mock, null,         $client_ip]
		);		
	}





	/**
	 * @dataProvider provideResponsesAndInvalidIpStuff
	 */
	public function testInvalidIps(  $ipstack_response_mock, $ip_address_attribute, $client_ip)
	{
		$ipstack_client = $this->prophesize( IpstackClientInterface::class );
		$ipstack_client->get( Argument::type("string"), Argument::any())->willReturn( $ipstack_response_mock );
		$sut = new IpstackMiddleware( $ipstack_client->reveal(), $ip_address_attribute );

		// Mock various invliad IP addresses
		$server_params = array('REMOTE_ADDR' => $client_ip);
		$request  = new ServerRequest('GET', 'http://httpbin.org/get', [], null, null, $server_params);
		if ($ip_address_attribute):
			$request = $request->withAttribute($ip_address_attribute, $client_ip);
		endif;

		$response = new Response;

		$next = function($request, $response) {
			return $response;
		};

		// Invoke middleware as Slim would do
		$result = $sut($request, $response, $next);
		$this->assertInstanceOf( ResponseInterface::class, $result );

		// So this is IMPORTANT
		$this->assertEquals( $result->getStatusCode(), 400 );
	}



	public function provideResponsesAndInvalidIpStuff()
	{
		$param_set = array();

		$invalid_client_ips = array(null, "", 22);
		foreach( $invalid_client_ips as $invalid_client_ip):
			$ipstack_response_mock = [
				'ip' => $invalid_client_ip,
				'country_code' => null,
				'country_name' => null,
			];

			$param_set[] = [ $ipstack_response_mock, "ip_address", $invalid_client_ip];
			$param_set[] = [ $ipstack_response_mock, null,         $invalid_client_ip];
		endforeach;

		
		return $param_set;		
	}


}