<?php
declare(strict_types=1);

namespace Cron\Monitoring;

use Cron\Cron;

class FaultyCron
{
	private string $key;
	private Cron   $cron;

	public static function create(): static
	{
		return new static();
	}

	public function getKey(): string
	{
		return $this->key;
	}

	public function setKey(string $key): FaultyCron
	{
		$this->key = $key;
		return $this;
	}

	public function getCron(): Cron
	{
		return $this->cron;
	}

	public function setCron(Cron $cron): FaultyCron
	{
		$this->cron = $cron;
		return $this;
	}
}