<?php
declare(strict_types=1);

namespace Cron;

/**
 * Identifies the single machine/container this process runs on.
 *
 * In contrast to Cron\Host - which is the shared scheduling scope - this value has to be
 * unique per machine/container, therefore it should not be configured in a shared config.
 */
class Instance
{
	public function __construct(
		private readonly array $config
	)
	{
	}

	public function get(): string
	{
		return ($this->config['cron']['instance'] ?? null)
			?? gethostname()
			?? '';
	}
}
