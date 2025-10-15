<?php
declare(strict_types=1);

namespace Cron\Shutdownable;

use Common\Cli\Shutdownable;
use Common\Db\FilterChain;
use Cron\Db\Execution\Filter as ExecutionDbFilter;
use Cron\Db\Execution\Repository;
use Cron\Execution\Status;
use Cron\Host;
use Throwable;

readonly class NoProcessingCron implements Shutdownable
{
	public function __construct(
		private Host $host,
		private Repository $repository,
	)
	{
	}

	/**
	 * @throws Throwable
	 */
	public function isShutdownable(): bool
	{
		$processingCount = $this->repository->countWithFilter(
			FilterChain::create()
				->addFilter(ExecutionDbFilter\Status::is(Status::RUNNING))
				->addFilter(ExecutionDbFilter\Host::is($this->host->get()))
		);

		return $processingCount === 0;
	}
}