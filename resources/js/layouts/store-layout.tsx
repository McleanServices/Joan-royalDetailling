import { Link, router, usePage } from '@inertiajs/react';
import { type ReactNode, useEffect, useState } from 'react';
import { type PageProps } from '@/types/store';
import '../../css/storefront.css';

type StoreTheme = 'light' | 'dark';

function currentTheme(): StoreTheme {
    if (typeof document === 'undefined') {
        return 'light';
    }

    return document.documentElement.dataset.storeTheme === 'light' ? 'light' : 'dark';
}

function ThemeSwitch({ theme, onToggle }: { theme: StoreTheme; onToggle: () => void }) {
    const light = theme === 'light';

    return (
        <button
            type="button"
            className="header-icon"
            aria-pressed={light}
            aria-label={light ? 'Passer en mode sombre' : 'Passer en mode clair'}
            onClick={onToggle}
        >
            <i className={light ? 'bi bi-sun-fill' : 'bi bi-moon-stars-fill'} />
        </button>
    );
}

export default function StoreLayout({ children }: { children: ReactNode }) {
    const { auth, store, flash, errors, reservationCount } = usePage<PageProps>().props;
    const [countOverride, setCountOverride] = useState<number | null>(null);
    const count = countOverride ?? reservationCount ?? 0;

    useEffect(() => {
        function onCount(event: Event) {
            const detail = (event as CustomEvent<number | null>).detail;
            setCountOverride(detail);
        }

        window.addEventListener('store:reservation-count', onCount);

        return () => window.removeEventListener('store:reservation-count', onCount);
    }, []);
    const [open, setOpen] = useState(false);
    const [theme, setTheme] = useState<StoreTheme>(currentTheme);
    const displayName = [auth.user?.first_name, auth.user?.last_name].filter(Boolean).join(' ') || auth.user?.name;

    function toggleTheme() {
        const next: StoreTheme = theme === 'light' ? 'dark' : 'light';
        setTheme(next);
        if (next === 'light') {
            document.documentElement.dataset.storeTheme = 'light';
        } else {
            delete document.documentElement.dataset.storeTheme;
        }
        localStorage.setItem('store-theme', next);
    }

    return (
        <div className="storefront-page">
            <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" />
            <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" />
            <link
                rel="stylesheet"
                href="https://fonts.googleapis.com/css2?family=Rajdhani:wght@500;700&family=Inter:wght@400;500;600&family=Plus+Jakarta+Sans:ital,wght@0,500;0,700;1,500;1,700&display=swap"
            />

            <div className="topbar py-1 d-none d-md-block">
                <div className="container-xl">
                    <div className="d-flex align-items-center justify-content-between">
                        <div className="d-flex align-items-center gap-3">
                            <a href={`tel:${store.phone}`} className="d-flex align-items-center gap-1">
                                <i className="bi bi-telephone-fill" style={{ color: 'var(--royal-gold)' }} />
                                <strong>{store.phoneDisplay}</strong>
                            </a>
                            <span className="topbar-divider">|</span>
                            <span className="d-flex align-items-center gap-1">
                                <i className="bi bi-clock" style={{ color: 'var(--royal-gold)' }} />
                                {store.hours}
                            </span>
                        </div>
                        <div className="d-flex align-items-center gap-3">
                            <a href={`https://wa.me/${store.whatsapp}`} target="_blank" rel="noopener noreferrer" className="whatsapp-btn">
                                <i className="bi bi-whatsapp" /> WhatsApp
                            </a>
                            <div className="social-icons d-flex gap-2">
                                <a href="#" aria-label="Facebook"><i className="bi bi-facebook" /></a>
                                <a href="#" aria-label="Instagram"><i className="bi bi-instagram" /></a>
                                <a href="#" aria-label="TikTok"><i className="bi bi-tiktok" /></a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <nav className="navbar navbar-expand-lg main-navbar sticky-top">
                <div className="container-xl px-2">
                    <Link href="/" className="navbar-brand d-flex align-items-center gap-2 text-decoration-none">
                        <img src="/images/logo.jpg" alt="Royal Detailing logo" className="brand-logo" onError={(event) => { event.currentTarget.style.display = 'none'; }} />
                        <div>
                            <div className="brand-name">Royal Detailing <span>971</span></div>
                            <div className="brand-tagline">Expertise & Prestige</div>
                        </div>
                    </Link>
                    <div id="store-nav" className={`navbar-collapse ${open ? 'show' : ''}`}>
                        <ul className="navbar-nav me-auto align-items-lg-center">
                            <li className="nav-item"><Link className="nav-link active" href="/" onClick={() => setOpen(false)}>Accueil</Link></li>
                            <li className="nav-item"><a className="nav-link" href="/#services" onClick={() => setOpen(false)}>Services</a></li>
                            <li className="nav-item"><a className="nav-link" href="/#contact" onClick={() => setOpen(false)}>Contact</a></li>
                            {!auth.user && (
                                <li className="nav-item d-lg-none">
                                    <Link className="nav-link" href="/account" onClick={() => setOpen(false)}>Mon compte</Link>
                                </li>
                            )}
                        </ul>
                        {auth.user && (
                            <div className="mobile-session d-lg-none">
                                <span className="user-name">{displayName}</span>
                                <button type="button" className="header-logout" onClick={() => router.post('/logout')}>
                                    Se déconnecter
                                </button>
                            </div>
                        )}
                    </div>
                    <div className="header-tools">
                        {auth.user && (
                            <div className="header-session d-none d-lg-flex">
                                <span className="user-name">{displayName}</span>
                                <button type="button" className="header-logout" onClick={() => router.post('/logout')}>
                                    Se déconnecter
                                </button>
                            </div>
                        )}
                        <div className="header-cluster">
                            {!auth.user && (
                                <Link href="/account" className="header-icon d-none d-lg-inline-flex" title="Mon compte" aria-label="Mon compte">
                                    <i className="bi bi-person" />
                                </Link>
                            )}
                            <ThemeSwitch theme={theme} onToggle={toggleTheme} />
                            <button
                                type="button"
                                className="header-icon header-cart"
                                aria-label={count > 0 ? `Réservation, ${count} ${count > 1 ? 'services' : 'service'}` : 'Réservation'}
                                onClick={() => window.dispatchEvent(new Event('store:open-reservation'))}
                            >
                                <i className="bi bi-cart3" />
                                {count > 0 && <span className="header-cart-count">{count}</span>}
                            </button>
                        </div>
                        <button
                            className="navbar-toggler d-lg-none"
                            type="button"
                            aria-label="Menu"
                            aria-expanded={open}
                            aria-controls="store-nav"
                            onClick={() => setOpen((value) => !value)}
                        >
                            <span className="navbar-toggler-icon" />
                        </button>
                    </div>
                </div>
            </nav>

            {flash.success && (
                <div className="container-xl pt-3">
                    <div className="alert alert-success mb-0">{flash.success}</div>
                </div>
            )}

            {errors?.payment && (
                <div className="container-xl pt-3">
                    <div className="alert alert-danger mb-0">{errors.payment}</div>
                </div>
            )}

            {children}
        </div>
    );
}
