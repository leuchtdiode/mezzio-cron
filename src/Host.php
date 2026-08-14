<?php
declare(strict_types=1);

namespace Cron;

/**
 * Identifies the scheduling scope of this process.
 *
 * All processes sharing this value schedule as one: a job due at a given minute is executed by
 * exactly one of them. Configure the same cron.host in every container of a deployment to scale
 * it out, and different ones to keep deployments sharing a database apart.
 *
 * The machine/container which actually executed a job is tracked separately, see Cron\Instance.
 */
class Host
{
	public function __construct(
		private readonly array $config
	)
	{
	}

	public function get(): string
	{
		return ($this->config['cron']['host'] ?? null)
			?? gethostname()
			?? '';
	}
}