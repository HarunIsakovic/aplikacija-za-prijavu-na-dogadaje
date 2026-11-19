import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Form, Head, Link, router } from '@inertiajs/react';
import { create, destroy, duplicate, edit } from '@/routes/admin/events';
import { useState } from 'react';

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
    status: 'draft' | 'published' | 'expired';
    categories: Category[];
};

type PaginationLink = {
    url: string | null;
    label: string;
    active: boolean;
};

type PaginatedEvents = {
    data: EventData[];
    current_page: number;
    last_page: number;
    total: number;
    links: PaginationLink[];
};

type Flash = {
    success?: string | null;
    error?: string | null;
};

type Props = {
    events: PaginatedEvents;
    filters: {
        search?: string;
    };
    flash: Flash;
};

export default function Index({ events, filters, flash }: Props) {
    const [search, setSearch] = useState(filters.search ?? '');

    function applySearch(event: React.FormEvent<HTMLFormElement>) {
        event.preventDefault();

        router.get(
            '/admin/events',
            { search: search || undefined },
            {
                preserveState: true,
                preserveScroll: true,
                replace: true,
            },
        );
    }

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

                <form
                    onSubmit={applySearch}
                    className="mt-6 flex flex-wrap gap-2"
                >
                    <Input
                        value={search}
                        onChange={(event) => setSearch(event.target.value)}
                        placeholder="Pretraži događaje..."
                        className="max-w-md"
                    />

                    <Button type="submit">Pretraži</Button>

                    {filters.search && (
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => {
                                setSearch('');
                                router.get('/admin/events');
                            }}
                        >
                            Poništi pretragu
                        </Button>
                    )}
                </form>

                <div className="mt-4 text-sm text-muted-foreground">
                    Ukupno pronađenih događaja: {events.total}
                </div>

                <div className="mt-6">
                    {events.data.length === 0 ? (
                        <p className="text-gray-600">
                            Nema događaja koji odgovaraju pretrazi.
                        </p>
                    ) : (
                        <div className="space-y-4">
                            {events.data.map((event) => (
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
                                                Kategorije:{' '}
                                                {event.categories.length > 0
                                                    ? event.categories
                                                          .map(
                                                              (category) =>
                                                                  category.name,
                                                          )
                                                          .join(', ')
                                                    : 'Nema kategorija'}
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
                                                            : event.status ===
                                                                'expired'
                                                              ? 'font-medium text-gray-500'
                                                              : 'font-medium text-yellow-600'
                                                    }
                                                >
                                                    {event.status ===
                                                    'published'
                                                        ? 'Objavljen'
                                                        : event.status ===
                                                            'expired'
                                                          ? 'Završen'
                                                          : 'Nacrt'}
                                                </span>
                                            </p>
                                        </div>
                                    </div>

                                    <div className="flex flex-wrap gap-2">
                                        <Button asChild variant="outline">
                                            <Link
                                                href={`/admin/events/${event.id}/registrations`}
                                            >
                                                Prijavljeni korisnici
                                            </Link>
                                        </Button>

                                        <Button asChild variant="outline">
                                            <Link href={edit.url(event.id)}>
                                                Uredi
                                            </Link>
                                        </Button>

                                        <Form
                                            action={duplicate.url(event.id)}
                                            method="post"
                                        >
                                            <Button
                                                type="submit"
                                                variant="outline"
                                            >
                                                Dupliciraj
                                            </Button>
                                        </Form>

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

                {events.last_page > 1 && (
                    <div className="mt-6 flex flex-wrap items-center justify-center gap-2">
                        {events.links.map((link, index) => (
                            <Button
                                key={index}
                                type="button"
                                variant={link.active ? 'default' : 'outline'}
                                size="sm"
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
        </>
    );
}
