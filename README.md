# Mezzio cron

This module provides the possibility to configure the applications crontabs via Mezzio config.

The only crontab which has to be run "outside" on the host machine is

`* * * * * vendor/bin/mezzio-cron process`

The processable jobs are being executed in parallel (sub processes) thanks to `amphp/process`.

You can enable a second crontab for monitoring purposes. Adapt the execution time to your needs:

`? ? ? ? ? vendor/bin/mezzio-cron monitoring`

The monitoring job is separate to not interfer with errors which are happening during processing. Monitoring would be useless if the monitoring job dies within exectuion.

Use `vendor/bin/mezzio-cron help` to show all possible commands.