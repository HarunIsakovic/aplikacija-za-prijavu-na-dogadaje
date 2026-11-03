import { Head } from '@inertiajs/react';

export default function Dashboard() {
    return ( 
        <> 
            <Head title="Administracija" />

            <div className="p-6"> 
                <h1 className="text-2xl font-bold"> 
                    Administratorski panel
                </h1>

                <p className="mt-2 text-gray-600">
                    Dobrodošli u administratorski dio aplikacije za prijavu na događaje. 
                </p>
            </div>
        </>
    );
} 