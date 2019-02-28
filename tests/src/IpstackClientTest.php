<?php
namespace tests;

use Germania\IpstackClient\IpstackClient;
use Germania\IpstackClient\IpstackExceptionInterface;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Prophecy\Argument;
use Psr\Http\Message\ResponseInterface;

class IpstackClientTest extends \PHPUnit\Framework\TestCase
{


	/**
	 * @dataProvider provideValidCredentials
	 */
	public function testValidRequest( $client, $endpoint, $apikey, $client_ip)
	{
		$sut = new IpstackClient( $endpoint, $apikey, $client );

		$result = $sut->get( $client_ip );
		print_r( $result );
		$this->assertInternalType( "array", $result );
		$this->assertArrayHasKey( "ip", $result );
		$this->assertArrayHasKey( "country_code", $result );
		$this->assertArrayHasKey( "country_name", $result );
	}


	public function provideValidCredentials()
	{

		$ip       = $GLOBALS['IPSTACK_DUMMY_IP'];
		$endpoint = $GLOBALS['IPSTACK_ENDPOINT'];
		$apikey   = $GLOBALS['IPSTACK_APIKEY'];

		$response = $this->prophesize( ResponseInterface::class);
		$response->getBody()->willReturn( json_encode( array("ip" => $ip, "country_code" => "cc", "country_name" => "Country" )) );
		$response_stub = $response->reveal();

		$client = $this->prophesize( ClientInterface::class );
		$client->request(Argument::type('string'), Argument::type('string'), Argument::type('array'))->willReturn( $response_stub );
		$client_stub = $client->reveal();


		$params_set = array(
			[ $client_stub, "foo",     "bar",   $ip ]
		);

		if (empty($apikey)):
			return $params_set;
		endif;

		return array_merge($params_set, array(
			[ new Client,   $endpoint, $apikey, $ip ],
			[ null,         $endpoint, $apikey, $ip ],
		));
	}



	/**
	 * @dataProvider provideInvalidCredentials
	 */
	public function testInvalidRequest( $client, $endpoint, $apikey, $client_ip)
	{
		$sut = new IpstackClient( $endpoint, $apikey, $client );

		$this->expectException( IpstackExceptionInterface::class );
		$result = $sut->get( $client_ip );
	}


	public function provideInvalidCredentials()
	{
		$endpoint = $GLOBALS['IPSTACK_ENDPOINT'];
		$ip       = $GLOBALS['IPSTACK_DUMMY_IP'];

		$client = $this->prophesize( ClientInterface::class );
		$client->request(Argument::type('string'), Argument::type('string'), Argument::type('array'))->willThrow( RequestException::class );
		$client_stub = $client->reveal();


		return array(
			[ new Client,   $endpoint, "",      $ip ],
			[ null,         $endpoint, "wrong", $ip ],
			[ $client_stub, $endpoint, "wrong", $ip ]
		);
	}

}