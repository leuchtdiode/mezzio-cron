<?php
declare(strict_types=1);

namespace Cron\Execution\Process;

use Amp;
use Amp\Process\Process;
use Common\Db\FilterChain;
use Common\Shutdown\State;
use Cron\Command;
use Cron\Cron;
use Cron\Db\Execution\Filter as ExecutionDbFilter;
use Cron\Db\Execution\Repository;
use Cron\Execution\Cleaner;
use Cron\Execution\ExecuteProcess;
use Cron\Execution\Status;
use Cron\ExecutionParams;
use Cron\Host;
use Cron\Instance;
use DateTime;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;
use function Amp\Promise\all;

class Processor implements Command
{
	use ExecuteProcess;

	public function __construct(
		private readonly array $config,
		private readonly Repository $repository,
		private readonly EntityManager $entityManager,
		private readonly Cleaner $cleaner,
		private readonly Host $host,
		private readonly Instance $instance,
		private readonly State $shutdownState
	)
	{
	}

	/**
	 * @throws NoResultException
	 * @throws NonUniqueResultException
	 */
	public function execute(ExecutionParams $params): void
	{
		$cronConfig = $this->config['cron'] ?? [];

		if (
			!$cronConfig['enabled']
			|| $this->shutdownState->isShuttingDown()
		)
		{
			return;
		}

		// clean up every hour after jobs finished
		$shouldCleanUp = ((int)(new DateTime())->format('i')) === 0;

		$jobsOnly    = $cronConfig['jobsOnly'] ?? [];
		$jobsExclude = $cronConfig['jobsExclude'] ?? [];

		$now = new DateTime();

		// truncate to the minute, so all instances of this host claim the very same slot
		$scheduledFor = (clone $now)->setTime(
			(int)$now->format('G'),
			(int)$now->format('i')
		);

		$processBags = [];

		foreach (($cronConfig['jobs'] ?? []) as $key => $cron)
		{
			$cron = Cron::fromArray($cron);

			$enabled = $cron->isEnabled()
				&& (!$jobsOnly || in_array($key, $jobsOnly))
				&& !in_array($key, $jobsExclude);

			if (!$enabled || !$cron->shouldExecute())
			{
				continue;
			}

			$alreadyRunning = $this->repository->countWithFilter(
					FilterChain::create()
						->addFilter(ExecutionDbFilter\Job::is($key))
						->addFilter(ExecutionDbFilter\Status::is(Status::RUNNING))
						->addFilter(ExecutionDbFilter\Host::is($this->host->get()))
				) > 0;

			if ($alreadyRunning)
			{
				continue;
			}

			$entity = $this->repository->claim(
				host: $this->host->get(),
				instance: $this->instance->get(),
				job: $key,
				scheduledFor: $scheduledFor
			);

			// another instance of this host already claimed the job for this minute
			if (!$entity)
			{
				continue;
			}

			$processBags[] = new ProcessBag(
				process: new Process($cron->getExecCommand()),
				entity: $entity
			);
		}

		if (!$processBags)
		{
			return;
		}

		/**
		 * @var ProcessBag[] $processBags
		 */
		Amp\Loop::run(function () use ($processBags)
		{
			$promises = [];

			foreach ($processBags as $processBag)
			{
				// entity has already been written to db as running by claiming it

				$promises[] = new Amp\Coroutine(
					call_user_func_array([ $this, 'executeProcess' ], [ $processBag ])
				);
			}

			yield all($promises);
		});

		if ($shouldCleanUp)
		{
			$this->cleaner->clean();
		}
	}
}