<?php

declare(strict_types=1);

use VotingSystem\Core\Database;

require __DIR__ . '/bootstrap.php';

$db = Database::connection();

$candidates = [
    ['Ana Torres', 'Partido Azul'],
    ['Luis Medina', 'Movimiento Verde'],
    ['Camila Rojas', null],
];

$voters = [
    ['Juan Perez', 'juan.perez@example.com'],
    ['Maria Gomez', 'maria.gomez@example.com'],
    ['Carlos Ruiz', 'carlos.ruiz@example.com'],
    ['Laura Castillo', 'laura.castillo@example.com'],
];

$candidateStmt = $db->prepare(
    'INSERT INTO candidates (name, party) VALUES (:name, :party)
     ON DUPLICATE KEY UPDATE party = VALUES(party)'
);
foreach ($candidates as [$name, $party]) {
    $candidateStmt->execute(['name' => $name, 'party' => $party]);
}

$voterStmt = $db->prepare(
    'INSERT INTO voters (name, email) VALUES (:name, :email)
     ON DUPLICATE KEY UPDATE name = VALUES(name)'
);
foreach ($voters as [$name, $email]) {
    $voterStmt->execute(['name' => $name, 'email' => $email]);
}

echo "Database seeded successfully.\n";
