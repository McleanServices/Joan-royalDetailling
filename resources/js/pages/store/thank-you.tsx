import { Head, Link } from '@inertiajs/react';
import StoreLayout from '@/layouts/store-layout';

export default function ThankYou() {
    return (
        <StoreLayout>
            <Head title="Merci" />
            <section className="py-5">
                <div className="container text-center">
                    <h1 className="hero-title">Paiement réussi</h1>
                    <p className="hero-subtitle">Merci. Votre commande Royal Detailing a bien été enregistrée.</p>
                    <Link href="/" className="btn-hero-primary">Retour à l'accueil</Link>
                </div>
            </section>
        </StoreLayout>
    );
}
