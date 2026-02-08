<?php

declare(strict_types=1);

namespace Eddieodira\Shoppingcart;

use CodeIgniter\I18n\Time;
use CodeIgniter\Events\Events;
use CodeIgniter\Config\Services;
use Illuminate\Support\Collection;
use Eddieodira\Shoppingcart\Models\CartModel;
use Eddieodira\Shoppingcart\Contracts\Buyable;
use Eddieodira\Shoppingcart\Exceptions\InvalidRowIDException;
use Eddieodira\Shoppingcart\Exceptions\UnknownModelException;

class Cart
{
    const string DEFAULT_INSTANCE = 'default';

    /**
     * Instance session manager.
     *
     * @var \CodeIgniter\Session\Session
     */
    protected $session;

    /**
     * Model shopping cart.
     *
     * @var \Fluent\ShoppingCart\Models\CartModel $cart_model
     */
    protected CartModel $cart_model;

    /**
     * @var string
     */
    protected string $instance;

    /**
     * Cart constructor.
     *
     */
    public function __construct()
    {
        $this->session = Services::session();

        $this->cart_model = new CartModel();

        $this->instance(self::DEFAULT_INSTANCE);
    }

    /**
     * Get the current cart instance.
     *
     * @param string|null $instance
     * @return $this
     */
    public function instance(?string $instance = null): Cart
    {
        $instance = $instance ?? self::DEFAULT_INSTANCE;
        
        $this->instance = sprintf('%s.%s', 'cart', $instance);

        return $this;
    }

    /**
     * Get the current instance.
     *
     * @return string
     */
    public function currentInstance(): string
    {
        return str_replace('cart.', '', $this->instance);
    }

    /**
     * Add an item to the cart.
     *
     * @param mixed          $id
     * @param mixed          $name
     * @param int|float|null $qty
     * @param float|null     $price
     * @param array          $options
     * @param float|null     $taxrate
     * @return \Fluent\ShoppingCart\CartItem|array
     */
    public function add(int|string|array $id, ?string $name = null, int|float|null $qty = null, ?float $price = null, array $options = [], ?float $taxrate = null): CartItem|array
    {
        if ($this->isMulti($id)) {
            return array_map(function ($item) {
                return $this->add($item);
            }, $id);
        }

        if ($id instanceof CartItem) {
            $cartItem = $id;
        } else {
            $cartItem = $this->createCartItem($id, $name, $qty, $price, $options, $taxrate);
        }

        $content = $this->getContent();

        if ($content->has($cartItem->rowId)) {
            $cartItem->qty += $content->get($cartItem->rowId)->qty;
        }

        $content->put($cartItem->rowId, $cartItem);

        Events::trigger('cart.added', $cartItem);

        $this->session->set($this->instance, $content);

        return $cartItem;
    }

    /**
     * Update the cart item with the given rowId.
     *
     * @param string $rowId
     * @param mixed  $qty
     * @return \Fluent\ShoppingCart\CartItem
     */
    public function update(string $rowId, int|float $qty)
    {
        $cartItem = $this->get($rowId);

        if ($qty instanceof Buyable) {
            $cartItem->updateFromBuyable($qty);
        } elseif (is_array($qty)) {
            $cartItem->updateFromArray($qty);
        } else {
            $cartItem->qty = $qty;
        }

        $content = $this->getContent();

        if ($rowId !== $cartItem->rowId) {
            $content->pull($rowId);

            if ($content->has($cartItem->rowId)) {
                $existingCartItem = $this->get($cartItem->rowId);
                $cartItem->setQuantity($existingCartItem->qty + $cartItem->qty);
            }
        }

        if ($cartItem->qty <= 0) {
            $this->remove($cartItem->rowId);

            return;
        } else {
            $content->put($cartItem->rowId, $cartItem);
        }

        Events::trigger('cart.updated', $cartItem);

        $this->session->set($this->instance, $content);

        return $cartItem;
    }

    /**
     * Remove the cart item with the given rowId from the cart.
     *
     * @param string $rowId
     * @return void
     */
    public function remove(string $rowId): void
    {
        $cartItem = $this->get($rowId);

        $content = $this->getContent();

        $content->pull($cartItem->rowId);

        Events::trigger('cart.removed', $cartItem);

        $this->session->set($this->instance, $content);
    }

    /**
     * Get a cart item from the cart by its rowId.
     *
     * @param string $rowId
     * @return \Fluent\ShoppingCart\CartItem
     */
    public function get(string $rowId): CartItem
    {
        $content = $this->getContent();

        if (! $content->has($rowId)) {
            throw new InvalidRowIDException("The cart does not contain rowId {$rowId}.");
        }

        return $content->get($rowId);
    }

    /**
     * Destroy the current cart instance.
     *
     * @return void
     */
    public function destroy(): void
    {
        $this->session->remove($this->instance);
    }

    /**
     * Get the content of the cart.
     *
     * @return \Tightenco\Collect\Support\Collection|array
     */
    public function content(): Collection|array
    {
        if (is_null($this->session->get($this->instance))) {
            return new Collection([]);
        }

        return $this->session->get($this->instance);
    }

    /**
     * Get the number of items in the cart.
     *
     * @return int|float
     */
    public function count(): int|float
    {
        return $this->getContent()->sum('qty');
    }

    /**
     * Get the total price of the items in the cart.
     *
     * @param int|null    $dPlace
     * @param string|null $dPoint
     * @param string|null $nSeparator
     * @return string
     */
    public function total(?int $dPlace = null, ?string $dPoint = null, ?string $nSeparator = null): string
    {
        $content = $this->getContent();

        $total = $content->reduce(function ($total, CartItem $cartItem) {
            return $total + ($cartItem->qty * $cartItem->rowTotal) + ($cartItem->qty * $cartItem->tax);
        }, 0);

        return CartItem::numberFormat($total, $dPlace, $dPoint, $nSeparator);
    }

    /**
     * Get the total tax of the items in the cart.
     *
     * @param int|null    $dPlace
     * @param string|null $dPoint
     * @param string|null $nSeparator
     * @return float
     */
    public function tax(?int $dPlace = null, ?string $dPoint = null, ?string $nSeparator = null): float|string
    {
        $content = $this->getContent();

        $tax = $content->reduce(function ($tax, CartItem $cartItem) {
            return $tax + ($cartItem->qty * $cartItem->tax);
        }, 0);

        return CartItem::numberFormat($tax, $dPlace, $dPoint, $nSeparator);
    }

    /**
     * Get the subtotal (total - tax) of the items in the cart.
     *
     * @param int|null    $dPlace
     * @param string|null $dPoint
     * @param string|null $nSeperator
     * @return float
     */
    public function subtotal(?int $dPlace = null, ?string $dPoint = null, ?string $nSeperator = null): float|string
    {
        $content = $this->getContent();

        $subTotal = $content->reduce(function ($subTotal, CartItem $cartItem) {
            return $subTotal + ($cartItem->qty * $cartItem->price);
        }, 0);

        return CartItem::numberFormat($subTotal, $dPlace, $dPoint, $nSeperator);
    }

    /**
     * Search the cart content for a cart item matching the given search closure.
     *
     * @param \Closure $search
     * @return \Tightenco\Collect\Support\Collection
     */
    public function search(\Closure $search): Collection
    {
        return $this->getContent()->filter($search);
    }

    /**
     * Associate the cart item with the given rowId with the given model.
     *
     * @param string $rowId
     * @param mixed  $cart_model
     * @return void
     */
    public function associate(string $rowId, CartModel $cart_model): void
    {
        if (is_string($cart_model) && ! class_exists($cart_model)) {
            throw new UnknownModelException("The supplied model {$cart_model} does not exist.");
        }

        $cartItem = $this->get($rowId);

        $cartItem->associate($cart_model);

        $content = $this->getContent();

        $content->put($cartItem->rowId, $cartItem);

        $this->session->set($this->instance, $content);
    }

    /**
     * Set the tax rate for the cart item with the given rowId.
     *
     * @param string    $rowId
     * @param int|float $taxRate
     * @return void
     */
    public function setTax(string $rowId, int|float $taxRate): void
    {
        $cartItem = $this->get($rowId);

        $cartItem->setTaxRate($taxRate);

        $content = $this->getContent();

        $content->put($cartItem->rowId, $cartItem);

        $this->session->set($this->instance, $content);
    }

    /**
     * Store an the current instance of the cart.
     *
     * @param mixed $identifier
     * @return void
     *
     * @throws \Exception
     */
    public function store(string $identifier): void
    {
        $content = $this->getContent();

        $this->model
            ->where('identifier', $identifier)
            ->where('instance', $this->currentInstance())
            ->delete();

        $this->model->insert([
            'identifier' => $identifier,
            'instance'   => $this->currentInstance(),
            'content'    => serialize($content),
            'created_at' => Time::now(),
            'updated_at' => Time::now(),
        ]);

        Events::trigger('cart.stored');
    }

    /**
     * Restore the cart with the given identifier.
     *
     * @param mixed $identifier
     * @return void
     */
    public function restore(string $identifier):void
    {
        if (! $this->storedCartWithIdentifierExists($identifier)) {
            return;
        }

        $stored = $this->model
            ->where('instance', $this->currentInstance())
            ->where('identifier', $identifier)
            ->first();

        $storedContent = unserialize($stored->content);

        $currentInstance = $this->currentInstance();

        $this->instance($stored->instance);

        $content = $this->getContent();

        foreach ($storedContent as $cartItem) {
            $content->put($cartItem->rowId, $cartItem);
        }

        Events::trigger('cart.restored');

        $this->session->set($this->instance, $content);

        $this->instance($currentInstance);
    }


    /**
     * Deletes the stored cart with given identifier
     *
     * @param mixed $identifier
     * @return mixed
     */
    protected function deleteStoredCart(string $identifier)
    {
        return $this->model->where('identifier', $identifier)->delete();
    }

    /**
     * Magic method to make accessing the total, tax and subtotal properties possible.
     *
     * @param string $attribute
     * @return float|null
     */
    public function __get(string $attribute): ?float
    {
        if ($attribute === 'total') {
            return $this->total();
        }

        if ($attribute === 'tax') {
            return $this->tax();
        }

        if ($attribute === 'subtotal') {
            return $this->subtotal();
        }

        return null;
    }

    /**
     * Get the carts content, if there is no cart content set yet, return a new empty Collection.
     *
     * @return \Tightenco\Collect\Support\Collection|array
     */
    protected function getContent(): Collection|array
    {
        return $this->session->has($this->instance)
            ? $this->session->get($this->instance)
            : new Collection();
    }

    /**
     * Create a new CartItem from the supplied attributes.
     *
     * @param mixed     $id
     * @param mixed     $name
     * @param int|float $qty
     * @param float     $price
     * @param array     $options
     * @param float     $taxrate
     * @return \Fluent\ShoppingCart\CartItem
     */
    private function createCartItem(int|string|array $id, ?string $name = null, int|float|null $qty = null, ?float $price = null, ?array $options = null, float|int|null $taxrate = null): CartItem
    {
        if ($id instanceof Buyable) {
            $cartItem = CartItem::fromBuyable($id, $qty ?: []);
            $cartItem->setQuantity($name ?: 1);
            $cartItem->associate($id);
        } elseif (is_array($id)) {
            $cartItem = CartItem::fromArray($id);
            $cartItem->setQuantity($id['qty']);
        } else {
            $cartItem = CartItem::fromAttributes($id, $name, $price, $options);
            $cartItem->setQuantity($qty);
        }

        if (isset($taxrate) && is_numeric($taxrate)) {
            $cartItem->setTaxRate($taxrate);
        } else {
            $cartItem->setTaxRate(setting('Cart.taxRate'));
        }

        return $cartItem;
    }

    /**
     * Check if the item is a multidimensional array or an array of Buyables.
     *
     * @param mixed $item
     * @return bool
     */
    private function isMulti(array|object $item): bool
    {
        if (! is_array($item)) {
            return false;
        }

        return is_array(reset($item)) || reset($item) instanceof Buyable;
    }

    /**
     * @param $identifier
     * @return bool
     */
    protected function storedCartWithIdentifierExists(string $identifier): bool
    {
        return $this->model->where('identifier', $identifier)->where('instance', $this->currentInstance())->first()
            ? true
            : false;
    }
}
