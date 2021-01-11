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
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Http\Server\MiddlewareInterface;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Argument;

class IpstackMiddlewareTest extends \PHPUnit\Framework\TestCase
{

	use CredentialsTrait,
        ProphecyTrait;


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


		//
		// Double Pass: Invoke middleware as Slim would do
		//
		$next = function($request, $response) { return $response; };
		$result = $sut($request, $response, $next);
		$this->assertInstanceOf( ResponseInterface::class, $result );

		//
		// PSR-15 approach
		//
		$handler = $this->prophesize( RequestHandlerInterface::class );
		$handler->handle( Argument::any() )->willReturn( $response );
		$result = $sut->process($request, $handler->reveal());
		$this->assertInstanceOf( ResponseInterface::class, $result );

	}


	public function provideResponsesAndIpStuff()
	{
		$ip4 = $GLOBALS['IPSTACK_DUMMY_IP4'];
		$ip6 = $GLOBALS['IPSTACK_DUMMY_IP6'];

		$ip4_ipstack_response_mock = [
			'ip' => $ip4,
			'country_code' => null,
			'country_name' => null,
		];

		$ip6_ipstack_response_mock = [
			'ip' => $ip6,
			'country_code' => null,
			'country_name' => null,
		];

		return array(
			[ $ip4_ipstack_response_mock, "ip_address", $ip4],
			[ $ip4_ipstack_response_mock, null,         $ip4],
			[ $ip6_ipstack_response_mock, "ip_address", $ip6],
			[ $ip6_ipstack_response_mock, null,         $ip6]
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

		//
		// Double Pass: Invoke middleware as Slim would do
		//
		$next = function($request, $response) { return $response; };
		$result = $sut($request, $response, $next);
		$this->assertInstanceOf( ResponseInterface::class, $result );
		$this->assertEquals( $result->getStatusCode(), 400 );


		//
		// PSR-15 approach
		//
		$handler = $this->prophesize( RequestHandlerInterface::class );
		$handler->handle( Argument::any() )->willReturn( $response );
		$result = $sut->process($request, $handler->reveal());
		$this->assertInstanceOf( ResponseInterface::class, $result );
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
