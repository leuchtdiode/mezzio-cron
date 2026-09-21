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
	 * The faulty jobs only - what the monitoring notifies about, see Process. A pending job is
	 * not among them, see report().
	 *
	 * @return FaultyCron[]
	 * @throws Throwable
	 */
	public function get(): array
	{
		return $this->report()->getFaulty();
	}

	/**
	 * @throws Throwable
	 */
	public function report(): Report
	{
		$cronConfig = $this->config['cron'];

		$generallyEnabled = $cronConfig['enabled'];
		$jobsOnly         = $cronConfig['jobsOnly'] ?? [];
		$jobsExclude      = $cronConfig['jobsExclude'] ?? [];

		$report = Report::create();
		$host   = $this->host->get();

		foreach ($this->config['cron']['jobs'] as $key => $cron)
		{
			$cron = Cron::fromArray($cron);

			$enabled = $generallyEnabled
				&& $cron->isEnabled()
				&& (!$jobsOnly || in_array($key, $jobsOnly))
				&& !in_array($key, $jobsExclude);

			if (!$enabled)
			{
				continue;
			}

			if (!($monitoring = $cron->getMonitoring()))
			{
				continue;
			}

			// a job without a single execution on this host has not had its first slot yet: it
			// is neither failed nor stale, only pending. Without this distinction every release
			// that ships a new monitored job reports unhealthy until the job has run once - which
			// it cannot before the release is live, so a deployment that waits for the health
			// check never gets there. A job that ran and died is told apart from this by the
			// clean up, which always keeps the newest execution - see Execution\Cleaner
			$executionsCount = $this->repository->countWithFilter(
				FilterChain::create()
					->addFilter(ExecutionDbFilter\Job::is($key))
					->addFilter(ExecutionDbFilter\Host::is($host))
			);

			if ($executionsCount === 0)
			{
				$report->addPending($key);

				continue;
			}

			$monitoringThreshold = $monitoring->getThreshold();

			$endTime = new DateTime();
			$endTime->modify('-' . $monitoringThreshold->getMinutes() . ' minute');

			$finishedItemsCount = $this->repository->countWithFilter(
				FilterChain::create()
					->addFilter(ExecutionDbFilter\Job::is($key))
					->addFilter(ExecutionDbFilter\Status::is(Status::FINISHED))
					->addFilter(ExecutionDbFilter\Host::is($host))
					->addFilter(ExecutionDbFilter\EndTime::min($endTime))
					->addFilter(ExecutionDbFilter\ExitCode::is(0))
			);

			// there must be at least one successful item within threshold, otherwise it is faulty
			if ($finishedItemsCount === 0)
			{
				$report->addFaulty(
					FaultyCron::create()
						->setKey($key)
						->setCron($cron)
				);
			}
		}

		return $report;
	}
}