import { store, update, destroy } from '@/routes/admin/tags';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Form, Head } from '@inertiajs/react';
import { useState } from 'react';

type Tag = {
    id: number;
    name: string;
};

type Flash = {
    success?: string | null;
    error?: string | null;
};

type Props = {
    tags: Tag[];
    flash: Flash;
};

export default function Index({ tags, flash }: Props) {
    const [editingTag, setEditingTag] = useState<Tag | null>(null);

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

                <div className="mt-6">
                    {tags.length === 0 ? (
                        <p className="text-gray-600">Trenutno nema tagova.</p>
                    ) : (
                        <ul className="space-y-2">
                            {tags.map((tag) => (
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
                </div>
            </div>
        </>
    );
}
