import { Head, router } from '@inertiajs/react';
import {
    CalendarDays,
    ClipboardList,
    Download,
    MapPin,
    Ticket,
} from 'lucide-react';
import { useState } from 'react';

type TicketData = {
    ticket_uuid: string;
    event_title: string;
    event_location: string;
    event_starts_at: string | null;
    event_ends_at: string | null;
    categories: string[];
    seat: string;
    price: string;
};

type Props = {
    upcomingTickets: TicketData[];
    pastTickets: TicketData[];
    filters: {
        search?: string;
    };
};

function TicketCard({ ticket }: { ticket: TicketData }) {
    return (
        <article className="rounded-xl border bg-card p-5 shadow-sm transition-shadow hover:shadow-md">
            <div className="flex items-start justify-between gap-3">
                <div>
                    <p className="mb-2 text-xs font-medium tracking-wider text-primary uppercase">
                        Digitalna ulaznica
                    </p>

                    <h3 className="text-lg font-semibold tracking-tight">
                        {ticket.event_title}
                    </h3>
                </div>

                <Ticket className="size-6 shrink-0 text-primary" />
            </div>

            <div className="mt-4 space-y-3 border-y py-4 text-sm">
                <div className="flex items-center gap-3">
                    <ClipboardList className="size-4 shrink-0 text-muted-foreground" />
                    <span>
                        {ticket.categories.join(', ') || 'Nema kategorija'}
                    </span>
                </div>

                <div className="flex items-center gap-3">
                    <MapPin className="size-4 shrink-0 text-muted-foreground" />
                    <span>
                        {ticket.event_location || 'Lokacija nije navedena'}
                    </span>
                </div>

                <div className="flex items-center gap-3">
                    <CalendarDays className="size-4 shrink-0 text-muted-foreground" />
                    <span>
                        {ticket.event_starts_at ?? 'Datum nije naveden'}
                    </span>
                </div>
            </div>

            <div className="mt-4 grid grid-cols-2 gap-3">
                <div>
                    <p className="text-xs text-muted-foreground">Sjedište</p>
                    <p className="mt-1 text-sm font-semibold">{ticket.seat}</p>
                </div>

                <div>
                    <p className="text-xs text-muted-foreground">Cijena</p>
                    <p className="mt-1 text-sm font-semibold">{ticket.price}</p>
                </div>
            </div>

            <a
                href={`/tickets/${encodeURIComponent(ticket.ticket_uuid)}/pdf`}
                className="mt-5 flex w-full items-center justify-center gap-2 rounded-md bg-primary px-4 py-2.5 text-sm font-medium text-primary-foreground transition-opacity hover:opacity-90"
            >
                <Download className="size-4" />
                Preuzmi PDF kartu
            </a>
        </article>
    );
}

export default function TicketsIndex({
    upcomingTickets,
    pastTickets,
    filters,
}: Props) {
    const [search, setSearch] = useState(filters.search ?? '');

    function handleSearch(value: string) {
        setSearch(value);

        router.get(
            '/my-tickets',
            { search: value || undefined },
            {
                preserveState: true,
                preserveScroll: true,
                replace: true,
            },
        );
    }

    const totalTickets = upcomingTickets.length + pastTickets.length;

    return (
        <>
            <Head title="Moje karte" />

            <div className="mx-auto max-w-7xl space-y-8 p-4 sm:p-6">
                <div>
                    <p className="mb-2 text-sm font-medium text-primary">
                        VAŠE ULAZNICE
                    </p>

                    <h1 className="text-3xl font-bold tracking-tight">
                        Moje karte
                    </h1>

                    <p className="mt-2 text-muted-foreground">
                        Pregledajte svoje ulaznice i preuzmite PDF kartu sa
                        jedinstvenim QR kodom za svaki događaj.
                    </p>
                </div>

                <div className="space-y-4">
                    <div className="flex flex-col justify-between gap-3 sm:flex-row sm:items-center">
                        <h2 className="text-xl font-semibold">
                            Pregled ulaznica
                        </h2>

                        <span className="w-fit rounded-full bg-muted px-3 py-1 text-sm text-muted-foreground">
                            {totalTickets}{' '}
                            {totalTickets === 1 ? 'karta' : 'karata'}
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

                {totalTickets === 0 ? (
                    <div className="rounded-xl border border-dashed p-10 text-center">
                        <Ticket className="mx-auto mb-4 size-12 text-muted-foreground" />

                        <h2 className="text-lg font-semibold">
                            {search
                                ? 'Nema rezultata pretrage'
                                : 'Nemate ulaznica'}
                        </h2>

                        <p className="mt-2 text-muted-foreground">
                            {search
                                ? 'Nijedna ulaznica ne odgovara unesenom nazivu događaja.'
                                : 'Kada rezervišete ulaznice za događaje, one će se pojaviti ovdje.'}
                        </p>
                    </div>
                ) : (
                    <div className="space-y-10">
                        <section className="space-y-4">
                            <div className="flex items-center justify-between">
                                <h2 className="text-xl font-semibold">
                                    Predstojeći događaji
                                </h2>

                                <span className="rounded-full bg-green-500/10 px-3 py-1 text-sm font-medium text-green-600">
                                    {upcomingTickets.length}
                                </span>
                            </div>

                            {upcomingTickets.length === 0 ? (
                                <p className="rounded-xl border border-dashed p-6 text-center text-sm text-muted-foreground">
                                    Nemate ulaznica za predstojeće događaje.
                                </p>
                            ) : (
                                <div className="grid gap-4 md:grid-cols-2">
                                    {upcomingTickets.map((ticket) => (
                                        <TicketCard
                                            key={ticket.ticket_uuid}
                                            ticket={ticket}
                                        />
                                    ))}
                                </div>
                            )}
                        </section>

                        <section className="space-y-4">
                            <div className="flex items-center justify-between">
                                <h2 className="text-xl font-semibold">
                                    Prošli događaji
                                </h2>

                                <span className="rounded-full bg-muted px-3 py-1 text-sm text-muted-foreground">
                                    {pastTickets.length}
                                </span>
                            </div>

                            {pastTickets.length === 0 ? (
                                <p className="rounded-xl border border-dashed p-6 text-center text-sm text-muted-foreground">
                                    Nemate ulaznica za prošle događaje.
                                </p>
                            ) : (
                                <div className="grid gap-4 md:grid-cols-2">
                                    {pastTickets.map((ticket) => (
                                        <TicketCard
                                            key={ticket.ticket_uuid}
                                            ticket={ticket}
                                        />
                                    ))}
                                </div>
                            )}
                        </section>
                    </div>
                )}
            </div>
        </>
    );
}
