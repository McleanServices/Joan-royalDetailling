import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { useState, type FormEvent } from 'react';
import StoreLayout from '@/layouts/store-layout';
import { type PageProps, type Reservation } from '@/types/store';

export default function ReservationPage({ reservation }: { reservation: Reservation }) {
    const { auth, errors } = usePage<PageProps>().props;
    const [removing, setRemoving] = useState<number | null>(null);
    const needsAddress = reservation.lines.some((line) => line.location.toLowerCase().includes('domicile'));
    const serviceLabel = reservation.count > 1 ? `${reservation.count} services` : `${reservation.count} service`;
    const form = useForm({
        first_name: auth.user?.first_name ?? '',
        last_name: auth.user?.last_name ?? '',
        email: auth.user?.email ?? '',
        phone: auth.user?.phone ?? '',
        line_one: '',
        postcode: '',
        city: '',
    });

    function remove(lineId: number) {
        setRemoving(lineId);
        router.delete(`/order/lines/${lineId}`, {
            preserveScroll: true,
            preserveState: true,
            onFinish: () => setRemoving(null),
        });
    }

    function pay(event: FormEvent) {
        event.preventDefault();
        form.post('/order/create', {
            preserveScroll: true,
            preserveState: true,
        });
    }

    return (
        <StoreLayout>
            <Head title="Réservation" />
            <section className="py-5">
                <div className="container reservation-review">
                    <div className="reservation-review-services">
                        <h1>Récapitulatif de la réservation</h1>
                        {reservation.lines.length === 0 ? (
                            <p className="reservation-empty">Aucun service dans la réservation.</p>
                        ) : (
                            reservation.lines.map((line) => (
                                <article className="reservation-line" key={line.key}>
                                    <img src={line.image} alt="" />
                                    <div>
                                        <strong>{line.name}</strong>
                                        <p>{line.duration} · {line.location}</p>
                                    </div>
                                    <span className="reservation-line-price">{line.price}</span>
                                    <button type="button" aria-label={`Retirer ${line.name}`} disabled={removing === line.id || line.id < 0} onClick={() => remove(line.id)}>
                                        <i className="bi bi-trash" />
                                    </button>
                                </article>
                            ))
                        )}
                    </div>
                    <form className="reservation-review-details" onSubmit={pay}>
                        {reservation.lines.length > 0 && (
                            <>
                                <h2>Vos coordonnées</h2>
                                <div className="reservation-fields-row">
                                    <Field label="Prénom" name="first_name" value={form.data.first_name} error={form.errors.first_name} autoComplete="given-name" onChange={(value) => form.setData('first_name', value)} />
                                    <Field label="Nom" name="last_name" value={form.data.last_name} error={form.errors.last_name} autoComplete="family-name" onChange={(value) => form.setData('last_name', value)} />
                                </div>
                                <Field label="E-mail" name="email" type="email" value={form.data.email} error={form.errors.email} autoComplete="email" placeholder="nom@exemple.fr" onChange={(value) => form.setData('email', value)} />
                                <Field label="Téléphone" name="phone" type="tel" value={form.data.phone} error={form.errors.phone} autoComplete="tel" placeholder="06 00 00 00 00" onChange={(value) => form.setData('phone', value)} />
                                {needsAddress && (
                                    <>
                                        <Field label="Adresse" name="line_one" value={form.data.line_one} error={form.errors.line_one} autoComplete="street-address" onChange={(value) => form.setData('line_one', value)} />
                                        <div className="reservation-fields-row">
                                            <Field label="Code postal" name="postcode" value={form.data.postcode} error={form.errors.postcode} autoComplete="postal-code" placeholder="97139" onChange={(value) => form.setData('postcode', value)} />
                                            <Field label="Ville" name="city" value={form.data.city} error={form.errors.city} autoComplete="address-level2" onChange={(value) => form.setData('city', value)} />
                                        </div>
                                    </>
                                )}
                                <div className="reservation-total-row">
                                    <span>Total</span>
                                    <strong>{reservation.total}</strong>
                                </div>
                                <div className="reservation-meta-row">
                                    <span>{serviceLabel} · {reservation.duration}</span>
                                    <span>dont {reservation.tax} de TVA</span>
                                </div>
                            </>
                        )}
                        {errors?.payment && <p className="reservation-field-error">{errors.payment}</p>}
                        <button type="submit" className="reservation-pay" disabled={form.processing || reservation.lines.length === 0}>
                            {form.processing ? 'Chargement…' : 'Paiement'}
                        </button>
                        <Link className="reservation-back" href="/">Retour</Link>
                    </form>
                </div>
            </section>
        </StoreLayout>
    );
}

function Field({
    label,
    name,
    value,
    error,
    onChange,
    type = 'text',
    autoComplete,
    placeholder,
}: {
    label: string;
    name: string;
    value: string;
    error?: string;
    onChange: (value: string) => void;
    type?: string;
    autoComplete: string;
    placeholder?: string;
}) {
    const fieldId = `reservation-${name}`;
    const errorId = error ? `${fieldId}-error` : undefined;

    return (
        <div className="reservation-field">
            <label htmlFor={fieldId}>{label} *</label>
            <input
                id={fieldId}
                name={name}
                type={type}
                value={value}
                autoComplete={autoComplete}
                placeholder={placeholder}
                aria-invalid={error ? true : undefined}
                aria-describedby={errorId}
                onChange={(event) => onChange(event.target.value)}
            />
            {error && <p id={errorId} className="reservation-field-error">{error}</p>}
        </div>
    );
}
