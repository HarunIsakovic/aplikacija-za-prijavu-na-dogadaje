import { Head, Link, usePage } from '@inertiajs/react';
import { dashboard, login, register } from '@/routes';

export default function Welcome() {
    const { auth } = usePage().props;

    return (
        <>
            <Head title="Dobro došli" />

            <div className="flex min-h-screen flex-col bg-background text-foreground">
                <header className="border-b">
                    <div className="mx-auto flex max-w-6xl items-center justify-between px-6 py-5">
                        <Link
                            href="/"
                            className="text-xl font-bold tracking-tight"
                        >
                            Prijava na događaje
                        </Link>

                        <nav className="flex items-center gap-3">
                            {auth.user ? (
                                <Link
                                    href={dashboard()}
                                    className="rounded-lg bg-primary px-5 py-2.5 text-sm font-medium text-primary-foreground hover:opacity-90"
                                >
                                    Moj profil
                                </Link>
                            ) : (
                                <>
                                    <Link
                                        href={login()}
                                        className="rounded-lg px-4 py-2.5 text-sm font-medium hover:bg-muted"
                                    >
                                        Prijava
                                    </Link>

                                    <Link
                                        href={register()}
                                        className="rounded-lg bg-primary px-5 py-2.5 text-sm font-medium text-primary-foreground hover:opacity-90"
                                    >
                                        Registracija
                                    </Link>
                                </>
                            )}
                        </nav>
                    </div>
                </header>

                <main className="flex flex-1 items-center justify-center px-6 py-20">
                    <div className="mx-auto max-w-3xl text-center">
                        <div className="mb-6 inline-flex rounded-full bg-primary/10 px-4 py-2 text-sm font-medium text-primary">
                            Platforma za organizaciju događaja
                        </div>

                        <h1 className="text-4xl font-bold tracking-tight sm:text-6xl">
                            Otkrijte događaje i
                            <span className="block text-primary">
                                postanite njihov dio.
                            </span>
                        </h1>

                        <p className="mx-auto mt-6 max-w-2xl text-lg leading-8 text-muted-foreground">
                            Pronađite zanimljive događaje, pregledajte njihove
                            detalje i jednostavno se prijavite. Na jednom mjestu
                            pratite svoje prijave i informacije o događajima.
                        </p>

                        <div className="mt-10 flex flex-wrap justify-center gap-4">
                            <Link
                                href="/events"
                                className="rounded-lg bg-primary px-6 py-3 text-sm font-semibold text-primary-foreground hover:opacity-90"
                            >
                                Pregledaj događaje
                            </Link>

                            {!auth.user && (
                                <>
                                    <Link
                                        href={login()}
                                        className="rounded-lg border px-6 py-3 text-sm font-semibold hover:bg-muted"
                                    >
                                        Prijava
                                    </Link>

                                    <Link
                                        href={register()}
                                        className="rounded-lg border px-6 py-3 text-sm font-semibold hover:bg-muted"
                                    >
                                        Registracija
                                    </Link>
                                </>
                            )}
                        </div>

                        {!auth.user && (
                            <p className="mt-5 text-sm text-muted-foreground">
                                Događaje možete pregledati bez kreiranja računa.
                            </p>
                        )}
                    </div>
                </main>

                <footer className="border-t py-6 text-center text-sm text-muted-foreground">
                    Aplikacija za prijavu na događaje
                </footer>
            </div>
        </>
    );
}
