import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import { CalendarDays, MapPin, Users, CheckCircle2 } from 'lucide-react';
import { Input } from '@/components/ui/input';
import { Button } from '@/components/ui/button';

type EventData = {
    id: number;
    title: string;
    location: string;
    starts_at: string;
};

type User = {
    id: number;
    name: string;
    email: string;
};

type Registration = {
    id: number;
    status: string;
    attended: boolean;
    user: User;
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
    total: number;
    links: PaginationLink[];
};

type Props = {
    event: EventData;
    registrations: PaginatedRegistrations;
    filters: {
        search?: string;
    };
    activeRegistrations: number;
    attendedCount: number;
};

export default function EventRegistrationsIndex({
    event,
    registrations,
    filters,
    activeRegistrations,
    attendedCount,
}: Props) {
    const [search, setSearch] = useState(filters.search ?? '');

    function handleSearch(value: string) {
        setSearch(value);

        router.get(
            `/admin/events/${event.id}/registrations`,
            { search: value || undefined },
            {
                preserveState: true,
                preserveScroll: true,
                replace: true,
            },
        );
    }

    function toggleAttendance(registration: Registration) {
        router.put(
            `/admin/registrations/${registration.id}/attendance`,
            {
                attended: !registration.attended,
            },
            {
                preserveScroll: true,
            },
        );
    }

    return (
        <>
            <Head title={`Prijavljeni korisnici - ${event.title}`} />

            <div className="mx-auto max-w-7xl space-y-8 p-4 sm:p-6">
                <div>
                    <p className="mb-2 text-sm font-medium text-primary">
                        ADMINISTRACIJA DOGAĐAJA
                    </p>

                    <h1 className="text-3xl font-bold tracking-tight">
                        Prijavljeni korisnici
                    </h1>

                    <p className="mt-2 text-muted-foreground">
                        Pregledajte prijave i evidentirajte prisustvo korisnika.
                    </p>
                </div>

                <div className="rounded-xl border bg-card p-6 shadow-sm">
                    <h2 className="text-xl font-semibold">{event.title}</h2>

                    <div className="mt-4 grid gap-4 text-sm sm:grid-cols-2">
                        <div className="flex items-center gap-3">
                            <MapPin className="size-5 shrink-0 text-muted-foreground" />
                            <span>{event.location}</span>
                        </div>

                        <div className="flex items-center gap-3">
                            <CalendarDays className="size-5 shrink-0 text-muted-foreground" />
                            <span>
                                {new Date(event.starts_at).toLocaleString(
                                    'bs-BA',
                                )}
                            </span>
                        </div>
                    </div>
                </div>

                <div className="grid gap-4 sm:grid-cols-2">
                    <div className="rounded-xl border bg-card p-5">
                        <div className="flex items-center gap-3">
                            <div className="rounded-lg bg-primary/10 p-3 text-primary">
                                <Users className="size-5" />
                            </div>

                            <div>
                                <p className="text-sm text-muted-foreground">
                                    Aktivne prijave
                                </p>

                                <p className="text-2xl font-bold tabular-nums">
                                    {activeRegistrations}
                                </p>
                            </div>
                        </div>
                    </div>

                    <div className="rounded-xl border bg-card p-5">
                        <div className="flex items-center gap-3">
                            <div className="rounded-lg bg-green-500/10 p-3 text-green-600">
                                <CheckCircle2 className="size-5" />
                            </div>

                            <div>
                                <p className="text-sm text-muted-foreground">
                                    Evidentirano prisustvo
                                </p>

                                <p className="text-2xl font-bold tabular-nums">
                                    {attendedCount}
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <div className="space-y-4">
                    <div>
                        <h2 className="text-xl font-semibold">Lista prijava</h2>

                        <p className="mt-1 text-sm text-muted-foreground">
                            Ukupno prijava: {registrations.total}
                        </p>
                    </div>

                    <div className="max-w-md">
                        <Input
                            type="search"
                            placeholder="Pretraži po imenu ili emailu..."
                            value={search}
                            onChange={(event) =>
                                handleSearch(event.target.value)
                            }
                        />
                    </div>

                    {registrations.data.length === 0 ? (
                        <div className="rounded-xl border border-dashed p-10 text-center">
                            <Users className="mx-auto mb-4 size-10 text-muted-foreground" />

                            <h3 className="text-lg font-semibold">
                                {search
                                    ? 'Nema rezultata pretrage'
                                    : 'Nema prijavljenih korisnika'}
                            </h3>

                            <p className="mt-2 text-muted-foreground">
                                {search
                                    ? 'Pokušajte pretražiti drugim pojmom.'
                                    : 'Na ovaj događaj se još niko nije prijavio.'}
                            </p>
                        </div>
                    ) : (
                        <div className="overflow-x-auto rounded-xl border bg-card">
                            <table className="w-full text-left text-sm">
                                <thead className="border-b bg-muted/50">
                                    <tr>
                                        <th className="px-5 py-4 font-semibold">
                                            Korisnik
                                        </th>

                                        <th className="px-5 py-4 font-semibold">
                                            Email
                                        </th>

                                        <th className="px-5 py-4 font-semibold">
                                            Status prijave
                                        </th>

                                        <th className="px-5 py-4 font-semibold">
                                            Prisustvo
                                        </th>

                                        <th className="px-5 py-4 font-semibold">
                                            Akcija
                                        </th>
                                    </tr>
                                </thead>

                                <tbody className="divide-y">
                                    {registrations.data.map((registration) => (
                                        <tr
                                            key={registration.id}
                                            className="transition-colors hover:bg-muted/30"
                                        >
                                            <td className="px-5 py-4 font-medium">
                                                {registration.user.name}
                                            </td>

                                            <td className="px-5 py-4 text-muted-foreground">
                                                {registration.user.email}
                                            </td>

                                            <td className="px-5 py-4">
                                                <span
                                                    className={`rounded-full px-3 py-1 text-xs font-medium ${
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
                                            </td>

                                            <td className="px-5 py-4">
                                                {registration.status ===
                                                'registered' ? (
                                                    <span
                                                        className={
                                                            registration.attended
                                                                ? 'font-medium text-green-600'
                                                                : 'text-muted-foreground'
                                                        }
                                                    >
                                                        {registration.attended
                                                            ? 'Prisustvovao'
                                                            : 'Nije evidentirano'}
                                                    </span>
                                                ) : (
                                                    '—'
                                                )}
                                            </td>

                                            <td className="px-5 py-4">
                                                {registration.status ===
                                                    'registered' && (
                                                    <button
                                                        type="button"
                                                        onClick={() =>
                                                            toggleAttendance(
                                                                registration,
                                                            )
                                                        }
                                                        className={`rounded-lg border px-3 py-2 text-xs font-medium transition-colors ${
                                                            registration.attended
                                                                ? 'border-destructive/40 text-destructive hover:bg-destructive/10'
                                                                : 'border-green-600/40 text-green-600 hover:bg-green-500/10'
                                                        }`}
                                                    >
                                                        {registration.attended
                                                            ? 'Poništi prisustvo'
                                                            : 'Označi prisustvo'}
                                                    </button>
                                                )}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}

                    {registrations.last_page > 1 && (
                        <div className="flex flex-wrap gap-2">
                            {registrations.links.map((link, index) => (
                                <Button
                                    key={index}
                                    type="button"
                                    variant={
                                        link.active ? 'default' : 'outline'
                                    }
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
                                >
                                    <span
                                        dangerouslySetInnerHTML={{
                                            __html: link.label,
                                        }}
                                    />
                                </Button>
                            ))}
                        </div>
                    )}
                </div>
            </div>
        </>
    );
}
