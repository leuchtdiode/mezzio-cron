<?php
declare(strict_types=1);

namespace Cron\Shutdownable;

use Common\Cli\Shutdownable;
use Common\Db\FilterChain;
use Cron\Db\Execution\Filter as ExecutionDbFilter;
use Cron\Db\Execution\Repository;
use Cron\Execution\Status;
use Cron\Instance;
use Throwable;

readonly class NoProcessingCron implements Shutdownable
{
	public function __construct(
		private Instance $instance,
		private Repository $repository,
	)
	{
	}

	/**
	 * @throws Throwable
	 */
	public function isShutdownable(): bool
	{
		// only executions of this very instance may block its shutdown, executions of other
		// instances sharing the host are none of its business
		$processingCount = $this->repository->countWithFilter(
			FilterChain::create()
				->addFilter(ExecutionDbFilter\Status::is(Status::RUNNING))
				->addFilter(ExecutionDbFilter\Instance::is($this->instance->get()))
		);

		return $processingCount === 0;
	}
}