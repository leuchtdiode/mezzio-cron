<?php
declare(strict_types=1);

namespace Cron\Wiki;

use Exception;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Psr7\HttpFactory;
use GuzzleHttp\RequestOptions;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Laminas\XmlRpc\Client as XmlRpcClient;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;

class ClientFactory implements FactoryInterface
{
	/**
	 * @param ContainerInterface $container
	 * @param $requestedName
	 * @param array|null $options
	 * @return Client
	 * @throws ContainerExceptionInterface
	 * @throws NotFoundExceptionInterface
	 * @throws Exception
	 */
	public function __invoke(ContainerInterface $container, $requestedName, ?array $options = null): Client
	{
		if (!class_exists(XmlRpcClient::class))
		{
			throw new Exception('Package laminas/laminas-xmlrpc is mandatory');
		}

		if (!class_exists(GuzzleClient::class))
		{
			throw new Exception('Package guzzlehttp/guzzle is mandatory');
		}

		$config = $container->get('config')['cron']['wiki'];

		$httpConfig = [];

		if (($user = $config['user'] ?? null) && ($password = $config['password']))
		{
			$httpConfig[RequestOptions::AUTH] = [ $user, $password ];
		}

		if (($proxy = $config['proxy'] ?? null))
		{
			$httpConfig[RequestOptions::PROXY] = $this->getProxyUri($proxy);
		}

		$httpFactory = new HttpFactory();

		return new Client(
			new XmlRpcClient(
				$config['host'],
				new GuzzleClient($httpConfig),
				$httpFactory,
				$httpFactory
			)
		);
	}

	private function getProxyUri(array $proxy): string
	{
		$scheme = 'http://';
		$host   = $proxy['host'];

		if (($position = strpos($host, '://')) !== false)
		{
			$scheme = substr($host, 0, $position + 3);
			$host   = substr($host, $position + 3);
		}

		$credentials = '';

		if (($user = $proxy['user'] ?? null))
		{
			$credentials = rawurlencode($user);

			if (($password = $proxy['password'] ?? null))
			{
				$credentials .= ':' . rawurlencode($password);
			}

			$credentials .= '@';
		}

		return $scheme . $credentials . $host . ':' . $proxy['port'];
	}
}
