<?php
declare(strict_types=1);

namespace Cron\Wiki;

use Laminas\XmlRpc\Client as XmlRpcClient;
use Throwable;

/**
 * Wrapper around the XML-RPC client which is final since laminas/laminas-xmlrpc 3.0
 */
class Client
{
	public function __construct(
		private readonly XmlRpcClient $client
	)
	{
	}

	/**
	 * @throws Throwable
	 */
	public function call(string $method, array $params = []): mixed
	{
		return $this->client->call($method, $params);
	}
}
