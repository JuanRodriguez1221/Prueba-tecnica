<?php

declare(strict_types=1);

use VotingSystem\Core\Database;

require __DIR__ . '/bootstrap.php';

$schema = file_get_contents(dirname(__DIR__) . '/database/schema.sql');

if ($schema === false) {
    throw new RuntimeException('Could not read database/schema.sql');
}

Database::connection()->exec($schema);

echo "Database migrated successfully.\n";
