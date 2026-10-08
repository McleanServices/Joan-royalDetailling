export type StoreContact = {
    phone: string;
    phoneDisplay: string;
    whatsapp: string;
    hours: string;
};

export type StoreUser = {
    name: string;
    first_name?: string | null;
    last_name?: string | null;
    phone?: string | null;
    email: string;
};

export type CatalogService = {
    name: string;
    description: string;
    duration: string;
    variant_id: number;
    price: string;
};

export type CatalogGroup = {
    label: string;
    services: CatalogService[];
};

export type ReservationLine = {
    id: number;
    key: string;
    variant_id: number;
    name: string;
    duration: string;
    location: string;
    image: string;
    price: string;
};

export type Reservation = {
    lines: ReservationLine[];
    total: string;
    count: number;
    duration: string;
    tax: string;
};

export type CatalogCollection = {
    key: string;
    name: string;
    description: string;
    icon: string;
    image: string;
    quote_only: boolean;
    groups: CatalogGroup[];
};

export type PageProps = {
    auth: { user: StoreUser | null };
    store: StoreContact;
    flash: { success?: string | null; error?: string | null };
    errors?: Record<string, string>;
    reservationCount: number;
};
