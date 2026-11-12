import { Head, router } from '@inertiajs/react';
import { CalendarDays, MapPin, Users, CheckCircle2 } from 'lucide-react';

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

type Props = {
    event: EventData;
    registrations: Registration[];
};

export default function EventRegistrationsIndex({
    event,
    registrations,
}: Props) {
    const activeRegistrations = registrations.filter(
        (registration) => registration.status === 'registered',
    );

    const attendedCount = activeRegistrations.filter(
        (registration) => registration.attended,
    ).length;

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
                                    {activeRegistrations.length}
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
                    <h2 className="text-xl font-semibold">Lista prijava</h2>

                    {registrations.length === 0 ? (
                        <div className="rounded-xl border border-dashed p-10 text-center">
                            <Users className="mx-auto mb-4 size-10 text-muted-foreground" />

                            <h3 className="text-lg font-semibold">
                                Nema prijavljenih korisnika
                            </h3>

                            <p className="mt-2 text-muted-foreground">
                                Na ovaj događaj se još niko nije prijavio.
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
                                    {registrations.map((registration) => (
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
                </div>
            </div>
        </>
    );
}
