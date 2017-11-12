import { Head, router } from '@inertiajs/react';
import { CalendarDays, MapPin, Users, Tag as TagIcon } from 'lucide-react';

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
    events: EventData[];
    categories: Category[];
    tags: Tag[];
    filters: {
        category_id?: string;
        tag_id?: string;
    };
};

export default function EventsIndex({
    events,
    categories,
    tags,
    filters,
}: Props) {
    function applyFilters(categoryId: string, tagId: string) {
        router.get(
            '/events',
            {
                category_id: categoryId || undefined,
                tag_id: tagId || undefined,
            },
            {
                preserveState: true,
                preserveScroll: true,
            },
        );
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
            <Head title="Događaji" />

            <div className="mx-auto max-w-7xl space-y-8 p-4 sm:p-6">
                <div>
                    <p className="mb-2 text-sm font-medium text-primary">
                        PRONAĐITE NEŠTO ZA SEBE
                    </p>

                    <h1 className="text-3xl font-bold tracking-tight">
                        Događaji
                    </h1>

                    <p className="mt-2 text-muted-foreground">
                        Pregledajte dostupne događaje i pronađite nešto za sebe.
                    </p>
                </div>

                <div className="rounded-xl border bg-card p-5 shadow-sm">
                    <h2 className="mb-4 text-lg font-semibold">
                        Filtrirajte događaje
                    </h2>

                    <div className="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label
                                htmlFor="category"
                                className="mb-2 block text-sm font-medium"
                            >
                                Kategorija
                            </label>

                            <select
                                id="category"
                                value={filters.category_id ?? ''}
                                onChange={(event) =>
                                    applyFilters(
                                        event.target.value,
                                        filters.tag_id ?? '',
                                    )
                                }
                                className="w-full rounded-lg border bg-background px-3 py-2.5 text-sm outline-none transition-colors focus:border-primary focus:ring-2 focus:ring-primary/20"
                            >
                                <option value="">Sve kategorije</option>

                                {categories.map((category) => (
                                    <option
                                        key={category.id}
                                        value={category.id}
                                    >
                                        {category.name}
                                    </option>
                                ))}
                            </select>
                        </div>

                        <div>
                            <label
                                htmlFor="tag"
                                className="mb-2 block text-sm font-medium"
                            >
                                Tag
                            </label>

                            <select
                                id="tag"
                                value={filters.tag_id ?? ''}
                                onChange={(event) =>
                                    applyFilters(
                                        filters.category_id ?? '',
                                        event.target.value,
                                    )
                                }
                                className="w-full rounded-lg border bg-background px-3 py-2.5 text-sm outline-none transition-colors focus:border-primary focus:ring-2 focus:ring-primary/20"
                            >
                                <option value="">Svi tagovi</option>

                                {tags.map((tag) => (
                                    <option key={tag.id} value={tag.id}>
                                        {tag.name}
                                    </option>
                                ))}
                            </select>
                        </div>
                    </div>
                </div>

                <div className="flex items-center justify-between">
                    <h2 className="text-xl font-semibold">
                        Dostupni događaji
                    </h2>

                    <span className="rounded-full bg-muted px-3 py-1 text-sm text-muted-foreground">
                        {events.length}{' '}
                        {events.length === 1 ? 'događaj' : 'događaja'}
                    </span>
                </div>

                {events.length === 0 ? (
                    <div className="rounded-xl border border-dashed p-10 text-center">
                        <CalendarDays className="mx-auto mb-4 size-10 text-muted-foreground" />

                        <h2 className="text-lg font-semibold">
                            Nema dostupnih događaja
                        </h2>

                        <p className="mt-2 text-muted-foreground">
                            Trenutno nema događaja koji odgovaraju odabranim
                            filterima.
                        </p>
                    </div>
                ) : (
                    <div className="grid gap-5 md:grid-cols-2 lg:grid-cols-3">
                        {events.map((event) => {
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
                                    <div className="mb-3 flex items-start justify-between gap-3">
                                        <span className="rounded-full bg-primary/10 px-3 py-1 text-xs font-medium text-primary">
                                            {event.category.name}
                                        </span>

                                        {event.is_registered && (
                                            <span className="rounded-full bg-green-500/10 px-3 py-1 text-xs font-medium text-green-600">
                                                Prijavljeni ste
                                            </span>
                                        )}
                                    </div>

                                    <a
                                        href={`/events/${event.id}`}
                                        className="text-xl font-semibold tracking-tight transition-colors hover:text-primary"
                                    >
                                        {event.title}
                                    </a>

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
                                                    width: `${Math.min(
                                                        (event.registered_count /
                                                            event.capacity) *
                                                            100,
                                                        100,
                                                    )}%`,
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

                                    <div className="mt-auto">
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
                                        ) : availableSeats > 0 ? (
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
                                                Događaj je popunjen
                                            </p>
                                        )}
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
