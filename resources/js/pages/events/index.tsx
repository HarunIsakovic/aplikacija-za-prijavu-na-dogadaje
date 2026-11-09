import { Head, router } from '@inertiajs/react';

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
        router.post(`/events/${eventId}/registrations`, {}, {
            preserveScroll: true,
        });
    }

    function cancelRegistration(eventId: number) {
        router.delete(`/events/${eventId}/registrations`, {
            preserveScroll: true,
        });
    }

    return (
        <>
            <Head title="Događaji" />

            <div className="mx-auto max-w-7xl space-y-6 p-6">
                <div>
                    <h1 className="text-3xl font-bold">Događaji</h1>
                    <p className="mt-2 text-muted-foreground">
                        Pregledajte dostupne događaje i pronađite nešto za sebe.
                    </p>
                </div>

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
                            className="w-full rounded-md border bg-background p-2"
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
                            className="w-full rounded-md border bg-background p-2"
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

                {events.length === 0 ? (
                    <div className="rounded-lg border p-8 text-center">
                        <h2 className="text-lg font-semibold">
                            Nema dostupnih događaja
                        </h2>
                        <p className="mt-2 text-muted-foreground">
                            Trenutno nema događaja koji odgovaraju odabranim
                            filterima.
                        </p>
                    </div>
                ) : (
                    <div className="grid gap-6 md:grid-cols-2 lg:grid-cols-3">
                        {events.map((event) => {
                            const availableSeats =
                                event.capacity - event.registered_count;

                            return (
                                <article
                                    key={event.id}
                                    className="rounded-lg border p-5 shadow-sm"
                                >
                                    <a
                                        href={`/events/${event.id}`}
                                        className="text-xl font-semibold hover:underline"
                                    >
                                        {event.title}
                                    </a>

                                    <p className="mt-2 text-sm text-muted-foreground">
                                        {event.description}
                                    </p>

                                    <div className="mt-4 space-y-2 text-sm">
                                        <p>
                                            <strong>Kategorija:</strong>{' '}
                                            {event.category.name}
                                        </p>

                                        <p>
                                            <strong>Lokacija:</strong>{' '}
                                            {event.location}
                                        </p>

                                        <p>
                                            <strong>Početak:</strong>{' '}
                                            {new Date(
                                                event.starts_at,
                                            ).toLocaleString('bs-BA')}
                                        </p>

                                        <p>
                                            <strong>Kapacitet:</strong>{' '}
                                            {event.capacity} mjesta
                                        </p>

                                        <p>
                                            <strong>Prijavljeno:</strong>{' '}
                                            {event.registered_count}
                                        </p>

                                        <p>
                                            <strong>Slobodna mjesta:</strong>{' '}
                                            {availableSeats}
                                        </p>
                                    </div>

                                    {event.tags.length > 0 && (
                                        <div className="mt-4 flex flex-wrap gap-2">
                                            {event.tags.map((tag) => (
                                                <span
                                                    key={tag.id}
                                                    className="rounded-full bg-muted px-3 py-1 text-xs"
                                                >
                                                    {tag.name}
                                                </span>
                                            ))}
                                        </div>
                                    )}

                                    <div className="mt-5">
                                        {event.is_registered ? (
                                            <button
                                                type="button"
                                                onClick={() =>
                                                    cancelRegistration(event.id)
                                                }
                                                className="w-full rounded-md border border-destructive px-4 py-2 text-sm font-medium text-destructive hover:bg-destructive/10"
                                            >
                                                Otkaži prijavu
                                            </button>
                                        ) : availableSeats > 0 ? (
                                            <button
                                                type="button"
                                                onClick={() =>
                                                    registerForEvent(event.id)
                                                }
                                                className="w-full rounded-md bg-primary px-4 py-2 text-sm font-medium text-primary-foreground hover:bg-primary/90"
                                            >
                                                Prijavi se
                                            </button>
                                        ) : (
                                            <p className="rounded-md bg-muted p-3 text-center text-sm font-medium">
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
