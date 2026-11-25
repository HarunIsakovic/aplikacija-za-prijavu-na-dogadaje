import { Head, Link } from '@inertiajs/react';
import { CheckCircle2, XCircle } from 'lucide-react';

type Props = {
    cancelled: boolean;
    message: string;
};

export default function ReservationCancel({ cancelled, message }: Props) {
    const Icon = cancelled ? CheckCircle2 : XCircle;

    return (
        <>
            <Head title="Otkazivanje rezervacije" />

            <div className="mx-auto flex min-h-[70vh] max-w-2xl items-center justify-center p-4 sm:p-6">
                <div className="w-full rounded-2xl border bg-card p-6 text-center shadow-sm sm:p-10">
                    <div
                        className={`mx-auto mb-6 flex size-16 items-center justify-center rounded-full ${
                            cancelled ? 'bg-green-500/10' : 'bg-muted'
                        }`}
                    >
                        <Icon
                            className={`size-9 ${
                                cancelled
                                    ? 'text-green-600'
                                    : 'text-muted-foreground'
                            }`}
                        />
                    </div>

                    <h1 className="text-2xl font-bold tracking-tight sm:text-3xl">
                        {cancelled
                            ? 'Rezervacija je otkazana'
                            : 'Rezervacija nije otkazana'}
                    </h1>

                    <p className="mx-auto mt-3 max-w-lg text-muted-foreground">
                        {message}
                    </p>

                    {cancelled && (
                        <p className="mt-4 text-sm text-muted-foreground">
                            Nije vam naplaćena ova rezervacija. Sjedišta su
                            ponovo dostupna drugim korisnicima.
                        </p>
                    )}

                    <div className="mt-8 flex flex-col justify-center gap-3 sm:flex-row">
                        <Link
                            href="/events"
                            className="inline-flex items-center justify-center rounded-md bg-primary px-5 py-2.5 text-sm font-medium text-primary-foreground transition-colors hover:bg-primary/90"
                        >
                            Pregled događaja
                        </Link>

                        <Link
                            href="/my-registrations"
                            className="inline-flex items-center justify-center rounded-md border px-5 py-2.5 text-sm font-medium transition-colors hover:bg-muted"
                        >
                            Moje prijave
                        </Link>
                    </div>
                </div>
            </div>
        </>
    );
}
