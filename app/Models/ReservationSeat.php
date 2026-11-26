<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class ReservationSeat extends Model
{
    protected $fillable = [
        'reservation_id',
        'event_seat_id',
        'price',
    ];

    protected static function booted(): void
    {
        static::creating(function (ReservationSeat $reservationSeat): void {
            $reservationSeat->ticket_uuid ??= (string) Str::uuid();
        });
    }

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<Reservation, $this>
     */
    public function reservation(): BelongsTo
    {
        return $this->belongsTo(Reservation::class);
    }

    /**
     * @return BelongsTo<EventSeat, $this>
     */
    public function eventSeat(): BelongsTo
    {
        return $this->belongsTo(EventSeat::class);
    }
}
