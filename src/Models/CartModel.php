<?php

declare(strict_types=1);

namespace Eddieodira\Shoppingcart\Models;

use CodeIgniter\Model;
use CodeIgniter\Settings\Settings;

class CartModel extends Model
{
    protected $table;
    protected $primaryKey = 'id';
    protected $returnType = 'object';
    protected $allowedFields = ['identifier', 'instance', 'content'];
    protected $useTimestamps = true;
    protected $createdField = 'created_at';
    protected $updatedField = 'updated_at';

    /**
     * Get config table name.
     *
     * @return string
     */
    public function __construct()
    {
        parent::__construct();
        
        $this->table = Settings::get('Cart.table') ?? 'cart';
    }
}
