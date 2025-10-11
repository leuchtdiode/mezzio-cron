<?php
declare(strict_types=1);

namespace Cron\Health;

use Common\Health\Check;
use Common\Health\CheckResult;
use Cron\Monitoring\FaultyCronsProvider;

readonly class FaultyCronsCheck implements Check
{
	public function __construct(
		private FaultyCronsProvider $faultyCronsProvider
	)
	{
	}

	public function check(): CheckResult
	{
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