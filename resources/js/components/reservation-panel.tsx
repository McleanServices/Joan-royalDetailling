import { Link, router } from '@inertiajs/react';
import { useState } from 'react';
import { type Reservation } from '@/types/store';

export default function ReservationPanel({ reservation, open, onClose }: { reservation: Reservation; open: boolean; onClose: () => void }) {
    const [removing, setRemoving] = useState<number | null>(null);
    const serviceLabel = reservation.count > 1 ? `${reservation.count} services` : `${reservation.count} service`;
    const canContinue = reservation.lines.length > 0;

    function remove(lineId: number) {
        setRemoving(lineId);
        router.delete(`/order/lines/${lineId}`, {
            preserveScroll: true,
            preserveState: true,
            onFinish: () => setRemoving(null),
        });
    }

    if (!open) {
        return null;
    }

    return (
        <>
            <button type="button" className="reservation-backdrop" aria-label="Fermer le récapitulatif" onClick={onClose} />
            <aside className="reservation-panel" role="dialog" aria-label="Récapitulatif de la réservation">
                <header className="reservation-panel-header">
                    <button type="button" className="reservation-collapse" aria-label="Fermer" onClick={onClose}>
                        <i className="bi bi-chevron-right" />
                    </button>
                    <h2>Récapitulatif de la réservation</h2>
                </header>
                <div className="reservation-panel-body">
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
                <footer className="reservation-panel-footer">
                    <div className="reservation-total-row">
                        <span>Total</span>
                        <strong>{reservation.total}</strong>
                    </div>
                    <div className="reservation-meta-row">
                        <span>{serviceLabel} · {reservation.duration}</span>
                        <span>dont {reservation.tax} de TVA</span>
                    </div>
                    {canContinue ? (
                        <Link className="reservation-pay" href="/reservation">
                            Poursuivre
                        </Link>
                    ) : (
                        <button type="button" className="reservation-pay" disabled>
                            Poursuivre
                        </button>
                    )}
                </footer>
            </aside>
        </>
    );
}
