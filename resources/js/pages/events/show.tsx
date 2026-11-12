import { Head, router } from '@inertiajs/react';
import { CalendarDays } from 'lucide-react';

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
    image: string | null;
    title: string;
    description: string;
    location: string;
    starts_at: string;
    ends_at: string | null;
    capacity: number;
    registered_count: number;
    is_registered: boolean;
    category: Category;
    tags: Tag[];
};

type Props = {
    event: EventData;
};

export default function EventShow({ event }: Props) {
    const availableSeats = Math.max(0, event.capacity - event.registered_count);

    function registerForEvent() {
        router.post(`/events/${event.id}/registrations`);
    }

    function cancelRegistration() {
        router.delete(`/events/${event.id}/registrations`);
    }

    return (
        <>
            <Head title={event.title} />

            <div className="mx-auto max-w-4xl space-y-6 p-6">
                <div>
                    <a
                        href="/events"
                        className="text-sm text-muted-foreground hover:underline"
                    >
                        ← Nazad na događaje
                    </a>

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
                            <strong>Kategorija:</strong> {event.category.name}
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

                        <div>
                            <strong>Slobodna mjesta:</strong> {availableSeats}
                        </div>

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

                    <div className="mt-6 border-t pt-6">
                        {event.is_registered ? (
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
                </div>
            </div>
        </>
    );
}
