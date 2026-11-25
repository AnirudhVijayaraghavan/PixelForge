<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property string $generation_id
 * @property int $user_id
 * @property string $intent_id
 * @property int $held
 * @property int|null $accepted_maximum
 * @property bool $maximum_unavailable
 * @property string $state
 * @property \DateTimeInterface|null $closed_at
 */
class CreditReservation extends Model
{
    protected $primaryKey = 'generation_id';

    protected $keyType = 'string';

    public $incrementing = false;

    public $timestamps = false;

    /** @return array<string,string> */
    protected function casts(): array
    {
        return ['maximum_unavailable' => 'boolean', 'held' => 'integer', 'accepted_maximum' => 'integer', 'closed_at' => 'immutable_datetime'];
    }
}
