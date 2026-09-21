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

		$report = $this->faultyCronsProvider->report();

		$result->setHealthy(count($report->getFaulty()) === 0);

		foreach ($report->getFaulty() as $faultyCron)
		{
			$result->addMessage(sprintf(
				'Cron %s is faulty, please check',
				$faultyCron->getKey()
			));
		}

		// healthy, but said: a job that never ran is a job somebody may be waiting for
		foreach ($report->getPending() as $key)
		{
			$result->addMessage(sprintf(
				'Cron %s has not been executed yet',
				$key
			));
		}

		return $result;
	}
}