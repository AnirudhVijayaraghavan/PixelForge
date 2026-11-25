<?php

namespace App\Models;

use App\Domain\Billing\Money;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $user_id
 * @property int $settled
 * @property int $reserved
 */
#[Fillable(['user_id'])]
class CreditWallet extends Model
{
    protected $primaryKey = 'user_id';

    public $incrementing = false;

    public $timestamps = false;

    protected $attributes = ['settled' => 0, 'reserved' => 0];

    /** @return array<string,string> */
    protected function casts(): array
    {
        return ['settled' => 'integer', 'reserved' => 'integer'];
    }

    public function available(): Money
    {
        return Money::fromNanodollars($this->settled)->subtract(Money::fromNanodollars($this->reserved));
    }

    /** @return array{settled:string,reserved:string,available:string} */
    public function summary(): array
    {
        return ['settled' => Money::fromNanodollars($this->settled)->toUsd(),
            'reserved' => Money::fromNanodollars($this->reserved)->toUsd(), 'available' => $this->available()->toUsd()];
    }
}
