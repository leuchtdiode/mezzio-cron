<?php
declare(strict_types=1);

namespace Cron;

use Cron\Health\FaultyCronsCheck;
use Cron\Shutdownable\NoProcessingCron;
use Doctrine\ORM\Mapping\Driver\AttributeDriver;
use Ramsey\Uuid\Doctrine\UuidType;

return [

	'doctrine' => [
		'types'  => [
			UuidType::NAME => UuidType::class,
		],
		'driver' => [
			'orm_default' => [
				'class' => AttributeDriver::class,
				'paths' => [ __DIR__ . '/../src/Db' ],
			],
		],
	],

	'dependencies' => [
		'abstract_factories' => [
			DefaultFactory::class,
		],
	],

	'common' => [
		'shutdown' => [
			'checkers' => [
				NoProcessingCron::class,
			],
		],
	],

	// merged into the application config, leuchtdiode/mezzio-monitoring is only a suggestion
	// and nothing reads this key when it is not installed
	'monitoring' => [
		'health' => [
			'checkers' => [
				FaultyCronsCheck::class,
			],
		],
	],
];
