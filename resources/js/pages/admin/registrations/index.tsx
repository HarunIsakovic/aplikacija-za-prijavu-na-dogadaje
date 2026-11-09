import { Head, router } from '@inertiajs/react';

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

            <div className="mx-auto max-w-7xl space-y-6 p-6">
                <div>
                    <h1 className="text-3xl font-bold">
                        Prijavljeni korisnici
                    </h1>

                    <h2 className="mt-2 text-xl font-semibold">
                        {event.title}
                    </h2>

                    <div className="mt-2 space-y-1 text-sm text-muted-foreground">
                        <p>Lokacija: {event.location}</p>

                        <p>
                            Početak:{' '}
                            {new Date(event.starts_at).toLocaleString('bs-BA')}
                        </p>
                    </div>

                    <p className="mt-3 text-sm">
                        Ukupno prijavljenih: {activeRegistrations.length}
                    </p>
                </div>

                {registrations.length === 0 ? (
                    <div className="rounded-lg border p-8 text-center">
                        <h2 className="text-lg font-semibold">
                            Nema prijavljenih korisnika
                        </h2>

                        <p className="mt-2 text-muted-foreground">
                            Na ovaj događaj se još niko nije prijavio.
                        </p>
                    </div>
                ) : (
                    <div className="overflow-x-auto rounded-lg border">
                        <table className="w-full text-left text-sm">
                            <thead className="border-b bg-muted/50">
                                <tr>
                                    <th className="p-4">Korisnik</th>
                                    <th className="p-4">Email</th>
                                    <th className="p-4">Status prijave</th>
                                    <th className="p-4">Prisustvo</th>
                                    <th className="p-4">Akcija</th>
                                </tr>
                            </thead>

                            <tbody>
                                {registrations.map((registration) => (
                                    <tr
                                        key={registration.id}
                                        className="border-b last:border-0"
                                    >
                                        <td className="p-4 font-medium">
                                            {registration.user.name}
                                        </td>

                                        <td className="p-4">
                                            {registration.user.email}
                                        </td>

                                        <td className="p-4">
                                            {registration.status ===
                                            'registered'
                                                ? 'Prijavljen'
                                                : 'Otkazan'}
                                        </td>

                                        <td className="p-4">
                                            {registration.status ===
                                            'registered'
                                                ? registration.attended
                                                    ? 'Prisustvovao'
                                                    : 'Nije evidentirano'
                                                : '—'}
                                        </td>

                                        <td className="p-4">
                                            {registration.status ===
                                                'registered' && (
                                                <button
                                                    type="button"
                                                    onClick={() =>
                                                        toggleAttendance(
                                                            registration,
                                                        )
                                                    }
                                                    className="rounded-md border px-3 py-2 text-sm font-medium hover:bg-muted"
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
        </>
    );
}
