<?php

declare(strict_types=1);

use App\Command\ImportCommand;
use App\Command\SeedCommand;
use Symfony\Component\Messenger\Command\ConsumeMessagesCommand;

// Консольные команды приложения; собираются из контейнера в bin/console.
return [
    SeedCommand::class,
    ImportCommand::class,
    // messenger:consume async — воркер очереди импорта (контейнер worker).
    ConsumeMessagesCommand::class,
];
