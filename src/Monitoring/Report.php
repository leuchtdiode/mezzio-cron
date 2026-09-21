<?php
declare(strict_types=1);

namespace Cron\Monitoring;

/**
 * What FaultyCronsProvider found: the jobs that are faulty, and the ones that are pending -
 * monitored, but without a single execution on this host yet, so there is nothing to judge.
 */
class Report
{
	/**
	 * @var FaultyCron[]
	 */
	private array $faulty = [];

	/**
	 * @var string[] job keys
	 */
	private array $pending = [];

	public static function create(): static
	{
		return new static();
	}

	/**
	 * @return FaultyCron[]
	 */
	public function getFaulty(): array
	{
		return $this->faulty;
	}

	public function addFaulty(FaultyCron $faultyCron): Report
	{
		$this->faulty[] = $faultyCron;

		return $this;
	}

	/**
	 * @return string[] job keys
	 */
	public function getPending(): array
	{
		return $this->pending;
	}

	public function addPending(string $key): Report
	{
		$this->pending[] = $key;

		return $this;
	}
}
