<?php
declare(strict_types=1);

namespace Cron\Db\Execution;

use Common\Db\EntityRepository;
use Cron\Execution\Status;
use DateTime;
use DateTimeInterface;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Ramsey\Uuid\Uuid;

class Repository extends EntityRepository
{
	/**
	 * Claims the execution slot of the given job for the given minute.
	 *
	 * The unique constraint on (host, job, scheduledFor) makes the insert the point of
	 * synchronization: if several instances of the same host tick at the same minute, exactly
	 * one of them gets the row, all others run into the constraint violation and get null back.
	 *
	 * @return Entity|null The managed entity if this instance won the claim, null otherwise
	 */
	public function claim(
		string $host,
		string $instance,
		string $job,
		DateTimeInterface $scheduledFor
	): ?Entity
	{
		$entityManager = $this->getEntityManager();

		$id = Uuid::uuid4();

		try
		{
			$entityManager
				->getConnection()
				->insert(
					$entityManager->getClassMetadata(Entity::class)->getTableName(),
					[
						'id'           => $id->toString(),
						'host'         => $host,
						'instance'     => $instance,
						'job'          => $job,
						'status'       => Status::RUNNING,
						'scheduledFor' => $scheduledFor->format('Y-m-d H:i:s'),
						'startTime'    => new DateTime()->format('Y-m-d H:i:s'),
					]
				);
		}
		catch (UniqueConstraintViolationException)
		{
			return null;
		}

		return $entityManager->find(Entity::class, $id);
	}
}
