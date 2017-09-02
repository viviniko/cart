<?php

namespace Viviniko\Cart\Console\Commands;

use Viviniko\Support\Console\CreateMigrationCommand;

class CartTableCommand extends CreateMigrationCommand
{
    /**
     * @var string
     */
    protected $name = 'cart:table';

    /**
     * @var string
     */
    protected $description = 'Create a migration for the cart service table';

    /**
     * @var string
     */
    protected $stub = __DIR__.'/stubs/cart.stub';

    /**
     * @var string
     */
    protected $migration = 'create_cart_table';
}
