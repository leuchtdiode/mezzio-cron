# Mezzio cron

This module provides the possibility to configure the applications crontabs via Mezzio config.

The only crontab which has to be run "outside" on the host machine is

`* * * * * vendor/bin/mezzio-cron process`

The processable jobs are being executed in parallel (sub processes) thanks to `amphp/process`.

You can enable a second crontab for monitoring purposes. Adapt the execution time to your needs:

`? ? ? ? ? vendor/bin/mezzio-cron monitoring`

The monitoring job is separate to not interfer with errors which are happening during processing. Monitoring would be useless if the monitoring job dies within exectuion.

Use `vendor/bin/mezzio-cron help` to show all possible commands.

## Running in multiple containers

`cron.host` is the **scheduling scope**, not the machine name. Every process sharing this value
schedules as one: a job which is due at a given minute is executed by exactly one of them.

```php
'cron' => [
	'host' => 'my-app-production', // same value in every container of the deployment
	// ...
],
```

So run `* * * * * vendor/bin/mezzio-cron process` in all your containers and configure the same
`cron.host` in all of them. Use different values to keep deployments which share a database apart
(e.g. staging vs. production).

Deduplication happens in the database, not in PHP: `process` claims a job by inserting its
execution row with `scheduledFor` set to the current minute, and the unique constraint on
`(host, job, scheduledFor)` lets exactly one container win. Losers skip the job for that minute.
There is no leader election and no additional infrastructure involved, any container may win.

The container which actually executed a job is recorded separately in the `instance` column, which
defaults to `gethostname()` and can be overridden via `cron.instance`. Do not put that one into a
shared config - it has to stay unique per container, otherwise a container refuses to shut down
while another one is still processing.

Two things to keep in mind:

* All containers have to agree on the current minute, so keep their clocks in sync (NTP).
* `monitoring` and `wiki` are not deduplicated. Keep those crontabs on a single container,
  otherwise you get one notification per container.

### Schema

Both columns and the unique constraint are part of the entity mapping, so a
`doctrine-migrations diff` in your application picks them up. When migrating an existing
installation, backfill `instance` before making it `NOT NULL`:

```sql
ALTER TABLE cron_execution ADD instance VARCHAR(255) DEFAULT NULL;
ALTER TABLE cron_execution ADD scheduledFor DATETIME DEFAULT NULL;
UPDATE cron_execution SET instance = host WHERE instance IS NULL;
ALTER TABLE cron_execution MODIFY instance VARCHAR(255) NOT NULL;
CREATE UNIQUE INDEX cron_execution_schedule ON cron_execution (host, job, scheduledFor);
```

`scheduledFor` stays nullable on purpose: manually triggered executions
(`vendor/bin/mezzio-cron trigger <job>`) leave it null and are therefore never deduplicated.