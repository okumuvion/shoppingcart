<?php

declare(strict_types=1);

namespace Eddieodira\Shoppingcart;

use Illuminate\Support\Arr;
use Illuminate\Contracts\Support\Jsonable;
use Illuminate\Contracts\Support\Arrayable;
use Eddieodira\Shoppingcart\Contracts\Buyable;

class CartItem implements Arrayable, Jsonable
{
    /**
     * The rowID of the cart item.
     *
     * @var string
     */
    public string $rowId;

    /**
     * The ID of the cart item.
     *
     * @var int|string
     */
    public int|string $id;

    /**
     * The quantity for this cart item.
     *
     * @var int|float
     */
    public int|float $qty;

    /**
     * The name of the cart item.
     *
     * @var string
     */
    public string $name;

    /**
     * The price without TAX of the cart item.
     *
     * @var float
     */
    public float $price;

    /**
     * The options for this cart item.
     *
     * @var object
     */
    public object|array $options;

    /**
     * The FQN of the associated model.
     *
     * @var string|null
     */
    private ?string $associatedModel = null;

    /**
     * The tax rate for the cart item.
     *
     * @var int|float
     */
    private int|float $taxRate = 0;

    /**
     * Is item saved for later.
     *
     * @var boolean
     */
    private bool $isSaved = false;

    /**
     * CartItem constructor.
     *
     * @param int|string $id
     * @param string     $name
     * @param float      $price
     * @param array      $options
     */
    public function __construct(int|string $id, string $name, float $price, array $options = [])
    {
        if (empty($id)) {
            throw new \InvalidArgumentException('Please supply a valid identifier.');
        }

        if (empty($name)) {
            throw new \InvalidArgumentException('Please supply a valid name.');
        }

        if (strlen(strval($price)) < 0 || !is_numeric($price)) {
            throw new \InvalidArgumentException('Please supply a valid price.');
        }

        $this->id = $id;
        $this->name = $name;
        $this->price = $price;
        $this->options = new CartItemOptions($options);
        $this->rowId = static::generateRowId($id, $options);
    }

    /**
     * Return the formatted price without TAX.
     *
     * @param int|null    $dPlace
     * @param string|null $dPoint
     * @param string|null $nSeparator
     * @return string
     */
    public function price(?int $dPlace = null, ?string $dPoint = null, ?string $nSeparator = null): string
    {
        return static::numberFormat($this->price, $dPlace, $dPoint, $nSeparator);
    }

    /**
     * Return the formatted price with TAX.
     *
     * @param int|null    $dPlace
     * @param string|null $dPoint
     * @param string|null $nSeparator
     * @return string
     */
    public function rowTotal(?int $dPlace = null, ?string $dPoint = null, ?string $nSeparator = null): string
    {
        return static::numberFormat($this->rowTotal, $dPlace, $dPoint, $nSeparator);
    }

    /**
     * Returns the formatted subTotal.
     *
     * @param int|null    $dPlace
     * @param string|null $dPoint
     * @param string|null $thousandSeparator
     * @return string
     */
    public function subTotal(?int $dPlace = null, ?string $dPoint = null, ?string $nSeparator = null): string
    {
        return static::numberFormat($this->subTotal, $dPlace, $dPoint, $nSeparator);
    }

    /**
     * Returns the formatted total.
     *
     * @param int|null    $dPlace
     * @param string|null $dPoint
     * @param string|null $nSeparator
     * @return string
     */
    public function total(?int $dPlace = null, ?string $dPoint = null, ?string $nSeparator = null): string
    {
        return static::numberFormat($this->total, $dPlace, $dPoint, $nSeparator);
    }

    /**
     * Returns the formatted tax.
     *
     * @param int|null    $dPlace
     * @param string|null $dPoint
     * @param string|null $nSeparator
     * @return string
     */
    public function tax(?int $dPlace = null, ?string $dPoint = null, ?string $nSeparator = null): string
    {
        return static::numberFormat($this->tax, $dPlace, $dPoint, $nSeparator);
    }

    /**
     * Returns the formatted tax.
     *
     * @param int|null    $dPlace
     * @param string|null $dPoint
     * @param string|null $nSeparator
     * @return string
     */
    public function taxTotal(?int $dPlace = null, ?string $dPoint = null, ?string $nSeparator = null): string
    {
        return static::numberFormat($this->taxTotal, $dPlace, $dPoint, $nSeparator);
    }

    /**
     * Set the quantity for the cart item.
     *
     * @param int|float $qty
     * @return void
     */
    public function setQuantity(int|float $qty):void
    {
        if (empty($qty) || !is_numeric($qty)) {
            throw new \InvalidArgumentException('Please supply a valid quantity.');
        }

        $this->qty = $qty;
    }

    /**
     * Update the cart item from a buyable.
     *
     * @param \Fluent\ShoppingCart\Contracts\Buyable $item
     * @return void
     */
    public function updateFromBuyable(Buyable $item): void
    {
        $this->id = $item->getBuyableIdentifier($this->options);
        $this->name = $item->getBuyableDescription($this->options);
        $this->price = $item->getBuyablePrice($this->options);
        $this->rowTotal = $this->price;
    }

    /**
     * Update the cart item from an array.
     *
     * @param array $attributes
     * @return void
     */
    public function updateFromArray(array $attributes): void
    {
        $this->id = Arr::get($attributes, 'id', $this->id);
        $this->qty = Arr::get($attributes, 'qty', $this->qty);
        $this->name = Arr::get($attributes, 'name', $this->name);
        $this->price = Arr::get($attributes, 'price', $this->price);
        $this->options = new CartItemOptions(Arr::get($attributes, 'options', $this->options));
        $this->rowTotal = $this->price;

        $this->rowId = $this->generateRowId($this->id, $this->options->all());
    }

    /**
     * Associate the cart item with the given model.
     *
     * @param mixed $cart_model
     * @return \Fluent\ShoppingCart\CartItem
     */
    public function associate(CartModel $cart_model): CartItem
    {
        $this->associatedModel = is_string($cart_model) ? $cart_model : get_class($cart_model);

        return $this;
    }

    /**
     * Set the tax rate.
     *
     * @param int|float $taxRate
     * @return \Fluent\ShoppingCart\CartItem
     */
    public function setTaxRate(int|float $taxRate): CartItem
    {
        $this->taxRate = $taxRate;

        return $this;
    }

    /**
     * Set saved state.
     *
     * @param bool $bool
     * @return \Fluent\ShoppingCart\CartItem
     */
    public function setSaved(bool $bool): CartItem
    {
        $this->isSaved = $bool;

        return $this;
    }

    /**
     * Get an attribute from cart item or get the associated model.
     *
     * @param $attribute
     * @return mixed
     */
    public function __get(string $attribute): ?string
    {
        if (property_exists($this, $attribute)) {
            return $this->{$attribute};
        }

        if ($attribute === 'rowTotal') {
            return number_format(($this->price), 2, '.', '');
        }

        if ($attribute === 'subtotal') {
            return number_format(($this->qty * $this->price), 2, '.', '');
        }

        if ($attribute === 'total') {
            return number_format(($this->qty * $this->rowTotal), 2, '.', '');
        }

        if ($attribute === 'tax') {
            return number_format(($this->price * ($this->taxRate / 100)), 4, '.', '');
        }

        if ($attribute === 'taxTotal') {
            return number_format(($this->tax * $this->qty), 2, '.', '');
        }

        if ($attribute === 'model' && isset($this->associatedModel)) {
            return with(new $this->associatedModel())->find($this->id);
        }

        return null;
    }

    /**
     * Create a new instance from a Buyable.
     *
     * @param \Fluent\ShoppingCart\Contracts\Buyable $item
     * @param array                                         $options
     * @return \Fluent\ShoppingCart\CartItem
     */
    public static function fromBuyable(Buyable $item, array $options = []): CartItem
    {
        return new self($item->getBuyableIdentifier($options), $item->getBuyableDescription($options), $item->getBuyablePrice($options), $options);
    }

    /**
     * Create a new instance from the given array.
     *
     * @param array $attributes
     * @return \Fluent\ShoppingCart\CartItem
     */
    public static function fromArray(array $attributes): CartItem
    {
        $options = Arr::get($attributes, 'options', []);

        return new self($attributes['id'], $attributes['name'], $attributes['price'], $options);
    }

    /**
     * Create a new instance from the given attributes.
     *
     * @param int|string $id
     * @param string     $name
     * @param float      $price
     * @param array      $options
     * @return \Fluent\ShoppingCart\CartItem
     */
    public static function fromAttributes(int|string $id, string $name, float $price, array $options = []): CartItem
    {
        return new self($id, $name, $price, $options);
    }

    /**
     * Generate a unique id for the cart item.
     *
     * @param string $id
     * @param array  $options
     * @return string
     */
    protected static function generateRowId(string $id, array $options): string
    {
        ksort($options);

        return md5($id . serialize($options));
    }

    /**
     * Get the instance as an array.
     *
     * @return array
     */
    public function toArray(): array
    {
        return [
            'rowId'    => $this->rowId,
            'id'       => $this->id,
            'name'     => $this->name,
            'qty'      => $this->qty,
            'price'    => $this->price,
            'options'  => $this->options->toArray(),
            'tax'      => $this->tax,
            'isSaved'  => $this->isSaved,
            'subTotal' => $this->subTotal,
        ];
    }

    /**
     * Convert the object to its JSON representation.
     *
     * @param int $options
     * @return string
     */
    public function toJson($options = 0): string
    {
        if (isset($this->associatedModel)) {
            return json_encode(array_merge($this->toArray(), ['model' => $this->model]), $options);
        }

        return json_encode($this->toArray(), $options);
    }

    /**
     * Get the formatted number.
     *
     * @param float       $value
     * @param int|null    $dPlace
     * @param string|null $dPoint
     * @param string|null $nSeparator
     * @return string
     */
    public static function numberFormat(float $value, ?int $dPlace = null, ?string $dPoint = null, ?string $nSeparator = null): string
    {
        $format = setting('Cart.format');
        if (is_null($dPlace)) {
            $dPlace = $format['decimal_places'] ?? 2;
        }

        if (is_null($dPoint)) {
            $dPoint = $format['decimal_point'] ?? '.';
        }

        if (is_null($nSeparator)) {
            $nSeparator = $format['number_separator'] ?? ',';
        }

        return number_format($value, $dPlace, $dPoint, $nSeparator);
    }
}
