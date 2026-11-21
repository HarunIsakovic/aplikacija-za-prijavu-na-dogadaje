import { Head, Link, router } from '@inertiajs/react';
import { CalendarDays, CalendarPlus, Heart } from 'lucide-react';
import { useEffect, useState } from 'react';

type Category = {
    id: number;
    name: string;
};

type Tag = {
    id: number;
    name: string;
};

type EventData = {
    id: number;
    slug: string;
    image: string | null;
    title: string;
    description: string;
    location: string;
    starts_at: string;
    ends_at: string | null;
    capacity: number;
    seat_rows: number;
    seat_columns: number;
    registered_count: number;
    is_registered?: boolean;
    is_favorite?: boolean;
    categories: Category[];
    tags: Tag[];
};

type Props = {
    event: EventData;
    can_register: boolean;
    can_favorite: boolean;
    is_archived: boolean;
};

function formatIcsDate(dateString: string): string {
    return new Date(dateString)
        .toISOString()
        .replace(/[-:]/g, '')
        .replace(/\.\d{3}Z$/, 'Z');
}

function escapeIcsText(value: string): string {
    return value
        .replace(/\\/g, '\\\\')
        .replace(/\r\n|\n|\r/g, '\\n')
        .replace(/,/g, '\\,')
        .replace(/;/g, '\\;');
}

export default function EventShow({
    event,
    can_register,
    can_favorite,
    is_archived,
}: Props) {
    const availableSeats = Math.max(0, event.capacity - event.registered_count);

    const [timeLeft, setTimeLeft] = useState(() =>
        Math.max(0, new Date(event.starts_at).getTime() - Date.now()),
    );

    const [selectedSeats, setSelectedSeats] = useState<string[]>([]);
    const [selectionInitialized, setSelectionInitialized] = useState(false);

    const seatRows = Array.from(
        { length: event.seat_rows },
        (_, index) => index + 1,
    );

    const seatColumns = Array.from(
        { length: event.seat_columns },
        (_, index) => index + 1,
    );

    const canSelectSeats =
        can_register &&
        !is_archived &&
        !event.is_registered &&
        availableSeats > 0;

    useEffect(() => {
        const interval = window.setInterval(() => {
            setTimeLeft(
                Math.max(0, new Date(event.starts_at).getTime() - Date.now()),
            );
        }, 1000);

        return () => window.clearInterval(interval);
    }, [event.starts_at]);

    useEffect(() => {
        const params = new URLSearchParams(window.location.search);
        const savedSeats = params.get('seats');

        if (savedSeats) {
            const validSeats = savedSeats.split(',').filter((seat) => {
                const match = seat.match(/^(\d+)-(\d+)$/);

                if (!match) {
                    return false;
                }

                const row = Number(match[1]);
                const column = Number(match[2]);

                return (
                    row >= 1 &&
                    row <= event.seat_rows &&
                    column >= 1 &&
                    column <= event.seat_columns
                );
            });

            setSelectedSeats([...new Set(validSeats)]);
        }

        setSelectionInitialized(true);
    }, [event.id, event.seat_rows, event.seat_columns]);

    useEffect(() => {
        if (!selectionInitialized) {
            return;
        }

        const url = new URL(window.location.href);

        if (selectedSeats.length > 0) {
            url.searchParams.set('seats', selectedSeats.join(','));
        } else {
            url.searchParams.delete('seats');
        }

        window.history.replaceState(
            window.history.state,
            '',
            `${url.pathname}${url.search}${url.hash}`,
        );
    }, [selectedSeats, selectionInitialized]);

    const totalSeconds = Math.floor(timeLeft / 1000);
    const days = Math.floor(totalSeconds / 86400);
    const hours = Math.floor((totalSeconds % 86400) / 3600);
    const minutes = Math.floor((totalSeconds % 3600) / 60);
    const seconds = totalSeconds % 60;

    function toggleSeat(row: number, column: number) {
        if (!canSelectSeats) {
            return;
        }

        const seatId = `${row}-${column}`;

        setSelectedSeats((currentSeats) =>
            currentSeats.includes(seatId)
                ? currentSeats.filter((seat) => seat !== seatId)
                : [...currentSeats, seatId].sort((a, b) => {
                      const [rowA, columnA] = a.split('-').map(Number);
                      const [rowB, columnB] = b.split('-').map(Number);

                      return rowA - rowB || columnA - columnB;
                  }),
        );
    }

    function addToCalendar() {
        const now = new Date();

        const icsContent = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//Aplikacija za prijavu na dogadjaje//BS',
            'CALSCALE:GREGORIAN',
            'METHOD:PUBLISH',
            'BEGIN:VEVENT',
            `UID:event-${event.id}@prijava-na-dogadjaje`,
            `DTSTAMP:${formatIcsDate(now.toISOString())}`,
            `DTSTART:${formatIcsDate(event.starts_at)}`,
            ...(event.ends_at ? [`DTEND:${formatIcsDate(event.ends_at)}`] : []),
            `SUMMARY:${escapeIcsText(event.title)}`,
            `LOCATION:${escapeIcsText(event.location)}`,
            `DESCRIPTION:${escapeIcsText(event.description)}`,
            'STATUS:CONFIRMED',
            'END:VEVENT',
            'END:VCALENDAR',
        ].join('\r\n');

        const blob = new Blob([icsContent], {
            type: 'text/calendar;charset=utf-8',
        });

        const url = URL.createObjectURL(blob);
        const link = document.createElement('a');

        link.href = url;
        link.download = `${event.slug}.ics`;

        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);

        window.setTimeout(() => URL.revokeObjectURL(url), 1000);
    }

    function registerForEvent() {
        router.post(`/events/${event.id}/registrations`);
    }

    function cancelRegistration() {
        router.delete(`/events/${event.id}/registrations`);
    }

    function toggleFavorite() {
        if (event.is_favorite) {
            router.delete(`/events/${event.id}/favorite`);
        } else {
            router.post(`/events/${event.id}/favorite`);
        }
    }

    return (
        <>
            <Head title={event.title} />

            <div className="mx-auto max-w-4xl space-y-6 p-6">
                <div>
                    <Link
                        href={is_archived ? '/events/archive' : '/events'}
                        className="text-sm text-muted-foreground hover:underline"
                    >
                        ←{' '}
                        {is_archived ? 'Nazad na arhivu' : 'Nazad na događaje'}
                    </Link>

                    {is_archived && (
                        <div className="mt-4 rounded-lg border border-muted bg-muted/50 p-4">
                            <p className="font-semibold">Događaj je završen</p>

                            <p className="mt-1 text-sm text-muted-foreground">
                                Ovaj događaj je završen. Prijave više nisu
                                dostupne.
                            </p>
                        </div>
                    )}

                    <h1 className="mt-4 text-3xl font-bold">{event.title}</h1>

                    {event.image ? (
                        <img
                            src={`/storage/${event.image}`}
                            alt={event.title}
                            className="mt-6 h-72 w-full rounded-xl object-cover shadow-sm"
                        />
                    ) : (
                        <div className="mt-6 flex h-48 items-center justify-center rounded-xl bg-muted">
                            <CalendarDays className="size-12 text-muted-foreground" />
                        </div>
                    )}
                </div>

                {!is_archived && timeLeft > 0 && (
                    <div className="rounded-lg border p-6 text-center shadow-sm">
                        <h2 className="mb-4 text-lg font-semibold">
                            Događaj počinje za:
                        </h2>

                        <div className="grid grid-cols-4 gap-3">
                            {[
                                { value: days, label: 'Dana' },
                                { value: hours, label: 'Sati' },
                                { value: minutes, label: 'Minuta' },
                                { value: seconds, label: 'Sekundi' },
                            ].map((item) => (
                                <div
                                    key={item.label}
                                    className="rounded-lg bg-muted p-3"
                                >
                                    <div className="text-2xl font-bold">
                                        {item.value}
                                    </div>
                                    <div className="text-sm text-muted-foreground">
                                        {item.label}
                                    </div>
                                </div>
                            ))}
                        </div>
                    </div>
                )}

                <div className="rounded-lg border p-6 shadow-sm">
                    <div className="space-y-4">
                        <div>
                            <h2 className="text-lg font-semibold">
                                Opis događaja
                            </h2>

                            <p className="mt-2 whitespace-pre-line text-muted-foreground">
                                {event.description}
                            </p>
                        </div>

                        <div>
                            <strong>Kategorije:</strong>

                            <div className="mt-2 flex flex-wrap gap-2">
                                {event.categories.map((category) => (
                                    <span
                                        key={category.id}
                                        className="rounded-full bg-primary/10 px-3 py-1 text-xs font-medium text-primary"
                                    >
                                        {category.name}
                                    </span>
                                ))}
                            </div>
                        </div>

                        <div>
                            <strong>Lokacija:</strong> {event.location}
                        </div>

                        <div>
                            <strong>Početak:</strong>{' '}
                            {new Date(event.starts_at).toLocaleString('bs-BA')}
                        </div>

                        {event.ends_at && (
                            <div>
                                <strong>Završetak:</strong>{' '}
                                {new Date(event.ends_at).toLocaleString(
                                    'bs-BA',
                                )}
                            </div>
                        )}

                        <div>
                            <strong>Ukupan kapacitet:</strong> {event.capacity}{' '}
                            mjesta
                        </div>

                        <div>
                            <strong>Prijavljeno korisnika:</strong>{' '}
                            {event.registered_count}
                        </div>

                        {!is_archived && (
                            <div>
                                <strong>Slobodna mjesta:</strong>{' '}
                                {availableSeats}
                            </div>
                        )}

                        {event.tags.length > 0 && (
                            <div>
                                <strong>Tagovi:</strong>

                                <div className="mt-2 flex flex-wrap gap-2">
                                    {event.tags.map((tag) => (
                                        <span
                                            key={tag.id}
                                            className="rounded-full bg-muted px-3 py-1 text-xs"
                                        >
                                            {tag.name}
                                        </span>
                                    ))}
                                </div>
                            </div>
                        )}
                    </div>

                    {!is_archived && (
                        <div className="mt-6 space-y-3 border-t pt-6">
                            <div className="space-y-4">
                                <div>
                                    <h2 className="text-lg font-semibold">
                                        Odabir sjedišta
                                    </h2>

                                    <p className="mt-1 text-sm text-muted-foreground">
                                        Odaberite sjedišta klikom na mjesta u
                                        mapi.
                                    </p>
                                </div>

                                <div className="rounded-lg bg-muted p-3 text-center text-sm font-semibold">
                                    POZORNICA
                                </div>

                                <div className="overflow-x-auto">
                                    <div className="mx-auto w-fit space-y-3">
                                        {seatRows.map((row) => (
                                            <div
                                                key={row}
                                                className="flex items-center gap-2"
                                            >
                                                <span className="w-6 text-sm font-medium text-muted-foreground">
                                                    {row}
                                                </span>

                                                <div className="flex gap-2">
                                                    {seatColumns.map(
                                                        (column) => {
                                                            const seatId = `${row}-${column}`;
                                                            const isSelected =
                                                                selectedSeats.includes(
                                                                    seatId,
                                                                );

                                                            return (
                                                                <button
                                                                    key={seatId}
                                                                    type="button"
                                                                    disabled={
                                                                        !canSelectSeats
                                                                    }
                                                                    onClick={() =>
                                                                        toggleSeat(
                                                                            row,
                                                                            column,
                                                                        )
                                                                    }
                                                                    title={`Red ${row}, sjedište ${column}`}
                                                                    aria-label={`Red ${row}, sjedište ${column}`}
                                                                    aria-pressed={
                                                                        isSelected
                                                                    }
                                                                    className={`flex size-9 items-center justify-center rounded-t-lg border text-xs font-medium transition ${
                                                                        isSelected
                                                                            ? 'border-primary bg-primary text-primary-foreground'
                                                                            : 'border-border bg-background hover:border-primary hover:bg-primary/10'
                                                                    } ${
                                                                        !canSelectSeats
                                                                            ? 'cursor-not-allowed opacity-60'
                                                                            : 'cursor-pointer'
                                                                    }`}
                                                                >
                                                                    {column}
                                                                </button>
                                                            );
                                                        },
                                                    )}
                                                </div>
                                            </div>
                                        ))}
                                    </div>
                                </div>

                                <div className="flex flex-wrap justify-center gap-4 text-sm">
                                    <div className="flex items-center gap-2">
                                        <span className="size-4 rounded border bg-background" />
                                        Slobodno za odabir
                                    </div>

                                    <div className="flex items-center gap-2">
                                        <span className="size-4 rounded border border-primary bg-primary" />
                                        Odabrano
                                    </div>
                                </div>

                                {canSelectSeats ? (
                                    <div className="rounded-lg bg-muted p-3">
                                        <strong>Odabrana sjedišta:</strong>{' '}
                                        {selectedSeats.length > 0
                                            ? selectedSeats
                                                  .map((seat) => {
                                                      const [row, column] =
                                                          seat.split('-');

                                                      return `Red ${row}, sjedište ${column}`;
                                                  })
                                                  .join('; ')
                                            : 'Niste odabrali nijedno sjedište.'}
                                    </div>
                                ) : !can_register ? (
                                    <p className="text-sm text-muted-foreground">
                                        Prijavite se kako biste mogli odabrati
                                        sjedišta.
                                    </p>
                                ) : event.is_registered ? (
                                    <p className="text-sm text-muted-foreground">
                                        Već ste prijavljeni na ovaj događaj.
                                    </p>
                                ) : (
                                    <p className="text-sm text-muted-foreground">
                                        Nema više slobodnih mjesta za ovaj
                                        događaj.
                                    </p>
                                )}

                                <p className="text-xs text-muted-foreground">
                                    Odabir se trenutno čuva u URL-u. Sjedišta
                                    još nisu rezervisana.
                                </p>
                            </div>

                            <button
                                type="button"
                                onClick={addToCalendar}
                                className="flex w-full items-center justify-center gap-2 rounded-md border px-4 py-3 font-medium hover:bg-muted"
                            >
                                <CalendarPlus className="size-5" />
                                Dodaj u kalendar
                            </button>

                            {can_favorite && (
                                <button
                                    type="button"
                                    onClick={toggleFavorite}
                                    className="flex w-full items-center justify-center gap-2 rounded-md border px-4 py-3 font-medium hover:bg-muted"
                                >
                                    <Heart
                                        className={`size-5 ${
                                            event.is_favorite
                                                ? 'fill-current text-red-500'
                                                : ''
                                        }`}
                                    />
                                    {event.is_favorite
                                        ? 'Ukloni iz omiljenih'
                                        : 'Dodaj u omiljene'}
                                </button>
                            )}

                            {!can_register ? (
                                <Link
                                    href="/login"
                                    className="block w-full rounded-md bg-primary px-4 py-3 text-center font-medium text-primary-foreground hover:bg-primary/90"
                                >
                                    Prijavite se da biste se prijavili na
                                    događaj
                                </Link>
                            ) : event.is_registered ? (
                                <button
                                    type="button"
                                    onClick={cancelRegistration}
                                    className="w-full rounded-md border border-destructive px-4 py-3 font-medium text-destructive hover:bg-destructive/10"
                                >
                                    Otkaži prijavu
                                </button>
                            ) : availableSeats > 0 ? (
                                <button
                                    type="button"
                                    onClick={registerForEvent}
                                    className="w-full rounded-md bg-primary px-4 py-3 font-medium text-primary-foreground hover:bg-primary/90"
                                >
                                    Prijavi se na događaj
                                </button>
                            ) : (
                                <p className="rounded-md bg-muted p-3 text-center font-medium">
                                    Događaj je popunjen
                                </p>
                            )}
                        </div>
                    )}
                </div>
            </div>
        </>
    );
}
