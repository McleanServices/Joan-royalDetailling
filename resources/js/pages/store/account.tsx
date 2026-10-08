import { Head, useForm } from '@inertiajs/react';
import { AuthField, AuthPanel } from '@/components/auth-panel';
import StoreLayout from '@/layouts/store-layout';

export default function Account() {
    const form = useForm({
        first_name: '',
        last_name: '',
        email: '',
        password: '',
        password_confirmation: '',
    });

    return (
        <StoreLayout>
            <Head title="Créer un compte" />
            <AuthPanel
                title="Créer un compte"
                from="account"
                switchPrompt="Déjà un compte ?"
                switchHref="/login"
                switchLabel="Se connecter"
            >
                <form
                    className="auth-form"
                    onSubmit={(event) => {
                        event.preventDefault();
                        form.post('/account');
                    }}
                >
                    <div className="auth-names">
                        <AuthField
                            id="first_name"
                            label="Prénom"
                            value={form.data.first_name}
                            error={form.errors.first_name}
                            autoComplete="given-name"
                            onChange={(value) => form.setData('first_name', value)}
                        />
                        <AuthField
                            id="last_name"
                            label="Nom"
                            value={form.data.last_name}
                            error={form.errors.last_name}
                            autoComplete="family-name"
                            onChange={(value) => form.setData('last_name', value)}
                        />
                    </div>
                    <AuthField
                        id="email"
                        label="E-mail"
                        type="email"
                        value={form.data.email}
                        error={form.errors.email}
                        autoComplete="email"
                        onChange={(value) => form.setData('email', value)}
                    />
                    <div className="auth-secrets">
                        <AuthField
                            id="password"
                            label="Mot de passe"
                            type="password"
                            value={form.data.password}
                            error={form.errors.password}
                            autoComplete="new-password"
                            onChange={(value) => form.setData('password', value)}
                        />
                        <AuthField
                            id="password_confirmation"
                            label="Confirmer le mot de passe"
                            type="password"
                            value={form.data.password_confirmation}
                            error={form.errors.password_confirmation}
                            autoComplete="new-password"
                            onChange={(value) => form.setData('password_confirmation', value)}
                        />
                    </div>
                    <button className="btn-hero-primary auth-submit" type="submit" disabled={form.processing} aria-busy={form.processing}>
                        Créer un compte
                    </button>
                </form>
            </AuthPanel>
        </StoreLayout>
    );
}
