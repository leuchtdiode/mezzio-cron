<?php
declare(strict_types=1);

namespace Cron\Monitoring;

use Cron\Command;
use Cron\ExecutionParams;
use Exception;
use Notification\Notify\NotificationData;
use Notification\Notify\Notifier;
use Psr\Container\ContainerInterface;
use Throwable;

class Process implements Command
{
	public function __construct(
		private readonly array $config,
		private readonly ContainerInterface $container,
		private readonly FaultyCronsProvider $faultyCronsProvider
	)
	{
	}

	/**
	 * @throws Throwable
	 */
	public function execute(ExecutionParams $params): void
	{
		$cronConfig       = $this->config['cron'];
		$monitoringConfig = $cronConfig['monitoring'] ?? null;

		if (!$monitoringConfig)
		{
			throw new Exception('Config cron.monitoring missing');
		}

		if (!class_exists('\Notification\Notify\Notifier'))
		{
			throw new Exception('leuchtdiode/mezzio-notification is mandatory');
		}

		/**
		 * @var Notifier $notifier
		 */
		$notifier = $this->container->get(Notifier::class);

		$faultyCrons = $this->faultyCronsProvider->get();

		if ($faultyCrons)
		{
			$notifier->notify(
				NotificationData::create()
					->setChannels($monitoringConfig['channels'])
					->setData(
						array_map(
							fn(FaultyCron $faultyCron) => sprintf(
								'Cron %s is faulty, please check',
								$faultyCron->getKey()
							),
							$faultyCrons
						)
					)
			);
		}
	}
}