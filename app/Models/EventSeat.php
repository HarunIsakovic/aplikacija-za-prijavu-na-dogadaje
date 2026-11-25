<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EventSeat extends Model
{
    public const STATUS_AVAILABLE = 'available';

    public const STATUS_RESERVED = 'reserved';

    public const STATUS_SOLD = 'sold';

    protected $fillable = [
        'event_id',
        'row_number',
        'seat_number',
        'status',
        'reservation_id',
    ];

    protected function casts(): array
    {
        return [
            'row_number' => 'integer',
            'seat_number' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Event, $this>
     */
    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    /**
     * @return BelongsTo<Reservation, $this>
     */
    public function reservation(): BelongsTo
    {
        return $this->belongsTo(Reservation::class);
    }

    /**
     * @return HasMany<ReservationSeat, $this>
     */
    public function reservationSeats(): HasMany
    {
        return $this->hasMany(ReservationSeat::class);
    }
}
