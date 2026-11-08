import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Form } from '@inertiajs/react';

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
    category_id: number;
    title: string;
    description: string;
    location: string;
    starts_at: string;
    ends_at: string | null;
    capacity: number;
    image: string | null;
    status: 'draft' | 'published';
    tags: Tag[];
};

type Props = {
    categories: Category[];
    event?: EventData;
    tags: Tag[];
};

export default function EventForm({ categories, event, tags }: Props) {
    const isEditing = Boolean(event);

    return (
        <Form
            action={isEditing ? `/admin/events/${event?.id}` : '/admin/events'}
            method={isEditing ? 'put' : 'post'}
            encType="multipart/form-data"
            className="mt-6 max-w-2xl space-y-5"
        >
            {({ processing, errors }) => (
                <>
                    <div className="grid gap-2">
                        <Label htmlFor="title">Naziv događaja</Label>
                        <Input
                            id="title"
                            name="title"
                            defaultValue={event?.title ?? ''}
                            placeholder="Unesite naziv događaja"
                            required
                        />
                        <InputError message={errors.title} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="description">Opis događaja</Label>
                        <textarea
                            id="description"
                            name="description"
                            defaultValue={event?.description ?? ''}
                            placeholder="Unesite opis događaja"
                            required
                            rows={5}
                            className="w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none focus-visible:ring-[3px] focus-visible:ring-ring/50"
                        />
                        <InputError message={errors.description} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="location">Lokacija</Label>
                        <Input
                            id="location"
                            name="location"
                            defaultValue={event?.location ?? ''}
                            placeholder="Unesite lokaciju"
                            required
                        />
                        <InputError message={errors.location} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="category_id">Kategorija</Label>
                        <Select
                            name="category_id"
                            defaultValue={
                                event ? String(event.category_id) : undefined
                            }
                            required
                        >
                            <SelectTrigger id="category_id" className="w-full">
                                <SelectValue placeholder="Odaberite kategoriju" />
                            </SelectTrigger>

                            <SelectContent>
                                {categories.map((category) => (
                                    <SelectItem
                                        key={category.id}
                                        value={String(category.id)}
                                    >
                                        {category.name}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <InputError message={errors.category_id} />
                    </div>

                    <div className="grid gap-2">
                        <Label>Tagovi</Label>

                        {tags.length > 0 ? (
                            <div className="grid gap-2">
                                {tags.map((tag) => (
                                    <div
                                        key={tag.id}
                                        className="flex items-center gap-2"
                                    >
                                        <input
                                            id={`tag-${tag.id}`}
                                            type="checkbox"
                                            name="tags[]"
                                            value={tag.id}
                                            defaultChecked={
                                                event?.tags.some(
                                                    (eventTag) =>
                                                        eventTag.id === tag.id,
                                                ) ?? false
                                            }
                                            className="h-4 w-4 rounded border-input"
                                        />

                                        <Label htmlFor={`tag-${tag.id}`}>
                                            {tag.name}
                                        </Label>
                                    </div>
                                ))}
                            </div>
                        ) : (
                            <p className="text-sm text-muted-foreground">
                                Trenutno nema dostupnih tagova.
                            </p>
                        )}

                        <InputError message={errors.tags} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="starts_at">
                            Datum i vrijeme početka
                        </Label>
                        <Input
                            id="starts_at"
                            name="starts_at"
                            type="datetime-local"
                            defaultValue={
                                event?.starts_at
                                    ? event.starts_at
                                          .slice(0, 16)
                                          .replace(' ', 'T')
                                    : ''
                            }
                            required
                        />
                        <InputError message={errors.starts_at} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="ends_at">
                            Datum i vrijeme završetka (opcionalno)
                        </Label>
                        <Input
                            id="ends_at"
                            name="ends_at"
                            type="datetime-local"
                            defaultValue={
                                event?.ends_at
                                    ? event.ends_at
                                          .slice(0, 16)
                                          .replace(' ', 'T')
                                    : ''
                            }
                        />
                        <InputError message={errors.ends_at} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="capacity">Kapacitet</Label>
                        <Input
                            id="capacity"
                            name="capacity"
                            type="number"
                            min="1"
                            defaultValue={event?.capacity ?? 1}
                            required
                        />
                        <InputError message={errors.capacity} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="image">Slika događaja</Label>
                        <Input
                            id="image"
                            name="image"
                            type="file"
                            accept=".jpg,.jpeg,.png,.webp"
                        />
                        <InputError message={errors.image} />

                        {event?.image && (
                            <p className="text-sm text-muted-foreground">
                                Trenutna slika: {event.image}
                            </p>
                        )}
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="status">Status događaja</Label>
                        <Select
                            name="status"
                            defaultValue={event?.status ?? 'draft'}
                        >
                            <SelectTrigger id="status" className="w-full">
                                <SelectValue placeholder="Odaberite status" />
                            </SelectTrigger>

                            <SelectContent>
                                <SelectItem value="draft">Nacrt</SelectItem>
                                <SelectItem value="published">
                                    Objavljen
                                </SelectItem>
                            </SelectContent>
                        </Select>
                        <InputError message={errors.status} />
                    </div>

                    <div className="flex gap-2">
                        <Button disabled={processing}>
                            {isEditing ? 'Sačuvaj izmjene' : 'Kreiraj događaj'}
                        </Button>
                    </div>
                </>
            )}
        </Form>
    );
}
