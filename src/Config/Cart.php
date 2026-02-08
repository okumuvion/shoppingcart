<?php

declare(strict_types=1);

namespace Eddieodira\Shoppingcart\Config;

use CodeIgniter\Config\BaseConfig;

class Cart extends BaseConfig
{
    /**
     * This default tax rate will be used when you make a class implement the
     * taxable interface and use the HasTax trait.
     */
    public int $taxRate = 16;

    /**
     * Here you can set the connection that the shoppingcart should use when
     * storing and restoring a cart.
     */
    public string $table = 'shopping_cart';

    /**
     * This defaults will be used for the formated numbers if you don't
     * set them in the method call.
     */
    public array $format = [
        'decimal_places'    => 2,
        'decimal_point'     => '.',
        'number_separator'  => ',',
    ];
}
