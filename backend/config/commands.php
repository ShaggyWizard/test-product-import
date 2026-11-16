<?php

declare(strict_types=1);

use App\Command\ImportCommand;
use App\Command\SeedCommand;

// Консольные команды приложения; собираются из контейнера в bin/console.
return [
    SeedCommand::class,
    ImportCommand::class,
];
