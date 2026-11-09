import { Head } from '@inertiajs/react';

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

type Props = {
    registrations: Registration[];
};

export default function RegistrationsIndex({
    registrations,
}: Props) {
    return (
        <>
            <Head title="Moje prijave" />

            <div className="mx-auto max-w-7xl space-y-6 p-6">
                <div>
                    <h1 className="text-3xl font-bold">Moje prijave</h1>

                    <p className="mt-2 text-muted-foreground">
                        Pregledajte događaje na koje ste se prijavili.
                    </p>
                </div>

                {registrations.length === 0 ? (
                    <div className="rounded-lg border p-8 text-center">
                        <h2 className="text-lg font-semibold">
                            Nemate prijava
                        </h2>

                        <p className="mt-2 text-muted-foreground">
                            Još se niste prijavili ni na jedan događaj.
                        </p>
                    </div>
                ) : (
                    <div className="space-y-4">
                        {registrations.map((registration) => (
                            <article
                                key={registration.id}
                                className="rounded-lg border p-5 shadow-sm"
                            >
                                <div className="flex flex-col justify-between gap-4 sm:flex-row">
                                    <div>
                                        <h2 className="text-xl font-semibold">
                                            {registration.event.title}
                                        </h2>

                                        <div className="mt-3 space-y-2 text-sm">
                                            <p>
                                                <strong>Kategorija:</strong>{' '}
                                                {registration.event.category.name}
                                            </p>

                                            <p>
                                                <strong>Lokacija:</strong>{' '}
                                                {registration.event.location}
                                            </p>

                                            <p>
                                                <strong>Početak:</strong>{' '}
                                                {new Date(
                                                    registration.event.starts_at,
                                                ).toLocaleString('bs-BA')}
                                            </p>
                                        </div>
                                    </div>

                                    <div className="flex flex-col gap-2 sm:items-end">
                                        <span
                                            className={`rounded-full px-3 py-1 text-sm font-medium ${
                                                registration.status === 'registered'
                                                    ? 'bg-green-100 text-green-800'
                                                    : 'bg-muted text-muted-foreground'
                                            }`}
                                        >
                                            {registration.status === 'registered'
                                                ? 'Prijavljen'
                                                : 'Otkazan'}
                                        </span>

                                        {registration.status === 'registered' && (
                                            <span className="text-sm text-muted-foreground">
                                                {registration.attended
                                                    ? 'Prisustvovao'
                                                    : 'Prisustvo nije evidentirano'}
                                            </span>
                                        )}
                                    </div>
                                </div>
                            </article>
                        ))}
                    </div>
                )}
            </div>
        </>
    );
}
