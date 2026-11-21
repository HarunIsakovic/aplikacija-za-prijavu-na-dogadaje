<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventSeatPriceGroup extends Model
{
    protected $fillable = [
        'event_id',
        'from_row',
        'to_row',
        'price',
    ];

    protected function casts(): array
    {
        return [
            'from_row' => 'integer',
            'to_row' => 'integer',
            'price' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<Event, $this>
     */
    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }
}
