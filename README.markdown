# Ladder [![Build Status](https://travis-ci.org/Drarok/ladder2.svg?branch=develop)](https://travis-ci.org/Drarok/ladder2) [![Coverage Status](https://coveralls.io/repos/github/Drarok/ladder2/badge.svg?branch=develop)](https://coveralls.io/github/Drarok/ladder2?branch=develop)

## What is it?

Ladder started life many years ago as an extremely simple database migration system.
It's grown over time to include a host of features, and had a ground-up rewrite to keep
it modern (and remove use of the `mysql` extension). It's written for PHP >= 7.2, and
supports the popular MySQL database server via PDO.

## What would I use this for?

It's used in conjunction with source control (Git, Mercurial, SVN, et al) in order to
track the changes made to the database alongside application source code.

This allows multiple developers to work on a project and know whether or not they
have the correct database schema for their local development environments, and
enables them to bring their schema up-to-date with a single command.

Migrations have at least two methods: `apply()` and `rollback()`. The `apply()` method
is run when the migration is applied, and `rollback()` when it is un-applied.
Logically, a `rollback()` method should do the opposite to its counterpart `apply()`
method; Dropping a column instead of adding it, etc.

## What is supported?

* Creating and dropping tables
* Adding, dropping, and altering columns
* Adding and dropping indexes/constraints
* Data operations: insert/update/delete
* Storing metadata when applying a migration, and using it during roll back

## How do I use it?

You can add Ladder to your project using Composer:

```bash
$ composer require zerifas/ladder
$ edit ladder.json
```

Your `ladder.json` file should look something like the included `ladder.json.sample`:

```json
{
    "db": {
        "host": "localhost",
        "dbname": "YOUR_DATABASE",
        "charset": "utf8",
        "username": "privileged_user",
        "password": "G00D_P@ssw0rd%"
    },
    "migrations": [
        {
            "namespace": "YourAppNamespace\\Migration",
            "path":      "src/Migration"
        }
    ]
}
```

If your database does not exist, and your user has access to create it, `init` will do so:

```bash
$ vendor/bin/ladder init
Created database 'test2'.
```

Now, you should be able to run the `status` command to see what's not applied, and `migrate` to apply changes.

```bash
$ vendor/bin/ladder status
Status
    Missing Migrations
        1 - Ladder internal tables
$ vendor/bin/ladder migrate
Migrate from 0 to 1
Applying 1 - Ladder internal tables: OK
```

You can get Ladder to create a migration template file for you:

```bash
$ vendor/bin/ladder create 'Create user table'
src/Migration/Migration1455898526.php
```

The template shows an example of creating and dropping a table, edit and save the file, and then:

```bash
$ vendor/bin/ladder migrate
Migrate from 1 to 1455898526
Applying 1455898526 - Create user table: OK
```

Additionally, you can provide a migration id to the `migrate` command in order to migrate to that specific point.

The `migrate` command can be used to migrate to an _older_ migration only when given the `--rollback` option. This is to avoid accidentally dropping tables or columns.

### Commands

Ladder aims to be self-documenting in use (though we need documentation for the Migration methods):

```bash
$ vendor/bin/ladder
Ladder version 2.1.0

Usage:
  [options] command [arguments]

…

Available commands:
  create   Create a new Migration file.
  help     Displays help for a command
  init     Attempt to create the configured database.
  list     Lists commands
  migrate  Migrate to the latest, or specified migration number.
  reapply  Rollback the given migration (if applied), then apply it.
  remove   Rollback a single migration.
  status   Show the current status of the database.
```

## Example Migration

Here's an example that does _way_ too much for a single Migration, but should cover all the use cases.

```php
<?php

namespace YourAppNamespace\Migration;

use Zerifas\Ladder\Database\Table;
use Zerifas\Ladder\Migration\AbstractMigration;

class Migration1455898557 extends AbstractMigration
{
    public function getName()
    {
        return 'Demo lots of features';
    }

    public function apply()
    {
        // We are assuming that the `users` table already exists for this example,
        // and we are creating the `posts` table, and creating a user.
        $posts = Table::factory('posts');

        // addColumn($name, $type, array $options = [])
        // Possible keys and values for `$options`:
        //     null - boolean used to allow a column's value to be null, default: true
        //     limit - integer for `varchar` column width, or string '10, 3' for `float`/`double` column precision
        //     options - array of strings for `enum` column options
        //     unsigned - boolean, used to mark `integer` columns as unsigned, default: false
        //     default - mixed value to use as default column value
        //     after - column name to add this new column after
        $posts->addColumn('id', 'autoincrement', ['null' => false, 'unsigned' => true])
            ->addColumn('userId', 'integer', ['null' => false, 'unsigned' => true])
            ->addColumn('urlSlug', 'varchar', ['null' => false, 'limit' => 10])
            ->addColumn('createdAt', 'datetime', ['null' => false])
            ->addColumn('publishedAt', 'datetime')
            ->addColumn('title', 'varchar', ['null' => false, 'limit' => 128])
            ->addColumn('body', 'text', ['null' => false])
            ->addIndex('PRIMARY', ['id']) // Create index named PRIMARY, containing the `id` column
            ->addIndex('userId') // If no columns given, Ladder assumes the name is a column
            ->addIndex('userId:createdAt', ['userId', 'createdAt'], ['unique' => true]) // Custom name, specified columns, and unique
            ->create()
        ;

        // Create a user, and get its unique id.
        $users = Table::factory('users');
        $users
            ->addColumn('userGroup', 'integer', ['null' => false, 'default' => 0, 'first' => true])
            ->addColumn('notes', 'text', ['after' => 'userGroup'])
            ->alter()
        ;
        $users->insert([
            'username' => '<username here>',
            'password' => '<some valid password hash>',
        ]);

        // If we return an array from this `apply` method, the same data will be supplied
        // as the `$data` parameter when `rollback` is called.
        return [
            'userId' => $users->getLastInsertId(),
        ];
    }

    public function rollback(array $data = null)
    {
        $users = Table::factory('users');

        if (is_array($data) && array_key_exists('userId', $data)) {
            $users->delete([
                'id' => $data['userId'],
            ]);
        }

        $users
            ->dropColumn('notes')
            ->dropColumn('userGroup')
            ->alter()
        ;

        Table::factory('posts')->drop();
    }
}
```
