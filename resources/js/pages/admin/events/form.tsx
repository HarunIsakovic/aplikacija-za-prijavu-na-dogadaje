import { useEffect, useState } from 'react';
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

type PriceGroup = {
    from_row: number;
    to_row: number;
    price: string;
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
    max_tickets_per_user: number;
    seat_price_groups: {
        id: number;
        from_row: number;
        to_row: number;
        price: string;
    }[];
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

    const [seatRows, setSeatRows] = useState(event?.seat_rows ?? 1);
    const [seatColumns, setSeatColumns] = useState(
        event?.seat_columns ?? event?.capacity ?? 1,
    );
    const [maxTicketsPerUser, setMaxTicketsPerUser] = useState(
        event?.max_tickets_per_user ?? 1,
    );

    const [priceGroups, setPriceGroups] = useState<PriceGroup[]>(
        event?.seat_price_groups?.map((group) => ({
            from_row: group.from_row,
            to_row: group.to_row,
            price: group.price,
        })) ?? [
            {
                from_row: 1,
                to_row: event?.seat_rows ?? 1,
                price: '0',
            },
        ],
    );

    const capacity = seatRows * seatColumns;

    useEffect(() => {
        setSeatRows(event?.seat_rows ?? 1);
        setSeatColumns(event?.seat_columns ?? event?.capacity ?? 1);
        setMaxTicketsPerUser(event?.max_tickets_per_user ?? 1);

        setPriceGroups(
            event?.seat_price_groups?.map((group) => ({
                from_row: group.from_row,
                to_row: group.to_row,
                price: group.price,
            })) ?? [
                {
                    from_row: 1,
                    to_row: event?.seat_rows ?? 1,
                    price: '0',
                },
            ],
        );
    }, [event]);

    function updatePriceGroup(
        index: number,
        field: keyof PriceGroup,
        value: number | string,
    ) {
        setPriceGroups((current) =>
            current.map((group, groupIndex) =>
                groupIndex === index ? { ...group, [field]: value } : group,
            ),
        );
    }

    function addPriceGroup() {
        setPriceGroups((current) => {
            const lastGroup = current[current.length - 1];
            const nextRow = lastGroup ? lastGroup.to_row + 1 : 1;

            return [
                ...current,
                {
                    from_row: nextRow,
                    to_row: Math.max(nextRow, seatRows),
                    price: '0',
                },
            ];
        });
    }

    function removePriceGroup(index: number) {
        setPriceGroups((current) =>
            current.filter((_, groupIndex) => groupIndex !== index),
        );
    }

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
                        <Label>Kategorije</Label>

                        {categories.length > 0 ? (
                            <div className="grid gap-2">
                                {categories.map((category) => (
                                    <div
                                        key={category.id}
                                        className="flex items-center gap-2"
                                    >
                                        <input
                                            id={`category-${category.id}`}
                                            type="checkbox"
                                            name="categories[]"
                                            value={category.id}
                                            defaultChecked={
                                                event?.categories.some(
                                                    (eventCategory) =>
                                                        eventCategory.id ===
                                                        category.id,
                                                ) ?? false
                                            }
                                            className="h-4 w-4 rounded border-input"
                                        />

                                        <Label
                                            htmlFor={`category-${category.id}`}
                                        >
                                            {category.name}
                                        </Label>
                                    </div>
                                ))}
                            </div>
                        ) : (
                            <p className="text-sm text-muted-foreground">
                                Trenutno nema dostupnih kategorija.
                            </p>
                        )}

                        <InputError message={errors.categories} />
                        <InputError message={errors['categories.0']} />
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
                        <Label htmlFor="seat_rows">Broj redova sjedišta</Label>
                        <Input
                            id="seat_rows"
                            name="seat_rows"
                            type="number"
                            min="1"
                            value={seatRows}
                            onChange={(e) =>
                                setSeatRows(Math.max(1, Number(e.target.value)))
                            }
                            required
                        />
                        <InputError message={errors.seat_rows} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="seat_columns">
                            Broj sjedišta u jednom redu
                        </Label>
                        <Input
                            id="seat_columns"
                            name="seat_columns"
                            type="number"
                            min="1"
                            value={seatColumns}
                            onChange={(e) =>
                                setSeatColumns(
                                    Math.max(1, Number(e.target.value)),
                                )
                            }
                            required
                        />
                        <InputError message={errors.seat_columns} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="capacity">Ukupan kapacitet</Label>
                        <Input
                            id="capacity"
                            name="capacity"
                            type="number"
                            value={capacity}
                            readOnly
                        />
                        <p className="text-sm text-muted-foreground">
                            Kapacitet se automatski izračunava na osnovu broja
                            redova i sjedišta u jednom redu.
                        </p>
                        <InputError message={errors.capacity} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="max_tickets_per_user">
                            Maksimalan broj karata po korisniku
                        </Label>
                        <Input
                            id="max_tickets_per_user"
                            name="max_tickets_per_user"
                            type="number"
                            min="1"
                            max={capacity}
                            value={maxTicketsPerUser}
                            onChange={(e) =>
                                setMaxTicketsPerUser(
                                    Math.max(1, Number(e.target.value)),
                                )
                            }
                            required
                        />
                        <p className="text-sm text-muted-foreground">
                            Odredite koliko najviše sjedišta jedan korisnik može
                            odabrati za ovaj događaj.
                        </p>
                        <InputError message={errors.max_tickets_per_user} />
                    </div>

                    <div className="grid gap-3">
                        <div>
                            <Label>Cjenovne grupe sjedišta</Label>
                            <p className="text-sm text-muted-foreground">
                                Odredite cijenu za raspone redova. Svaki red
                                mora pripadati tačno jednoj cjenovnoj grupi.
                            </p>
                        </div>

                        {priceGroups.map((group, index) => (
                            <div
                                key={index}
                                className="grid gap-3 rounded-md border p-4"
                            >
                                <div className="flex items-center justify-between">
                                    <Label>Cjenovna grupa {index + 1}</Label>

                                    {priceGroups.length > 1 && (
                                        <Button
                                            type="button"
                                            variant="destructive"
                                            size="sm"
                                            onClick={() =>
                                                removePriceGroup(index)
                                            }
                                        >
                                            Ukloni
                                        </Button>
                                    )}
                                </div>

                                <div className="grid grid-cols-2 gap-3">
                                    <div className="grid gap-2">
                                        <Label
                                            htmlFor={`price-group-${index}-from`}
                                        >
                                            Od reda
                                        </Label>
                                        <Input
                                            id={`price-group-${index}-from`}
                                            name={`price_groups[${index}][from_row]`}
                                            type="number"
                                            min="1"
                                            max={seatRows}
                                            value={group.from_row}
                                            onChange={(e) =>
                                                updatePriceGroup(
                                                    index,
                                                    'from_row',
                                                    Number(e.target.value),
                                                )
                                            }
                                            required
                                        />
                                        <InputError
                                            message={
                                                errors[
                                                    `price_groups.${index}.from_row`
                                                ]
                                            }
                                        />
                                    </div>

                                    <div className="grid gap-2">
                                        <Label
                                            htmlFor={`price-group-${index}-to`}
                                        >
                                            Do reda
                                        </Label>
                                        <Input
                                            id={`price-group-${index}-to`}
                                            name={`price_groups[${index}][to_row]`}
                                            type="number"
                                            min="1"
                                            max={seatRows}
                                            value={group.to_row}
                                            onChange={(e) =>
                                                updatePriceGroup(
                                                    index,
                                                    'to_row',
                                                    Number(e.target.value),
                                                )
                                            }
                                            required
                                        />
                                        <InputError
                                            message={
                                                errors[
                                                    `price_groups.${index}.to_row`
                                                ]
                                            }
                                        />
                                    </div>
                                </div>

                                <div className="grid gap-2">
                                    <Label
                                        htmlFor={`price-group-${index}-price`}
                                    >
                                        Cijena jednog sjedišta (KM)
                                    </Label>
                                    <Input
                                        id={`price-group-${index}-price`}
                                        name={`price_groups[${index}][price]`}
                                        type="number"
                                        min="0"
                                        step="0.01"
                                        value={group.price}
                                        onChange={(e) =>
                                            updatePriceGroup(
                                                index,
                                                'price',
                                                e.target.value,
                                            )
                                        }
                                        required
                                    />
                                    <InputError
                                        message={
                                            errors[
                                                `price_groups.${index}.price`
                                            ]
                                        }
                                    />
                                </div>
                            </div>
                        ))}

                        <InputError message={errors.price_groups} />

                        <Button
                            type="button"
                            variant="outline"
                            onClick={addPriceGroup}
                        >
                            Dodaj cjenovnu grupu
                        </Button>
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
