import { Head, useForm } from '@inertiajs/react';
import { AuthField, AuthPanel } from '@/components/auth-panel';
import StoreLayout from '@/layouts/store-layout';

export default function Login({ status }: { status?: string }) {
    const form = useForm({
        email: '',
        password: '',
        remember: false,
    });

    return (
        <StoreLayout>
            <Head title="Se connecter" />
            <AuthPanel
                title="Se connecter"
                from="login"
                note={status}
                switchPrompt="Pas encore de compte ?"
                switchHref="/account"
                switchLabel="Créer un compte"
            >
                <form
                    className="auth-form"
                    onSubmit={(event) => {
                        event.preventDefault();
                        form.post('/login');
                    }}
                >
                    <AuthField
                        id="email"
                        label="E-mail"
                        type="email"
                        value={form.data.email}
                        error={form.errors.email}
                        autoComplete="email"
                        autoFocus
                        onChange={(value) => form.setData('email', value)}
                    />
                    <AuthField
                        id="password"
                        label="Mot de passe"
                        type="password"
                        value={form.data.password}
                        error={form.errors.password}
                        autoComplete="current-password"
                        onChange={(value) => form.setData('password', value)}
                    />
                    <button className="btn-hero-primary auth-submit" type="submit" disabled={form.processing} aria-busy={form.processing}>
                        Connexion
                    </button>
                </form>
            </AuthPanel>
        </StoreLayout>
    );
}
