<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = $request->only(['search']);

        $users = User::query()
            ->when($filters['search'] ?? null, function ($query, $search) {
                $query->where(function ($query) use ($search) {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->orderBy('name')
            ->paginate(10)
            ->withQueryString();

        return Inertia::render('admin/users/index', [
            'users' => $users,
            'filters' => $filters,
        ]);
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email,'.$user->id],
            'role' => ['required', 'in:user,admin'],
        ]);

        if ($request->user()->is($user) && $validated['role'] !== 'admin') {
            return redirect()
                ->route('admin.users.index')
                ->with('error', 'Ne možete promijeniti vlastitu administratorsku ulogu.');
        }

        if (
            $user->role === 'admin'
            && $validated['role'] !== 'admin'
            && User::where('role', 'admin')->count() <= 1
        ) {
            return redirect()
                ->route('admin.users.index')
                ->with('error', 'Nije moguće ukloniti ulogu posljednjem administratoru.');
        }

        $user->name = $validated['name'];
        $user->email = $validated['email'];
        $user->role = $validated['role'];
        $user->save();

        return redirect()
            ->route('admin.users.index')
            ->with('success', 'Podaci korisnika su uspješno ažurirani.');
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        if ($request->user()->is($user)) {
            return redirect()
                ->route('admin.users.index')
                ->with('error', 'Ne možete obrisati vlastiti korisnički račun.');
        }

        if (
            $user->role === 'admin'
            && User::where('role', 'admin')->count() <= 1
        ) {
            return redirect()
                ->route('admin.users.index')
                ->with('error', 'Nije moguće obrisati posljednjeg administratora.');
        }

        $user->delete();

        return redirect()
            ->route('admin.users.index')
            ->with('success', 'Korisnik je uspješno obrisan.');
    }
}
