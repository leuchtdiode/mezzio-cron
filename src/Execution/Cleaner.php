<?php
declare(strict_types=1);

namespace Cron\Execution;

use Cron\Cron;
use Cron\Db\Execution\Entity;
use Cron\Db\Execution\Repository;
use Cron\Host;
use DateTime;

class Cleaner
{
	public function __construct(
		private readonly array $config,
		private readonly Repository $repository,
		private readonly Host $host
	)
	{
	}

	public function clean(): void
	{
		foreach ($this->config['cron']['jobs'] as $key => $cron)
		{
			$cron = Cron::fromArray($cron);

			$qb = $this->repository->createQueryBuilder('t');

			$expr = $qb->expr();

			$cleanUpThreshold = $cron->getCleanUpThreshold();

			$maxStartTime = new DateTime();
			$maxStartTime->modify('-' . $cleanUpThreshold->getMinutes() . ' minute');

			$qb
				->delete()
				->andWhere(
					$expr->eq('t.host', ':host')
				)
				->andWhere(
					$expr->eq('t.job', ':job')
				)
				->andWhere(
					$expr->lte('t.startTime', ':maxStartTime')
				)
				->setParameter('host', $this->host->get())
				->setParameter('job', $key)
				->setParameter('maxStartTime', $maxStartTime->format('Y-m-d H:i:s'));

			// the newest execution stays, however old: a job without any execution is one that
			// never ran, see Monitoring\FaultyCronsProvider, and a job that died longer ago than
			// its clean up threshold must not turn into one of those
			if (($newest = $this->newest($key)))
			{
				$qb
					->andWhere(
						$expr->neq('t.id', ':newest')
					)
					->setParameter('newest', $newest->getId()->toString());
			}

			$qb
				->getQuery()
				->execute();
		}
	}

	private function newest(string $job): ?Entity
	{
		return $this->repository->findOneBy(
			[
				'host' => $this->host->get(),
				'job'  => $job,
			],
			[
				'startTime' => 'DESC',
			]
		);
	}
}