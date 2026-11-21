import { Head, Link } from '@inertiajs/react';
import EventForm from './form';

type Category = {
    id: number;
    name: string;
};

type Tag = {
    id: number;
    name: string;
};

type EventData = {
    id: number;
    categories: Category[];
    title: string;
    description: string;
    location: string;
    starts_at: string;
    ends_at: string | null;
    capacity: number;
    seat_rows: number;
    seat_columns: number;
    image: string | null;
    status: 'draft' | 'published';
    tags: Tag[];
};

type Props = {
    categories: Category[];
    event: EventData;
    tags: Tag[];
};

export default function Edit({ categories, event, tags }: Props) {
    return (
        <>
            <Head title="Uredi događaj" />

            <div className="p-6">
                <h1 className="text-2xl font-bold">Uredi događaj</h1>

                <EventForm categories={categories} event={event} tags={tags} />

                <Link
                    href="/admin/events"
                    className="mt-4 inline-block text-sm text-muted-foreground hover:underline"
                >
                    Nazad na događaje
                </Link>
            </div>
        </>
    );
}
