<?php

namespace App\Models;

/**
 * @property int $id format(int64) example(10)
 * @property int $petId format(int64) example(198772)
 * @property int $quantity format(int32) example(7)
 * @property string $shipDate format(date-time)
 * @property string $status enum(placed,approved,delivered) Order Status example(approved)
 * @property bool $complete
 */
class Order
{
}
