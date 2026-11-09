import { Button } from '@/components/ui/button';
import { Form, Head, Link } from '@inertiajs/react';
import { create, destroy, edit } from '@/routes/admin/events';

type Category = {
    id: number;
    name: string;
};

type EventData = {
    id: number;
    title: string;
    description: string;
    location: string;
    starts_at: string;
    ends_at: string | null;
    capacity: number;
    image: string | null;
    status: 'draft' | 'published';
    category: Category;
};

type Flash = {
    success?: string | null;
    error?: string | null;
};

type Props = {
    events: EventData[];
    flash: Flash;
};

export default function Index({ events, flash }: Props) {
    return (
        <>
            <Head title="Događaji" />

            <div className="p-6">
                <div className="flex flex-wrap items-center justify-between gap-4">
                    <h1 className="text-2xl font-bold">Događaji</h1>

                    <Button asChild>
                        <Link href={create.url()}>Dodaj događaj</Link>
                    </Button>
                </div>

                {flash.success && (
                    <p className="mt-4 text-sm text-green-600">
                        {flash.success}
                    </p>
                )}

                {flash.error && (
                    <p className="mt-4 text-sm text-red-600">{flash.error}</p>
                )}

                <div className="mt-6">
                    {events.length === 0 ? (
                        <p className="text-gray-600">Trenutno nema događaja.</p>
                    ) : (
                        <div className="space-y-4">
                            {events.map((event) => (
                                <div
                                    key={event.id}
                                    className="flex flex-col justify-between gap-4 rounded-lg border p-4 sm:flex-row sm:items-center"
                                >
                                    <div className="flex items-start gap-4">
                                        {event.image && (
                                            <img
                                                src={`/storage/${event.image}`}
                                                alt={`Slika događaja ${event.title}`}
                                                className="h-24 w-32 shrink-0 rounded-md object-cover"
                                            />
                                        )}

                                        <div className="space-y-1">
                                            <h2 className="font-semibold">
                                                {event.title}
                                            </h2>

                                            <p className="text-sm text-muted-foreground">
                                                Kategorija:{' '}
                                                {event.category.name}
                                            </p>

                                            <p className="text-sm text-muted-foreground">
                                                Lokacija: {event.location}
                                            </p>

                                            <p className="text-sm text-muted-foreground">
                                                Početak:{' '}
                                                {new Date(
                                                    event.starts_at,
                                                ).toLocaleString('bs-BA')}
                                            </p>

                                            <p className="text-sm text-muted-foreground">
                                                Kapacitet: {event.capacity}
                                            </p>

                                            <p className="text-sm">
                                                Status:{' '}
                                                <span
                                                    className={
                                                        event.status ===
                                                        'published'
                                                            ? 'font-medium text-green-600'
                                                            : 'font-medium text-yellow-600'
                                                    }
                                                >
                                                    {event.status ===
                                                    'published'
                                                        ? 'Objavljen'
                                                        : 'Nacrt'}
                                                </span>
                                            </p>
                                        </div>
                                    </div>

                                    <div className="flex flex-wrap gap-2">
                                        <Button asChild variant="outline">
                                            <Link href={`/admin/events/${event.id}/registrations`}>
                                                Prijavljeni korisnici
                                            </Link>
                                        </Button>

                                        <Button asChild variant="outline">
                                            <Link href={edit.url(event.id)}>
                                                Uredi
                                            </Link>
                                        </Button>

                                        <Form
                                            action={destroy.url(event.id)}
                                            method="delete"
                                            onBefore={() =>
                                                window.confirm(
                                                    'Da li ste sigurni da želite obrisati ovaj događaj?',
                                                )
                                            }
                                        >
                                            <Button
                                                type="submit"
                                                variant="destructive"
                                            >
                                                Obriši
                                            </Button>
                                        </Form>
                                    </div>
                                </div>
                            ))}
                        </div>
                    )}
                </div>
            </div>
        </>
    );
}
