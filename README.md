# matrix-php

**Version 0.92.0** — [Changelog](CHANGELOG.md) — [Source](https://codeberg.org/joho1968/matrix-php)

Reusable PHP 8.4+ library for interacting with a Matrix/Synapse homeserver.
Provides an Application Service client (`MatrixClient`) for sending events and
managing rooms/users, and a Synapse Admin API client (`SynapseAdminClient`) for
administrative operations. No framework dependencies.

## Requirements

- PHP 8.4+
- `ext-curl`

## Installation

As a Composer path dependency:

```json
{
    "repositories": [
        {
            "type": "path",
            "url": "../matrix-php",
            "options": { "symlink": false }
        }
    ],
    "require": {
        "joho/matrix-php": "dev-main"
    },
    "minimum-stability": "dev",
    "prefer-stable": true
}
```

## MatrixClient

Sends events and manages rooms and users via the Application Service API.
Requires an AS access token (`as_token` from the registration YAML).

```php
use Joho\Matrix\Client\MatrixClient;
use Joho\Matrix\Http\HttpClient;

$client = new MatrixClient(
    http:          new HttpClient(),
    homeserverUrl: 'https://matrix.example.com',
    asToken:       'your_as_token',
    serverName:    'example.com',
);

// Register a user
$user = $client->registerUser( 'alice' );

// Create a room as alice
$room = $client->createRoom(
    [ 'name' => 'General', 'preset' => 'private_chat' ],
    $user->getUserId(),
);

// Send a message with a historical timestamp (ms)
$client->sendEvent(
    $room->getRoomId(),
    'm.room.message',
    [ 'msgtype' => 'm.text', 'body' => 'Hello' ],
    $user->getUserId(),
    1_700_000_000_000,
);
```

## SynapseAdminClient

Wraps the `/_synapse/admin/*` endpoints for user, room, media, token, and
federation management. Requires an admin user's access token.

```php
use Joho\Matrix\Client\SynapseAdminClient;
use Joho\Matrix\Http\HttpClient;

$admin = new SynapseAdminClient(
    http:          new HttpClient(),
    homeserverUrl: 'https://matrix.example.com',
    adminToken:    'syt_...',
);

$users = $admin->listUsers( limit: 50 );
$admin->deactivateUser( '@alice:example.com', erase: true );
$admin->deleteRoom( '!abc:example.com', purge: true );
```

See `src/Contracts/SynapseAdminClientInterface.php` for the full method list.

## Logging

`FileLogger` and `ConsoleLogger` both implement `LoggerInterface` and accept a
minimum log level (`debug`, `info`, `warning`, `error`). `FileLogger` appends to
a file; `ConsoleLogger` writes `info`/`debug` to STDOUT and `warning`/`error`
to STDERR.

```php
use Joho\Matrix\Logger\FileLogger;

$logger = new FileLogger( '/var/log/matrix.log', 'info' );
```

## Projects using this library

- [Into The Matrix (ITM)](https://codeberg.org/joho1968/into-the-matrix) — Mattermost Bulk Export to Matrix/Synapse migration tool
- [mtxctl](https://codeberg.org/joho1968/mtxctl) — Matrix/Synapse admin CLI tool

## License

GNU Affero General Public License v3.0 or later. See `LICENSE`.

## Copyright

Written by Joaquim Homrighausen while converting caffeine into code.
Copyright 2026 Joaquim Homrighausen; all rights reserved.

Sponsored by WebbPlatsen i Sverige AB, Sweden.

If you need a GDPR-safe place to host your Matrix, Mattermost, and/or
RocketChat instance, get in touch with support@webbplatsen.se
