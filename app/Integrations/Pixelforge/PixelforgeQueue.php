<?php

declare(strict_types=1);

namespace App\Integrations\Pixelforge;

use LogicException;

final class PixelforgeQueue
{
    public static function connection(): string
    {
        $connection = config('services.pixelforge.queue_connection');
        $driver = is_string($connection) ? config('queue.connections.'.$connection.'.driver') : null;

        if (! is_string($connection) || ! in_array($driver, ['database', 'redis', 'sqs', 'beanstalkd'], true)) {
            throw new LogicException('Pixelforge requires a configured asynchronous queue connection.');
        }

        if ($driver !== 'sqs' && (int) config('queue.connections.'.$connection.'.retry_after', 0) <= 35) {
            throw new LogicException('Pixelforge queue retry_after must exceed the 30-second worker timeout with a safety margin.');
        }

        return $connection;
    }
}
