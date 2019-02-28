<?php
namespace Germania\IpstackClient;

interface IpstackClientInterface {

	public function get( string $ip ): array;
	
}