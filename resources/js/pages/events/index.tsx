import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import {
    CalendarDays,
    Heart,
    MapPin,
    Users,
    Tag as TagIcon,
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
    available_seat_count: number;
    is_registered: boolean;
    is_favorite?: boolean;
    categories: Category[];
    tags: Tag[];
};

type PaginationLink = {
    url: string | null;
    label: string;
    active: boolean;
};

type PaginatedEvents = {
    data: EventData[];
    current_page: number;
    last_page: number;
    total: number;
    per_page: number;
    links: PaginationLink[];
};

type Props = {
    events: PaginatedEvents;
    categories: Category[];
    tags: Tag[];
    can_register: boolean;
    can_favorite: boolean;
    filters: {
        search?: string;
        category_ids?: (string | number)[];
        tag_ids?: (string | number)[];
    };
};

export default function EventsIndex({
    events,
    categories,
    tags,
    can_register,
    can_favorite,
    filters,
}: Props) {
    const [search, setSearch] = useState(filters.search ?? '');

    const [selectedCategories, setSelectedCategories] = useState<string[]>(
        (filters.category_ids ?? []).map(String),
    );

    const [selectedTags, setSelectedTags] = useState<string[]>(
        (filters.tag_ids ?? []).map(String),
    );

    function applyFilters(
        searchValue: string,
        categoryIds: string[],
        tagIds: string[],
    ) {
        router.get(
            '/events',
            {
                search: searchValue || undefined,
                category_ids: categoryIds.length ? categoryIds : undefined,
                tag_ids: tagIds.length ? tagIds : undefined,
            },
            {
                preserveState: true,
                preserveScroll: true,
                replace: true,
            },
        );
    }

    function toggleCategory(categoryId: string) {
        const updated = selectedCategories.includes(categoryId)
            ? selectedCategories.filter((id) => id !== categoryId)
            : [...selectedCategories, categoryId];

        setSelectedCategories(updated);
        applyFilters(search, updated, selectedTags);
    }

    function toggleTag(tagId: string) {
        const updated = selectedTags.includes(tagId)
            ? selectedTags.filter((id) => id !== tagId)
            : [...selectedTags, tagId];

        setSelectedTags(updated);
        applyFilters(search, selectedCategories, updated);
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

    function toggleFavorite(event: EventData) {
        if (event.is_favorite) {
            router.delete(`/events/${event.id}/favorite`, {
                preserveScroll: true,
            });
        } else {
            router.post(
                `/events/${event.id}/favorite`,
                {},
                {
                    preserveScroll: true,
                },
            );
        }
    }

    function clearFilters() {
        setSearch('');
        setSelectedCategories([]);
        setSelectedTags([]);

        applyFilters('', [], []);
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

                    <div className="mt-4 flex flex-wrap gap-4">
                        <Link
                            href="/events/archive"
                            className="inline-flex items-center gap-2 text-sm font-medium text-primary hover:underline"
                        >
                            <CalendarDays className="size-4" />
                            Pogledajte arhivu završenih događaja
                        </Link>

                        {can_favorite && (
                            <Link
                                href="/my-favorites"
                                className="inline-flex items-center gap-2 text-sm font-medium text-primary hover:underline"
                            >
                                <Heart className="size-4" />
                                Moji omiljeni događaji
                            </Link>
                        )}
                    </div>
                </div>

                <div className="space-y-5 rounded-xl border bg-card p-5 shadow-sm">
                    <div>
                        <h2 className="mb-3 text-lg font-semibold">
                            Pretražite događaje
                        </h2>

                        <form
                            onSubmit={(event) => {
                                event.preventDefault();
                                applyFilters(
                                    search,
                                    selectedCategories,
                                    selectedTags,
                                );
                            }}
                            className="flex flex-col gap-3 sm:flex-row"
                        >
                            <input
                                type="search"
                                value={search}
                                onChange={(event) =>
                                    setSearch(event.target.value)
                                }
                                placeholder="Naziv, opis ili lokacija događaja..."
                                className="w-full rounded-lg border bg-background px-3 py-2.5 text-sm outline-none focus:border-primary focus:ring-2 focus:ring-primary/20"
                            />

                            <button
                                type="submit"
                                className="rounded-lg bg-primary px-5 py-2.5 text-sm font-medium text-primary-foreground transition-colors hover:bg-primary/90"
                            >
                                Pretraži
                            </button>
                        </form>
                    </div>

                    <div className="grid gap-6 sm:grid-cols-2">
                        <div>
                            <h3 className="mb-3 text-sm font-semibold">
                                Kategorije
                            </h3>

                            <div className="space-y-2">
                                {categories.map((category) => (
                                    <label
                                        key={category.id}
                                        className="flex cursor-pointer items-center gap-2 text-sm"
                                    >
                                        <input
                                            type="checkbox"
                                            checked={selectedCategories.includes(
                                                String(category.id),
                                            )}
                                            onChange={() =>
                                                toggleCategory(
                                                    String(category.id),
                                                )
                                            }
                                            className="size-4 rounded border-input accent-primary"
                                        />

                                        {category.name}
                                    </label>
                                ))}
                            </div>
                        </div>

                        <div>
                            <h3 className="mb-3 text-sm font-semibold">
                                Tagovi
                            </h3>

                            <div className="space-y-2">
                                {tags.map((tag) => (
                                    <label
                                        key={tag.id}
                                        className="flex cursor-pointer items-center gap-2 text-sm"
                                    >
                                        <input
                                            type="checkbox"
                                            checked={selectedTags.includes(
                                                String(tag.id),
                                            )}
                                            onChange={() =>
                                                toggleTag(String(tag.id))
                                            }
                                            className="size-4 rounded border-input accent-primary"
                                        />

                                        {tag.name}
                                    </label>
                                ))}
                            </div>
                        </div>
                    </div>

                    <div>
                        <button
                            type="button"
                            onClick={clearFilters}
                            className="text-sm font-medium text-primary hover:underline"
                        >
                            Poništi sve filtere
                        </button>
                    </div>
                </div>

                <div className="flex flex-wrap items-center justify-between gap-2">
                    <h2 className="text-xl font-semibold">Dostupni događaji</h2>

                    <span className="rounded-full bg-muted px-3 py-1 text-sm text-muted-foreground">
                        {events.total}{' '}
                        {events.total === 1 ? 'događaj' : 'događaja'}
                    </span>
                </div>

                {events.data.length === 0 ? (
                    <div className="rounded-xl border border-dashed p-10 text-center">
                        <CalendarDays className="mx-auto mb-4 size-10 text-muted-foreground" />

                        <h2 className="text-lg font-semibold">
                            Nema dostupnih događaja
                        </h2>

                        <p className="mt-2 text-muted-foreground">
                            Trenutno nema događaja koji odgovaraju vašoj
                            pretrazi i odabranim filterima.
                        </p>
                    </div>
                ) : (
                    <div className="grid gap-5 md:grid-cols-2 lg:grid-cols-3">
                        {events.data.map((event) => {
                            const availableSeats = event.available_seat_count;

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

                                        {event.is_registered && (
                                            <span className="rounded-full bg-green-500/10 px-3 py-1 text-xs font-medium text-green-600">
                                                Prijavljeni ste
                                            </span>
                                        )}
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
                                                                  ((event.capacity -
                                                                      event.available_seat_count) /
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
                                        {can_favorite && (
                                            <button
                                                type="button"
                                                onClick={() =>
                                                    toggleFavorite(event)
                                                }
                                                className={`w-full rounded-lg border px-4 py-2.5 text-sm font-medium transition-colors ${
                                                    event.is_favorite
                                                        ? 'border-primary/50 bg-primary/10 text-primary hover:bg-primary/20'
                                                        : 'hover:bg-muted'
                                                }`}
                                            >
                                                <Heart
                                                    className={`mr-2 inline size-4 ${
                                                        event.is_favorite
                                                            ? 'fill-current'
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
                                                className="block w-full rounded-lg bg-primary px-4 py-2.5 text-center text-sm font-medium text-primary-foreground transition-colors hover:bg-primary/90"
                                            >
                                                Prijavite se za učešće
                                            </Link>
                                        ) : event.is_registered ? (
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

                {events.last_page > 1 && (
                    <div className="flex flex-wrap items-center justify-center gap-2">
                        {events.links.map((link, index) => (
                            <button
                                key={index}
                                type="button"
                                disabled={!link.url}
                                onClick={() => {
                                    if (link.url) {
                                        router.get(
                                            link.url,
                                            {},
                                            {
                                                preserveState: true,
                                                preserveScroll: true,
                                            },
                                        );
                                    }
                                }}
                                className={`rounded-lg border px-3 py-2 text-sm transition-colors ${
                                    link.active
                                        ? 'border-primary bg-primary text-primary-foreground'
                                        : 'bg-background hover:bg-muted'
                                } disabled:cursor-not-allowed disabled:opacity-40`}
                            >
                                {link.label
                                    .replace('&laquo;', '«')
                                    .replace('&raquo;', '»')}
                            </button>
                        ))}
                    </div>
                )}

                {events.total > 0 && (
                    <p className="text-center text-sm text-muted-foreground">
                        Prikazano {events.data.length} od ukupno {events.total}{' '}
                        događaja. Stranica {events.current_page} od{' '}
                        {events.last_page}.
                    </p>
                )}
            </div>
        </>
    );
}
