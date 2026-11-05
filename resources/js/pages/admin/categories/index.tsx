import { store, update, destroy } from '@/routes/admin/categories';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Form, Head } from '@inertiajs/react';
import { useState } from 'react';

type Category = {
    id: number;
    name: string;
};

type Flash = {
    success?: string | null;
    error?: string | null;
};

type Props = {
    categories: Category[];
    flash: Flash;
};

export default function Index({ categories, flash }: Props) {
    const [editingCategory, setEditingCategory] = useState<Category | null>(
        null,
    );

    return (
        <>
            <Head title="Kategorije" />
            <div className="p-6">
                <h1 className="text-2xl font-bold">Kategorije</h1>

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
                                <Label htmlFor="name">Naziv kategorije</Label>

                                <Input
                                    id="name"
                                    name="name"
                                    placeholder="Unesite naziv kategorije"
                                    required
                                />

                                <InputError message={errors.name} />
                            </div>

                            <Button disabled={processing}>
                                Dodaj kategoriju
                            </Button>
                        </>
                    )}
                </Form>

                {editingCategory && (
                    <Form
                        {...update.form(editingCategory.id)}
                        key={editingCategory.id}
                        className="mt-6 space-y-4"
                    >
                        {({ processing, errors }) => (
                            <>
                                <div className="grid gap-2">
                                    <Label htmlFor="edit-name">
                                        Novi naziv kategorije
                                    </Label>

                                    <Input
                                        id="edit-name"
                                        name="name"
                                        defaultValue={editingCategory.name}
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
                                        onClick={() => setEditingCategory(null)}
                                    >
                                        Odustani
                                    </Button>
                                </div>
                            </>
                        )}
                    </Form>
                )}

                <div className="mt-6">
                    {categories.length === 0 ? (
                        <p className="text-gray-600">
                            Trenutno nema kategorija.
                        </p>
                    ) : (
                        <ul className="space-y-2">
                            {categories.map((category) => (
                                <li
                                    key={category.id}
                                    className="flex items-center justify-between rounded-lg border p-4"
                                >
                                    {category.name}

                                    <div className="flex gap-2">
                                        <Button
                                            type="button"
                                            variant="outline"
                                            onClick={() =>
                                                setEditingCategory(category)
                                            }
                                        >
                                            Uredi
                                        </Button>

                                        <Form
                                            {...destroy.form(category.id)}
                                            onBefore={() => {
                                                return window.confirm(
                                                    'Da li ste sigurni da želite obrisati ovu kategoriju?',
                                                );
                                            }}
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
                </div>
            </div>
        </>
    );
}
