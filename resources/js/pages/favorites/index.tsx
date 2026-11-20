import { Head, Link, router } from '@inertiajs/react';
import {
    CalendarDays,
    Heart,
    MapPin,
    Tag as TagIcon,
    Users,
} from 'lucide-react';

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
    registered_count: number;
    is_registered: boolean;
    can_register: boolean;
    categories: Category[];
    tags: Tag[];
};

type Props = {
    favorites: EventData[];
};

export default function FavoritesIndex({ favorites }: Props) {
    function removeFavorite(eventId: number) {
        router.delete(`/events/${eventId}/favorite`, {
            preserveScroll: true,
        });
    }

    function registerForEvent(eventId: number) {
        router.post(
            `/events/${eventId}/registrations`,
            {},
            {
                preserveScroll: true,
            },
        );
    }

    function cancelRegistration(eventId: number) {
        router.delete(`/events/${eventId}/registrations`, {
            preserveScroll: true,
        });
    }

    return (
        <>
            <Head title="Moji omiljeni događaji" />

            <div className="mx-auto max-w-7xl space-y-8 p-4 sm:p-6">
                <div>
                    <p className="mb-2 text-sm font-medium text-primary">
                        VAŠ IZBOR
                    </p>

                    <h1 className="flex items-center gap-3 text-3xl font-bold tracking-tight">
                        <Heart className="size-8 fill-primary text-primary" />
                        Moji omiljeni događaji
                    </h1>

                    <p className="mt-2 text-muted-foreground">
                        Ovdje možete pregledati događaje koje ste označili kao
                        omiljene.
                    </p>

                    <Link
                        href="/events"
                        className="mt-4 inline-flex items-center gap-2 text-sm font-medium text-primary hover:underline"
                    >
                        <CalendarDays className="size-4" />
                        Pregledaj sve događaje
                    </Link>
                </div>

                <div className="flex flex-wrap items-center justify-between gap-2">
                    <h2 className="text-xl font-semibold">Sačuvani događaji</h2>

                    <span className="rounded-full bg-muted px-3 py-1 text-sm text-muted-foreground">
                        {favorites.length}{' '}
                        {favorites.length === 1 ? 'događaj' : 'događaja'}
                    </span>
                </div>

                {favorites.length === 0 ? (
                    <div className="rounded-xl border border-dashed p-10 text-center">
                        <Heart className="mx-auto mb-4 size-10 text-muted-foreground" />

                        <h2 className="text-lg font-semibold">
                            Još nemate omiljenih događaja
                        </h2>

                        <p className="mt-2 text-muted-foreground">
                            Pregledajte dostupne događaje i označite one koji
                            vam se sviđaju.
                        </p>

                        <Link
                            href="/events"
                            className="mt-5 inline-flex rounded-lg bg-primary px-5 py-2.5 text-sm font-medium text-primary-foreground transition-colors hover:bg-primary/90"
                        >
                            Pronađi događaje
                        </Link>
                    </div>
                ) : (
                    <div className="grid gap-5 md:grid-cols-2 lg:grid-cols-3">
                        {favorites.map((event) => {
                            const availableSeats =
                                event.capacity - event.registered_count;

                            return (
                                <article
                                    key={event.id}
                                    className="flex flex-col rounded-xl border bg-card p-5 shadow-sm transition-all duration-200 hover:-translate-y-1 hover:shadow-md"
                                >
                                    {event.image ? (
                                        <img
                                            src={`/storage/${event.image}`}
                                            alt={event.title}
                                            className="mb-5 h-48 w-full rounded-lg object-cover"
                                        />
                                    ) : (
                                        <div className="mb-5 flex h-48 items-center justify-center rounded-lg bg-muted">
                                            <CalendarDays className="size-12 text-muted-foreground" />
                                        </div>
                                    )}

                                    <div className="mb-3 flex flex-wrap items-start justify-between gap-3">
                                        <div className="flex flex-wrap gap-2">
                                            {event.categories.map(
                                                (category) => (
                                                    <span
                                                        key={category.id}
                                                        className="rounded-full bg-primary/10 px-3 py-1 text-xs font-medium text-primary"
                                                    >
                                                        {category.name}
                                                    </span>
                                                ),
                                            )}
                                        </div>

                                        <span className="rounded-full bg-primary/10 px-3 py-1 text-xs font-medium text-primary">
                                            <Heart className="mr-1 inline size-3 fill-current" />
                                            Omiljeni
                                        </span>
                                    </div>

                                    <Link
                                        href={`/events/${event.slug}`}
                                        className="text-xl font-semibold tracking-tight transition-colors hover:text-primary"
                                    >
                                        {event.title}
                                    </Link>

                                    <p className="mt-2 line-clamp-3 text-sm leading-6 text-muted-foreground">
                                        {event.description}
                                    </p>

                                    <div className="my-5 space-y-3 border-y py-4 text-sm">
                                        <div className="flex items-start gap-3">
                                            <MapPin className="mt-0.5 size-4 shrink-0 text-muted-foreground" />
                                            <span>{event.location}</span>
                                        </div>

                                        <div className="flex items-start gap-3">
                                            <CalendarDays className="mt-0.5 size-4 shrink-0 text-muted-foreground" />
                                            <span>
                                                {new Date(
                                                    event.starts_at,
                                                ).toLocaleString('bs-BA')}
                                            </span>
                                        </div>

                                        <div className="flex items-start gap-3">
                                            <Users className="mt-0.5 size-4 shrink-0 text-muted-foreground" />
                                            <span>
                                                {event.registered_count} /{' '}
                                                {event.capacity} prijavljenih
                                            </span>
                                        </div>
                                    </div>

                                    <div className="mb-5">
                                        <p className="mb-2 text-sm font-medium">
                                            Slobodna mjesta:{' '}
                                            <span
                                                className={
                                                    availableSeats > 0
                                                        ? 'text-green-600'
                                                        : 'text-destructive'
                                                }
                                            >
                                                {availableSeats}
                                            </span>
                                        </p>

                                        <div className="h-2 overflow-hidden rounded-full bg-muted">
                                            <div
                                                className="h-full rounded-full bg-primary transition-all"
                                                style={{
                                                    width: `${
                                                        event.capacity > 0
                                                            ? Math.min(
                                                                  (event.registered_count /
                                                                      event.capacity) *
                                                                      100,
                                                                  100,
                                                              )
                                                            : 0
                                                    }%`,
                                                }}
                                            />
                                        </div>
                                    </div>

                                    {event.tags.length > 0 && (
                                        <div className="mb-5 flex flex-wrap gap-2">
                                            {event.tags.map((tag) => (
                                                <span
                                                    key={tag.id}
                                                    className="inline-flex items-center gap-1 rounded-md bg-muted px-2.5 py-1 text-xs text-muted-foreground"
                                                >
                                                    <TagIcon className="size-3" />
                                                    {tag.name}
                                                </span>
                                            ))}
                                        </div>
                                    )}

                                    <div className="mt-auto space-y-3">
                                        {event.is_registered ? (
                                            <button
                                                type="button"
                                                onClick={() =>
                                                    cancelRegistration(event.id)
                                                }
                                                className="w-full rounded-lg border border-destructive/50 px-4 py-2.5 text-sm font-medium text-destructive transition-colors hover:bg-destructive/10"
                                            >
                                                Otkaži prijavu
                                            </button>
                                        ) : event.can_register ? (
                                            <button
                                                type="button"
                                                onClick={() =>
                                                    registerForEvent(event.id)
                                                }
                                                className="w-full rounded-lg bg-primary px-4 py-2.5 text-sm font-medium text-primary-foreground transition-colors hover:bg-primary/90"
                                            >
                                                Prijavi se
                                            </button>
                                        ) : (
                                            <p className="rounded-lg bg-muted p-3 text-center text-sm font-medium text-muted-foreground">
                                                Prijave nisu dostupne
                                            </p>
                                        )}

                                        <button
                                            type="button"
                                            onClick={() =>
                                                removeFavorite(event.id)
                                            }
                                            className="w-full rounded-lg border border-destructive/50 px-4 py-2.5 text-sm font-medium text-destructive transition-colors hover:bg-destructive/10"
                                        >
                                            <Heart className="mr-2 inline size-4" />
                                            Ukloni iz omiljenih
                                        </button>
                                    </div>
                                </article>
                            );
                        })}
                    </div>
                )}
            </div>
        </>
    );
}
