import { Head, Link } from '@inertiajs/react';
import StoreLayout from '@/layouts/store-layout';

const copy: Record<string, string> = {
    pending: "Le paiement n'est pas encore confirmé.",
    failed: 'Le paiement a échoué. Réessayez.',
    expired: 'Le paiement a expiré.',
    unavailable: 'Le paiement est indisponible pour le moment.',
};

export default function Checkout({
    orderId,
    total,
    state,
    hostedUrl,
}: {
    orderId: number;
    total: string;
    state: string;
    hostedUrl: string | null;
}) {
    const canRetry = hostedUrl !== null && (state === 'pending' || state === 'failed');

    return (
        <StoreLayout>
            <Head title={`Paiement commande ${orderId}`} />
            <section className="py-5">
                <div className="container">
                    <div className="col-lg-6 mx-auto card card-auth p-4">
                        <h1 className="h3">Commande #{orderId}</h1>
                        <p className="fs-4">Total : {total}</p>
                        <div className="alert alert-warning">{copy[state] ?? copy.unavailable}</div>
                        <div className="d-flex gap-2">
                            {canRetry && (
                                <a className="btn btn-primary" href={hostedUrl}>
                                    Paiement
                                </a>
                            )}
                            <Link className="btn btn-outline-secondary" href="/">
                                Retour
                            </Link>
                        </div>
                    </div>
                </div>
            </section>
        </StoreLayout>
    );
}
