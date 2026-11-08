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

type Props = {
    categories: Category[];
    tags: Tag[];
};

export default function Create({ categories, tags }: Props) {
    return (
        <>
            <Head title="Novi događaj" />

            <div className="p-6">
                <h1 className="text-2xl font-bold">Dodaj događaj</h1>

                <EventForm categories={categories} tags={tags} />

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
