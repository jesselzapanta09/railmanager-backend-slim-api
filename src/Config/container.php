<?php

declare(strict_types=1);

use App\Config\Database;

return [
    // Database PDO instance
    PDO::class => function () {
        return Database::getConnection();
    },
];
