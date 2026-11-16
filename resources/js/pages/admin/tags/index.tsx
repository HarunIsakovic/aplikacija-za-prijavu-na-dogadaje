import { store, update, destroy } from '@/routes/admin/tags';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Form, Head, router } from '@inertiajs/react';
import { useState } from 'react';

type Tag = {
    id: number;
    name: string;
};

type PaginationLink = {
    url: string | null;
    label: string;
    active: boolean;
};

type PaginatedTags = {
    data: Tag[];
    total: number;
    links: PaginationLink[];
};

type Flash = {
    success?: string | null;
    error?: string | null;
};

type Props = {
    tags: PaginatedTags;
    filters: {
        search?: string;
    };
    flash: Flash;
};

export default function Index({ tags, filters, flash }: Props) {
    const [editingTag, setEditingTag] = useState<Tag | null>(null);
    const [search, setSearch] = useState(filters.search ?? '');

    function handleSearch(value: string) {
        setSearch(value);

        router.get(
            '/admin/tags',
            { search: value || undefined },
            {
                preserveState: true,
                preserveScroll: true,
                replace: true,
            },
        );
    }

    return (
        <>
            <Head title="Tagovi" />

            <div className="p-6">
                <h1 className="text-2xl font-bold">Tagovi</h1>

                {flash.success && (
                    <p className="mt-4 text-sm text-green-600">
                        {flash.success}
                    </p>
                )}

                {flash.error && (
                    <p className="mt-4 text-sm text-red-600">{flash.error}</p>
                )}

                <Form {...store.form()} className="mt-6 space-y-4">
                    {({ processing, errors }) => (
                        <>
                            <div className="grid gap-2">
                                <Label htmlFor="name">Naziv taga</Label>

                                <Input
                                    id="name"
                                    name="name"
                                    placeholder="Unesite naziv taga"
                                    required
                                />

                                <InputError message={errors.name} />
                            </div>

                            <Button disabled={processing}>Dodaj tag</Button>
                        </>
                    )}
                </Form>

                {editingTag && (
                    <Form
                        {...update.form(editingTag.id)}
                        key={editingTag.id}
                        className="mt-6 space-y-4"
                    >
                        {({ processing, errors }) => (
                            <>
                                <div className="grid gap-2">
                                    <Label htmlFor="edit-name">
                                        Novi naziv taga
                                    </Label>

                                    <Input
                                        id="edit-name"
                                        name="name"
                                        defaultValue={editingTag.name}
                                        required
                                    />

                                    <InputError message={errors.name} />
                                </div>

                                <div className="flex gap-2">
                                    <Button disabled={processing}>
                                        Sačuvaj izmjene
                                    </Button>

                                    <Button
                                        type="button"
                                        variant="outline"
                                        onClick={() => setEditingTag(null)}
                                    >
                                        Odustani
                                    </Button>
                                </div>
                            </>
                        )}
                    </Form>
                )}

                <div className="mt-8">
                    <div className="mb-4 grid gap-2">
                        <Label htmlFor="search">Pretraga tagova</Label>

                        <Input
                            id="search"
                            value={search}
                            onChange={(event) =>
                                handleSearch(event.target.value)
                            }
                            placeholder="Pretraži po nazivu taga..."
                        />
                    </div>

                    <p className="mb-4 text-sm text-gray-600">
                        Ukupno rezultata: {tags.total}
                    </p>

                    {tags.data.length === 0 ? (
                        <p className="text-gray-600">
                            Nema tagova koji odgovaraju pretrazi.
                        </p>
                    ) : (
                        <ul className="space-y-2">
                            {tags.data.map((tag) => (
                                <li
                                    key={tag.id}
                                    className="flex items-center justify-between rounded-lg border p-4"
                                >
                                    {tag.name}

                                    <div className="flex gap-2">
                                        <Button
                                            type="button"
                                            variant="outline"
                                            onClick={() => setEditingTag(tag)}
                                        >
                                            Uredi
                                        </Button>

                                        <Form
                                            {...destroy.form(tag.id)}
                                            onBefore={() =>
                                                window.confirm(
                                                    'Da li ste sigurni da želite obrisati ovaj tag?',
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
                                </li>
                            ))}
                        </ul>
                    )}

                    <div className="mt-6 flex flex-wrap gap-2">
                        {tags.links.map((link, index) => (
                            <Button
                                key={index}
                                type="button"
                                size="sm"
                                variant={link.active ? 'default' : 'outline'}
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
                </div>
            </div>
        </>
    );
}
