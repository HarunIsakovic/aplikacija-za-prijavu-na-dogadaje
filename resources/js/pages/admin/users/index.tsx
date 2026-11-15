import { destroy, update } from '@/routes/admin/users';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Form, Head } from '@inertiajs/react';
import { useState } from 'react';

type User = {
    id: number;
    name: string;
    email: string;
    role: string;
    created_at: string;
};

type Flash = {
    success?: string | null;
    error?: string | null;
};

type Props = {
    users: User[];
    flash: Flash;
};

export default function Index({ users, flash }: Props) {
    const [editingUser, setEditingUser] = useState<User | null>(null);

    return (
        <>
            <Head title="Korisnici" />

            <div className="p-6">
                <h1 className="text-2xl font-bold">Korisnici</h1>

                <p className="mt-2 text-muted-foreground">
                    Pregled i upravljanje registrovanim korisnicima.
                </p>

                {flash.success && (
                    <p className="mt-4 text-sm text-green-600">
                        {flash.success}
                    </p>
                )}

                {flash.error && (
                    <p className="mt-4 text-sm text-red-600">{flash.error}</p>
                )}

                {editingUser && (
                    <Form
                        {...update.form(editingUser.id)}
                        key={editingUser.id}
                        className="mt-6 space-y-4 rounded-lg border p-4"
                    >
                        {({ processing, errors }) => (
                            <>
                                <h2 className="text-lg font-semibold">
                                    Uređivanje korisnika
                                </h2>

                                <div className="grid gap-2">
                                    <Label htmlFor="edit-name">Ime</Label>

                                    <Input
                                        id="edit-name"
                                        name="name"
                                        defaultValue={editingUser.name}
                                        required
                                    />

                                    <InputError message={errors.name} />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="edit-email">Email</Label>

                                    <Input
                                        id="edit-email"
                                        name="email"
                                        type="email"
                                        defaultValue={editingUser.email}
                                        required
                                    />

                                    <InputError message={errors.email} />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="edit-role">Uloga</Label>

                                    <select
                                        id="edit-role"
                                        name="role"
                                        defaultValue={editingUser.role}
                                        className="h-10 rounded-md border border-input bg-background px-3 text-sm"
                                    >
                                        <option value="user">Korisnik</option>
                                        <option value="admin">
                                            Administrator
                                        </option>
                                    </select>

                                    <InputError message={errors.role} />
                                </div>

                                <div className="flex gap-2">
                                    <Button disabled={processing}>
                                        Sačuvaj izmjene
                                    </Button>

                                    <Button
                                        type="button"
                                        variant="outline"
                                        onClick={() => setEditingUser(null)}
                                    >
                                        Odustani
                                    </Button>
                                </div>
                            </>
                        )}
                    </Form>
                )}

                <div className="mt-6 overflow-x-auto rounded-lg border">
                    {users.length === 0 ? (
                        <p className="p-4 text-gray-600">
                            Trenutno nema registrovanih korisnika.
                        </p>
                    ) : (
                        <table className="w-full text-left text-sm">
                            <thead className="border-b bg-muted/50">
                                <tr>
                                    <th className="p-4">Ime</th>
                                    <th className="p-4">Email</th>
                                    <th className="p-4">Uloga</th>
                                    <th className="p-4">Datum registracije</th>
                                    <th className="p-4">Akcije</th>
                                </tr>
                            </thead>

                            <tbody>
                                {users.map((user) => (
                                    <tr
                                        key={user.id}
                                        className="border-b last:border-0"
                                    >
                                        <td className="p-4 font-medium">
                                            {user.name}
                                        </td>

                                        <td className="p-4">{user.email}</td>

                                        <td className="p-4">
                                            {user.role === 'admin'
                                                ? 'Administrator'
                                                : 'Korisnik'}
                                        </td>

                                        <td className="p-4">
                                            {new Date(
                                                user.created_at,
                                            ).toLocaleDateString('bs-BA')}
                                        </td>

                                        <td className="p-4">
                                            <div className="flex gap-2">
                                                <Button
                                                    type="button"
                                                    variant="outline"
                                                    onClick={() =>
                                                        setEditingUser(user)
                                                    }
                                                >
                                                    Uredi
                                                </Button>

                                                <Form
                                                    {...destroy.form(user.id)}
                                                    onBefore={() =>
                                                        window.confirm(
                                                            `Da li ste sigurni da želite obrisati korisnika ${user.name}?`,
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
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    )}
                </div>
            </div>
        </>
    );
}
