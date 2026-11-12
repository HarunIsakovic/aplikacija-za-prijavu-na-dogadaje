import { Head, Link } from '@inertiajs/react';
import { CalendarDays, Tags, FolderOpen, ArrowRight } from 'lucide-react';

const adminSections = [
    {
        title: 'Upravljanje događajima',
        description:
            'Kreirajte nove događaje, uređujte postojeće i upravljajte njihovom objavom.',
        href: '/admin/events',
        icon: CalendarDays,
    },
    {
        title: 'Kategorije',
        description: 'Dodajte, uređujte i pregledajte kategorije događaja.',
        href: '/admin/categories',
        icon: FolderOpen,
    },
    {
        title: 'Tagovi',
        description: 'Upravljajte tagovima koji se mogu dodijeliti događajima.',
        href: '/admin/tags',
        icon: Tags,
    },
];

export default function Dashboard() {
    return (
        <>
            <Head title="Administracija" />

            <div className="mx-auto max-w-7xl space-y-8 p-4 sm:p-6">
                <div>
                    <p className="mb-2 text-sm font-medium text-primary">
                        ADMINISTRATORSKI PANEL
                    </p>

                    <h1 className="text-3xl font-bold tracking-tight">
                        Administracija
                    </h1>

                    <p className="mt-2 text-muted-foreground">
                        Upravljajte događajima, kategorijama, tagovima i
                        prijavama korisnika na jednom mjestu.
                    </p>
                </div>

                <div className="grid gap-5 sm:grid-cols-2">
                    {adminSections.map((section) => {
                        const Icon = section.icon;

                        return (
                            <Link
                                key={section.title}
                                href={section.href}
                                className="group flex flex-col rounded-xl border bg-card p-6 shadow-sm transition-all duration-200 hover:-translate-y-1 hover:shadow-md"
                            >
                                <div className="mb-5 flex items-center justify-between">
                                    <div className="rounded-xl bg-primary/10 p-3 text-primary">
                                        <Icon className="size-6" />
                                    </div>

                                    <ArrowRight className="size-5 text-muted-foreground transition-transform group-hover:translate-x-1 group-hover:text-primary" />
                                </div>

                                <h2 className="text-lg font-semibold">
                                    {section.title}
                                </h2>

                                <p className="mt-2 text-sm leading-6 text-muted-foreground">
                                    {section.description}
                                </p>
                            </Link>
                        );
                    })}
                </div>
            </div>
        </>
    );
}
