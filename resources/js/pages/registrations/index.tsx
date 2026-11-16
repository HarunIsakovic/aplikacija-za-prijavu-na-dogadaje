import { Head, router } from '@inertiajs/react';
import { CalendarDays, MapPin, ClipboardList } from 'lucide-react';
import { useState } from 'react';

type EventData = {
    id: number;
    title: string;
    location: string;
    starts_at: string;
    ends_at: string | null;
    category: {
        id: number;
        name: string;
    };
};

type Registration = {
    id: number;
    status: string;
    attended: boolean;
    event: EventData;
};

type PaginationLink = {
    url: string | null;
    label: string;
    active: boolean;
};

type PaginatedRegistrations = {
    data: Registration[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    links: PaginationLink[];
};

type Props = {
    registrations: PaginatedRegistrations;
    filters: {
        search?: string;
    };
};

export default function RegistrationsIndex({ registrations, filters }: Props) {
    const [search, setSearch] = useState(filters.search ?? '');

    function handleSearch(value: string) {
        setSearch(value);

        router.get(
            '/registrations',
            { search: value || undefined },
            {
                preserveState: true,
                preserveScroll: true,
                replace: true,
            },
        );
    }

    return (
        <>
            <Head title="Moje prijave" />

            <div className="mx-auto max-w-7xl space-y-8 p-4 sm:p-6">
                <div>
                    <p className="mb-2 text-sm font-medium text-primary">
                        VAŠI DOGAĐAJI
                    </p>

                    <h1 className="text-3xl font-bold tracking-tight">
                        Moje prijave
                    </h1>

                    <p className="mt-2 text-muted-foreground">
                        Pregledajte događaje na koje ste se prijavili i pratite
                        status svojih prijava.
                    </p>
                </div>

                <div className="space-y-4">
                    <div className="flex flex-col justify-between gap-3 sm:flex-row sm:items-center">
                        <h2 className="text-xl font-semibold">
                            Pregled prijava
                        </h2>

                        <span className="w-fit rounded-full bg-muted px-3 py-1 text-sm text-muted-foreground">
                            {registrations.total}{' '}
                            {registrations.total === 1 ? 'prijava' : 'prijava'}
                        </span>
                    </div>

                    <input
                        type="search"
                        value={search}
                        onChange={(event) => handleSearch(event.target.value)}
                        placeholder="Pretraži po nazivu događaja..."
                        className="w-full rounded-md border border-input bg-background px-3 py-2 text-sm shadow-sm outline-none placeholder:text-muted-foreground focus-visible:ring-2 focus-visible:ring-ring sm:max-w-md"
                    />
                </div>

                {registrations.data.length === 0 ? (
                    <div className="rounded-xl border border-dashed p-10 text-center">
                        <ClipboardList className="mx-auto mb-4 size-12 text-muted-foreground" />

                        <h2 className="text-lg font-semibold">
                            {search
                                ? 'Nema rezultata pretrage'
                                : 'Nemate prijava'}
                        </h2>

                        <p className="mt-2 text-muted-foreground">
                            {search
                                ? 'Nijedna prijava ne odgovara unesenom nazivu događaja.'
                                : 'Još se niste prijavili ni na jedan događaj.'}
                        </p>
                    </div>
                ) : (
                    <>
                        <div className="grid gap-4 md:grid-cols-2">
                            {registrations.data.map((registration) => (
                                <article
                                    key={registration.id}
                                    className="rounded-xl border bg-card p-5 shadow-sm transition-shadow hover:shadow-md"
                                >
                                    <div className="flex items-start justify-between gap-3">
                                        <h2 className="text-lg font-semibold tracking-tight">
                                            {registration.event.title}
                                        </h2>

                                        <span
                                            className={`shrink-0 rounded-full px-3 py-1 text-xs font-medium ${
                                                registration.status ===
                                                'registered'
                                                    ? 'bg-green-500/10 text-green-600'
                                                    : 'bg-muted text-muted-foreground'
                                            }`}
                                        >
                                            {registration.status ===
                                            'registered'
                                                ? 'Prijavljen'
                                                : 'Otkazan'}
                                        </span>
                                    </div>

                                    <div className="mt-4 space-y-3 border-y py-4 text-sm">
                                        <div className="flex items-center gap-3">
                                            <ClipboardList className="size-4 shrink-0 text-muted-foreground" />
                                            <span>
                                                {
                                                    registration.event.category
                                                        .name
                                                }
                                            </span>
                                        </div>

                                        <div className="flex items-center gap-3">
                                            <MapPin className="size-4 shrink-0 text-muted-foreground" />
                                            <span>
                                                {registration.event.location}
                                            </span>
                                        </div>

                                        <div className="flex items-center gap-3">
                                            <CalendarDays className="size-4 shrink-0 text-muted-foreground" />
                                            <span>
                                                {new Date(
                                                    registration.event
                                                        .starts_at,
                                                ).toLocaleString('bs-BA')}
                                            </span>
                                        </div>
                                    </div>

                                    {registration.status === 'registered' && (
                                        <div className="mt-4">
                                            <p className="text-sm text-muted-foreground">
                                                Status prisustva
                                            </p>

                                            <p className="mt-1 text-sm font-medium">
                                                {registration.attended
                                                    ? 'Prisustvovali ste događaju'
                                                    : 'Prisustvo još nije evidentirano'}
                                            </p>
                                        </div>
                                    )}
                                </article>
                            ))}
                        </div>

                        {registrations.last_page > 1 && (
                            <div className="flex flex-wrap items-center justify-center gap-2 pt-4">
                                {registrations.links.map((link, index) => (
                                    <button
                                        key={index}
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
                                        dangerouslySetInnerHTML={{
                                            __html: link.label,
                                        }}
                                        className={`rounded-md border px-3 py-2 text-sm ${
                                            link.active
                                                ? 'border-primary bg-primary text-primary-foreground'
                                                : 'bg-background hover:bg-muted'
                                        } disabled:pointer-events-none disabled:opacity-50`}
                                    />
                                ))}
                            </div>
                        )}
                    </>
                )}
            </div>
        </>
    );
}
