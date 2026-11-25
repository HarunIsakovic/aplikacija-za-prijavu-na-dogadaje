<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property Carbon $expires_at
 */
class Reservation extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_CONFIRMED = 'confirmed';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUS_EXPIRED = 'expired';

    protected $fillable = [
        'user_id',
        'event_id',
        'status',
        'total_amount',
        'currency',
        'expires_at',
        'confirmed_at',
        'cancelled_at',
        'expired_at',
    ];

    protected function casts(): array
    {
        return [
            'total_amount' => 'decimal:2',
            'expires_at' => 'datetime',
            'confirmed_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'expired_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Event, $this>
     */
    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    /**
     * @return BelongsToMany<EventSeat, $this>
     */
    public function seats(): BelongsToMany
    {
        return $this->belongsToMany(
            EventSeat::class,
            'reservation_seats',
        )->withPivot('price')->withTimestamps();
    }

    /**
     * @return HasMany<ReservationSeat, $this>
     */
    public function reservationSeats(): HasMany
    {
        return $this->hasMany(ReservationSeat::class);
    }

    /**
     * @return HasMany<Payment, $this>
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }
}
