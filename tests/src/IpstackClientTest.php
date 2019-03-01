<?php
namespace tests;

use Germania\IpstackClient\IpstackClient;
use Germania\IpstackClient\IpstackExceptionInterface;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Prophecy\Argument;

class IpstackClientTest extends \PHPUnit\Framework\TestCase
{

	use CredentialsTrait;

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
		$ip4      = $GLOBALS['IPSTACK_DUMMY_IP4'];
		$ip6      = $GLOBALS['IPSTACK_DUMMY_IP6'];

		$client = $this->prophesize( ClientInterface::class );
		$client->request(Argument::type('string'), Argument::type('string'), Argument::type('array'))->willThrow( RequestException::class );
		$client_stub = $client->reveal();


		return array(
			[ new Client,   $endpoint, "",      $ip4 ],
			[ null,         $endpoint, "wrong", $ip4 ],
			[ $client_stub, $endpoint, "wrong", $ip4 ],
			[ new Client,   $endpoint, "",      $ip6 ],
			[ null,         $endpoint, "wrong", $ip6 ],
			[ $client_stub, $endpoint, "wrong", $ip6 ]
		);
	}

}