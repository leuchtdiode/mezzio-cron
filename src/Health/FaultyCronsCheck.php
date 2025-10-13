<?php
declare(strict_types=1);

namespace Cron\Health;

use Cron\Monitoring\FaultyCronsProvider;
use Monitoring\Health\Check;
use Monitoring\Health\CheckResult;
use RuntimeException;
use Throwable;

readonly class FaultyCronsCheck implements Check
{
	public function __construct(
		private FaultyCronsProvider $faultyCronsProvider
	)
	{
	}

	/**
	 * @throws Throwable
	 */
	public function check(): CheckResult
	{
		if (!interface_exists('\Monitoring\Health\Check'))
		{
			throw new RuntimeException('leuchtdiode/mezzio-monitoring is mandatory');
		}

		$result = new CheckResult();
		$result->setKey('cron-faulty-crons');

		$faultyCrons = $this->faultyCronsProvider->get();

		$result->setHealthy(count($faultyCrons) === 0);

		if ($faultyCrons)
		{
			foreach ($faultyCrons as $faultyCron)
			{
				$result->addMessage(sprintf(
					'Cron %s is faulty, please check',
					$faultyCron->getKey()
				));
			}
		}

		return $result;
	}
}