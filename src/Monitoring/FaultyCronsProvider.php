<?php
declare(strict_types=1);

namespace Cron\Monitoring;

use Common\Db\FilterChain;
use Cron\Cron;
use Cron\Db\Execution\Filter as ExecutionDbFilter;
use Cron\Db\Execution\Repository;
use Cron\Execution\Status;
use Cron\Host;
use DateTime;
use Throwable;

class FaultyCronsProvider
{
	public function __construct(
		private readonly array $config,
		private readonly Host $host,
		private readonly Repository $repository,
	)
	{
	}

	/**
	 * @return FaultyCron[]
	 * @throws Throwable
	 */
	public function get(): array
	{
		$cronConfig = $this->config['cron'];

		$generallyEnabled = $cronConfig['enabled'];
		$jobsOnly         = $cronConfig['jobsOnly'] ?? [];

		$faultyCrons = [];

		foreach ($this->config['cron']['jobs'] as $key => $cron)
		{
			$cron = Cron::fromArray($cron);

			$enabled = $generallyEnabled && $cron->isEnabled() && (!$jobsOnly || in_array($key, $jobsOnly));

			if (!$enabled)
			{
				continue;
			}

			if (!($monitoring = $cron->getMonitoring()))
			{
				continue;
			}

			$monitoringThreshold = $monitoring->getThreshold();

			$endTime = new DateTime();
			$endTime->modify('-' . $monitoringThreshold->getMinutes() . ' minute');

			$finishedItemsCount = $this->repository->countWithFilter(
				FilterChain::create()
					->addFilter(ExecutionDbFilter\Job::is($key))
					->addFilter(ExecutionDbFilter\Status::is(Status::FINISHED))
					->addFilter(ExecutionDbFilter\Host::is($this->host->get()))
					->addFilter(ExecutionDbFilter\EndTime::min($endTime))
					->addFilter(ExecutionDbFilter\ExitCode::is(0))
			);

			// there must be at least one successful item within threshold, otherwise it is faulty
			if ($finishedItemsCount === 0)
			{
				$faultyCrons[] = FaultyCron::create()
					->setKey($key)
					->setCron($cron);
			}
		}

		return $faultyCrons;
	}
}