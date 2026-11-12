import { Head, Link } from '@inertiajs/react';
import { CalendarDays, ClipboardList } from 'lucide-react';
import { dashboard } from '@/routes';

export default function Dashboard() {
    return (
        <>
            <Head title="Početna" />

            <div className="flex flex-1 flex-col gap-8 p-4">
                <div>
                    <h1 className="text-2xl font-bold tracking-tight">
                        Dobro došli!
                    </h1>

                    <p className="mt-2 text-muted-foreground">
                        Dobro došli na platformu za prijavu na događaje. Ovdje
                        možete pronaći događaje koji vas zanimaju i upravljati
                        svojim prijavama.
                    </p>
                </div>

                <div className="grid gap-4 md:grid-cols-2">
                    <Link
                        href="/events"
                        className="group flex items-start gap-4 rounded-xl border p-6 transition-colors hover:bg-muted/50"
                    >
                        <div className="rounded-lg bg-primary/10 p-3 text-primary">
                            <CalendarDays className="size-6" />
                        </div>

                        <div>
                            <h2 className="font-semibold">Pregled događaja</h2>

                            <p className="mt-1 text-sm text-muted-foreground">
                                Pregledajte dostupne događaje i prijavite se na
                                one koji vas zanimaju.
                            </p>
                        </div>
                    </Link>

                    <Link
                        href="/my-registrations"
                        className="group flex items-start gap-4 rounded-xl border p-6 transition-colors hover:bg-muted/50"
                    >
                        <div className="rounded-lg bg-primary/10 p-3 text-primary">
                            <ClipboardList className="size-6" />
                        </div>

                        <div>
                            <h2 className="font-semibold">Moje prijave</h2>

                            <p className="mt-1 text-sm text-muted-foreground">
                                Pregledajte svoje prijave i pratite njihov
                                status.
                            </p>
                        </div>
                    </Link>
                </div>
            </div>
        </>
    );
}

Dashboard.layout = {
    breadcrumbs: [
        {
            title: 'Početna',
            href: dashboard(),
        },
    ],
};
