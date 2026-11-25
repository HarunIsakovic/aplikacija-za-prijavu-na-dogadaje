import { Head, Link } from '@inertiajs/react';
import { CheckCircle2, Clock, XCircle } from 'lucide-react';

type ReservationStatus = 'pending' | 'confirmed' | 'cancelled' | 'expired';

type Reservation = {
    id: number;
    status: ReservationStatus;
    total_amount: string;
    currency: string;
    event_title: string;
};

type Props = {
    reservation: Reservation;
};

const statusMessages: Record<
    ReservationStatus,
    {
        title: string;
        description: string;
        icon: typeof CheckCircle2;
        color: string;
        background: string;
    }
> = {
    confirmed: {
        title: 'Plaćanje je uspješno!',
        description: 'Vaša rezervacija je potvrđena.',
        icon: CheckCircle2,
        color: 'text-green-600',
        background: 'bg-green-500/10',
    },
    pending: {
        title: 'Plaćanje se obrađuje',
        description:
            'Čekamo potvrdu uplate. Rezervacija će biti potvrđena nakon što sistem za naplatu potvrdi transakciju.',
        icon: Clock,
        color: 'text-amber-600',
        background: 'bg-amber-500/10',
    },
    cancelled: {
        title: 'Rezervacija je otkazana',
        description: 'Ova rezervacija je otkazana.',
        icon: XCircle,
        color: 'text-muted-foreground',
        background: 'bg-muted',
    },
    expired: {
        title: 'Rezervacija je istekla',
        description:
            'Vrijeme za plaćanje je isteklo. Možete ponovo odabrati dostupna sjedišta.',
        icon: XCircle,
        color: 'text-muted-foreground',
        background: 'bg-muted',
    },
};

export default function ReservationSuccess({ reservation }: Props) {
    const status = statusMessages[reservation.status];
    const StatusIcon = status.icon;

    return (
        <>
            <Head title="Status rezervacije" />

            <div className="mx-auto flex min-h-[70vh] max-w-2xl items-center justify-center p-4 sm:p-6">
                <div className="w-full rounded-2xl border bg-card p-6 text-center shadow-sm sm:p-10">
                    <div
                        className={`mx-auto mb-6 flex size-16 items-center justify-center rounded-full ${status.background}`}
                    >
                        <StatusIcon className={`size-9 ${status.color}`} />
                    </div>

                    <h1 className="text-2xl font-bold tracking-tight sm:text-3xl">
                        {status.title}
                    </h1>

                    <p className="mx-auto mt-3 max-w-lg text-muted-foreground">
                        {status.description}
                    </p>

                    <div className="my-8 rounded-xl border bg-muted/30 p-5 text-left">
                        <div className="space-y-4">
                            <div>
                                <p className="text-sm text-muted-foreground">
                                    Događaj
                                </p>
                                <p className="mt-1 font-semibold">
                                    {reservation.event_title}
                                </p>
                            </div>

                            <div className="border-t pt-4">
                                <p className="text-sm text-muted-foreground">
                                    Broj rezervacije
                                </p>
                                <p className="mt-1 font-semibold">
                                    #{reservation.id}
                                </p>
                            </div>

                            <div className="border-t pt-4">
                                <p className="text-sm text-muted-foreground">
                                    Ukupan iznos
                                </p>
                                <p className="mt-1 text-xl font-bold">
                                    {Number(reservation.total_amount).toFixed(
                                        2,
                                    )}{' '}
                                    {reservation.currency.toUpperCase()}
                                </p>
                            </div>
                        </div>
                    </div>

                    <div className="flex flex-col justify-center gap-3 sm:flex-row">
                        <Link
                            href="/my-registrations"
                            className="inline-flex items-center justify-center rounded-md bg-primary px-5 py-2.5 text-sm font-medium text-primary-foreground transition-colors hover:bg-primary/90"
                        >
                            Moje prijave
                        </Link>

                        <Link
                            href="/events"
                            className="inline-flex items-center justify-center rounded-md border px-5 py-2.5 text-sm font-medium transition-colors hover:bg-muted"
                        >
                            Pregled događaja
                        </Link>
                    </div>
                </div>
            </div>
        </>
    );
}
