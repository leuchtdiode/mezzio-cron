<?php
declare(strict_types=1);

namespace Cron;

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
		'shutdownable' => [
			'checkers' => [
				NoProcessingCron::class,
			],
		],
	],
];
