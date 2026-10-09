import { Head, router } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';
import ReservationPanel from '@/components/reservation-panel';
import StoreLayout from '@/layouts/store-layout';
import { type CatalogCollection, type CatalogService, type Reservation, type ReservationLine } from '@/types/store';

function minutesFrom(duration: string): number {
    const hours = duration.match(/(\d+)\s*h(?:\s*(\d+))?/u);
    if (hours) {
        return Number(hours[1]) * 60 + (hours[2] ? Number(hours[2]) : 0);
    }

    const mins = duration.match(/(\d+)\s*min/u);
    return mins ? Number(mins[1]) : 0;
}

function formatMinutes(total: number): string {
    const hours = Math.floor(total / 60);
    const mins = total % 60;

    if (hours > 0 && mins > 0) {
        return `${hours} h ${mins} min`;
    }

    if (hours > 0) {
        return `${hours} h`;
    }

    return `${mins} min`;
}

function centsFrom(price: string): number {
    const amount = Number(price.replace(/[^\d,.-]/g, '').replace(',', '.'));
    return Number.isFinite(amount) ? Math.round(amount * 100) : 0;
}

function formatCents(cents: number): string {
    return `${(cents / 100).toFixed(2).replace('.', ',')} €`;
}

let pendingLineId = 0;

function withService(reservation: Reservation, service: CatalogService, location: string, image: string): Reservation {
    pendingLineId -= 1;
    const line: ReservationLine = {
        id: pendingLineId,
        key: `pending-${service.variant_id}-${pendingLineId}`,
        variant_id: service.variant_id,
        name: service.name,
        duration: service.duration,
        location,
        image,
        price: service.price,
    };

    return {
        ...reservation,
        lines: [...reservation.lines, line],
        count: reservation.count + 1,
        total: formatCents(centsFrom(reservation.total) + centsFrom(service.price)),
        duration: formatMinutes(minutesFrom(reservation.duration) + minutesFrom(service.duration)),
    };
}

function Cover({ src, alt, icon }: { src: string; alt: string; icon: string }) {
    const [failed, setFailed] = useState(false);

    if (failed) {
        return (
            <div className="image-fallback">
                <i className={`bi ${icon}`} />
            </div>
        );
    }

    return <img src={src} alt={alt} onError={() => setFailed(true)} />;
}

export default function Home({ collections, reservation }: { collections: CatalogCollection[]; reservation: Reservation }) {
    const [active, setActive] = useState<CatalogCollection | null>(null);
    const [panelOpen, setPanelOpen] = useState(false);
    const [preview, setPreview] = useState<Reservation | null>(null);
    const reservationRef = useRef(reservation);
    const queue = useRef(Promise.resolve());
    const latestRequest = useRef(0);
    reservationRef.current = preview ?? reservation;
    const shown = preview ?? reservation;

    useEffect(() => {
        function openReservation() {
            setPanelOpen(true);
        }

        window.addEventListener('store:open-reservation', openReservation);

        return () => window.removeEventListener('store:open-reservation', openReservation);
    }, []);

    function addToReservation(service: CatalogService, location: string, image: string) {
        const next = withService(reservationRef.current, service, location, image);
        const request = ++latestRequest.current;
        reservationRef.current = next;
        setPreview(next);
        setPanelOpen(true);
        window.dispatchEvent(new CustomEvent('store:reservation-count', { detail: next.count }));

        queue.current = queue.current.then(() => new Promise<void>((resolve) => {
            router.post('/order/add', { variant_id: service.variant_id }, {
                async: true,
                preserveScroll: true,
                preserveState: true,
                only: ['reservation', 'reservationCount'],
                onSuccess: () => {
                    if (request === latestRequest.current) {
                        setPreview(null);
                        window.dispatchEvent(new CustomEvent('store:reservation-count', { detail: null }));
                    }
                },
                onFinish: () => resolve(),
            });
        }));
    }

    return (
        <StoreLayout>
            <Head title="Royal Detailing – Entretien & Nettoyage Auto" />

            <section className="hero" id="accueil">
                <div className="hero-bg" />
                <div className="container-xl">
                    <div className="row align-items-center gy-5">
                        <div className="col-lg-8 hero-content">
                            <h1 className="hero-title">
                                Réservez facilement<br />
                                le service dont vous<br />
                                <span className="highlight">avez besoin</span>
                            </h1>
                            <p className="hero-subtitle">
                                Nettoyage, entretien, services spécialisés et plus encore  réservation rapide en ligne, sans attente.
                            </p>
                            <div className="hero-actions">
                                <a href="#services" className="btn-hero-primary">
                                    <i className="bi bi-calendar-check-fill" /> Réserver un service
                                </a>
                                <a href="#services" className="btn-hero-secondary">
                                    <i className="bi bi-grid-fill" /> Voir les services
                                </a>
                            </div>
                            <div className="hero-trust">
                                <div className="trust-item"><i className="bi bi-shield-fill-check" /> Produits professionnels</div>
                                <div className="trust-item"><i className="bi bi-geo-alt-fill" /> Intervention à domicile</div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <section className="services-section" id="services">
                <div className="container-xl">
                    <div className="mb-5">
                        <p className="section-label">Nos Prestations</p>
                        <h2 className="section-heading">Choisissez votre <span>catégorie</span></h2>
                        <p className="section-sub">Sélectionnez la catégorie qui correspond à votre besoin pour découvrir nos formules et tarifs détaillés.</p>
                    </div>
                    <div className="row g-4">
                        {collections.map((collection) => (
                            <div className="col-12 col-md-6 col-lg-4" key={collection.key}>
                                <div className="cat-card">
                                    <div className="cat-image">
                                        <Cover src={collection.image} alt={collection.name} icon={collection.icon} />
                                    </div>
                                    <div className="cat-card-title">{collection.name}</div>
                                    <p className="cat-card-desc">{collection.description}</p>
                                    <span className="cat-count">
                                        <i className={`bi ${collection.quote_only ? 'bi-telephone-fill' : 'bi-grid-fill'}`} />
                                        {collection.quote_only ? ' Sur devis' : ` ${collection.groups.reduce((count, group) => count + group.services.length, 0)} services`}
                                    </span>
                                    <button type="button" className="btn-see-services" onClick={() => setActive(collection)}>
                                        <i className="bi bi-eye-fill" /> Voir les services
                                    </button>
                                </div>
                            </div>
                        ))}
                    </div>
                </div>
            </section>

            {active && (
                <div className={`modal fade svc-modal show d-block${panelOpen ? ' with-reservation' : ''}`} role="dialog" aria-modal="true" style={{ background: 'rgba(0,0,0,.65)' }}>
                    <div className={`modal-dialog modal-dialog-scrollable ${active.quote_only ? '' : 'modal-lg'}`}>
                        <div className="modal-content">
                            <div className="modal-header">
                                <div className="d-flex align-items-center gap-2">
                                    <div className="cat-icon-wrap" style={{ width: 38, height: 38, borderRadius: 9, fontSize: '1.1rem', marginBottom: 0 }}>
                                        <i className={`bi ${active.icon}`} />
                                    </div>
                                    <h5 className="modal-title">{active.name}</h5>
                                </div>
                                <button type="button" className="btn-close" aria-label="Fermer" onClick={() => setActive(null)} />
                            </div>
                            <div className="modal-body">
                                {active.quote_only ? (
                                    <div className="devis-callout">
                                        <i className={`bi ${active.icon} devis-icon`} />
                                        <h5>{active.name}</h5>
                                        <p>{active.description}</p>
                                        <div className="d-flex justify-content-center">
                                            <a href="tel:+590690112233" className="btn-cta" onClick={() => setActive(null)}>
                                                <i className="bi bi-telephone-fill" /> {active.key === 'autres' ? 'Nous contacter' : 'Demander un devis'}
                                            </a>
                                        </div>
                                    </div>
                                ) : (
                                    active.groups.map((group) => (
                                        <div key={group.label}>
                                            <p className="svc-group-label">{group.label}</p>
                                            {group.services.map((service) => (
                                                <div className="svc-card" key={service.variant_id}>
                                                    <div className="svc-card-name">{service.name}</div>
                                                    <div className="svc-card-desc">{service.description}</div>
                                                    <div className="svc-card-footer">
                                                        <div className="svc-meta">
                                                            <span className="svc-duration"><i className="bi bi-clock" /> {service.duration}</span>
                                                            <span className="svc-price">{service.price}</span>
                                                        </div>
                                                        <div className="svc-actions">
                                                            <button type="button" className="btn-add-reservation" onClick={() => addToReservation(service, group.label, active.image)}>
                                                                Ajouter à la réservation
                                                            </button>
                                                            <button type="button" className="btn btn-primary btn-sm" onClick={() => addToReservation(service, group.label, active.image)}>
                                                                <i className="bi bi-calendar-check-fill" /> Réserver
                                                            </button>
                                                        </div>
                                                    </div>
                                                </div>
                                            ))}
                                        </div>
                                    ))
                                )}
                            </div>
                        </div>
                    </div>
                </div>
            )}

            <ReservationPanel reservation={shown} open={panelOpen} onClose={() => setPanelOpen(false)} />

            <section className="py-5" id="contact">
                <div className="container">
                    <h3 className="fs-5 mb-4 text-secondary text-uppercase">Contact</h3>
                    <div className="row gy-4">
                        <div className="col-12 col-lg-6">
                            <form className="border p-4" onSubmit={(event) => event.preventDefault()}>
                                <div className="mb-3">
                                    <label className="form-label" htmlFor="fullname">Nom complet <span className="text-danger">*</span></label>
                                    <input className="form-control" id="fullname" name="fullname" required />
                                </div>
                                <div className="row">
                                    <div className="col-md-6 mb-3">
                                        <label className="form-label" htmlFor="email">E-mail <span className="text-danger">*</span></label>
                                        <input className="form-control" id="email" name="email" type="email" required />
                                    </div>
                                    <div className="col-md-6 mb-3">
                                        <label className="form-label" htmlFor="phone">Téléphone</label>
                                        <input className="form-control" id="phone" name="phone" type="tel" />
                                    </div>
                                </div>
                                <div className="mb-3">
                                    <label className="form-label" htmlFor="subject">Objet <span className="text-danger">*</span></label>
                                    <input className="form-control" id="subject" name="subject" required />
                                </div>
                                <div className="mb-3">
                                    <label className="form-label" htmlFor="message">Message <span className="text-danger">*</span></label>
                                    <textarea className="form-control" id="message" name="message" rows={3} required />
                                </div>
                                <button className="btn btn-primary btn-lg w-100" type="button">Envoyer</button>
                            </form>
                        </div>
                        <div className="col-12 col-lg-6">
                            <h4>Téléphone</h4>
                            <p><a className="link-secondary text-decoration-none" href="tel:+590690112233">+590 690 11 22 33</a></p>
                            <h4>E-mail</h4>
                            <p><a className="link-secondary text-decoration-none" href="mailto:royaldetailling@mail.com">royaldetailling@mail.com</a></p>
                            <h4>Horaires d&apos;ouverture</h4>
                            <p className="text-secondary mb-1">Lun – Ven · 9h – 17h</p>
                            <p className="text-secondary">Sam – Dim · 9h – 14h</p>
                        </div>
                    </div>
                </div>
            </section>
        </StoreLayout>
    );
}

Home.layout = null;
