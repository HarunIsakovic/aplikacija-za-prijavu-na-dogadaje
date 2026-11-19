import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
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
    slug: string;
    image: string | null;
    title: string;
    description: string;
    location: string;
    starts_at: string;
    ends_at: string | null;
    capacity: number;
    registered_count: number;
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
    filters: {
        search?: string;
    };
};

export default function EventsArchive({ events, filters }: Props) {
    const [search, setSearch] = useState(filters.search ?? '');

    function applySearch(searchValue: string) {
        router.get(
            '/events/archive',
            {
                search: searchValue || undefined,
            },
            {
                preserveState: true,
                preserveScroll: true,
                replace: true,
            },
        );
    }

    return (
        <>
            <Head title="Arhiva događaja" />

            <div className="mx-auto max-w-7xl space-y-8 p-4 sm:p-6">
                <div>
                    <p className="mb-2 text-sm font-medium text-primary">
                        PROŠLI DOGAĐAJI
                    </p>

                    <h1 className="text-3xl font-bold tracking-tight">
                        Arhiva događaja
                    </h1>

                    <p className="mt-2 text-muted-foreground">
                        Pregledajte završene događaje i pogledajte kako su
                        protekli.
                    </p>

                    <Link
                        href="/events"
                        className="mt-4 inline-block text-sm font-medium text-primary hover:underline"
                    >
                        ← Nazad na dostupne događaje
                    </Link>
                </div>

                <div className="rounded-xl border bg-card p-5 shadow-sm">
                    <h2 className="mb-3 text-lg font-semibold">
                        Pretražite arhivu
                    </h2>

                    <form
                        onSubmit={(event) => {
                            event.preventDefault();
                            applySearch(search);
                        }}
                        className="flex flex-col gap-3 sm:flex-row"
                    >
                        <input
                            type="search"
                            value={search}
                            onChange={(event) => setSearch(event.target.value)}
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

                <div className="flex flex-wrap items-center justify-between gap-2">
                    <h2 className="text-xl font-semibold">Završeni događaji</h2>

                    <span className="rounded-full bg-muted px-3 py-1 text-sm text-muted-foreground">
                        {events.total}{' '}
                        {events.total === 1 ? 'događaj' : 'događaja'}
                    </span>
                </div>

                {events.data.length === 0 ? (
                    <div className="rounded-xl border border-dashed p-10 text-center">
                        <CalendarDays className="mx-auto mb-4 size-10 text-muted-foreground" />

                        <h2 className="text-lg font-semibold">
                            Arhiva je prazna
                        </h2>

                        <p className="mt-2 text-muted-foreground">
                            Trenutno nema završenih događaja koji odgovaraju
                            vašoj pretrazi.
                        </p>
                    </div>
                ) : (
                    <div className="grid gap-5 md:grid-cols-2 lg:grid-cols-3">
                        {events.data.map((event) => (
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

                                <div className="mb-3 flex flex-wrap gap-2">
                                    {event.categories.map((category) => (
                                        <span
                                            key={category.id}
                                            className="rounded-full bg-primary/10 px-3 py-1 text-xs font-medium text-primary"
                                        >
                                            {category.name}
                                        </span>
                                    ))}

                                    <span className="rounded-full bg-muted px-3 py-1 text-xs font-medium text-muted-foreground">
                                        Završen
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
                                    <Link
                                        href={`/events/${event.slug}`}
                                        className="block w-full rounded-lg border px-4 py-2.5 text-center text-sm font-medium transition-colors hover:bg-muted"
                                    >
                                        Pogledaj detalje
                                    </Link>
                                </div>
                            </article>
                        ))}
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
