<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UserProfileController extends Controller
{
    /**
     * Redirige al perfil usando el mismo host y base path que la petición actual
     * (evita mandar a APP_URL si difiere del dominio real, p. ej. localhost vs .test).
     */
    protected function redirectToProfile(Request $request): \Illuminate\Http\RedirectResponse
    {
        $path = route('profile', [], false);

        return redirect()->away($request->getUriForPath($path));
    }

    public function show(Request $request)
    {
        $user = $request->user()->load(['person', 'profile']);

        return view('pages.profile', [
            'title' => 'Mi perfil',
            'user' => $user,
            'person' => $user->person,
        ]);
    }

    public function update(Request $request)
    {
        $user = $request->user();

        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
        ];

        if ($user->person_id) {
            $rules['first_name'] = ['nullable', 'string', 'max:255'];
            $rules['last_name'] = ['nullable', 'string', 'max:255'];
            $rules['person_email'] = ['nullable', 'string', 'email', 'max:255'];
            $rules['phone'] = ['nullable', 'string', 'max:50'];
            $rules['address'] = ['nullable', 'string', 'max:500'];
            $rules['document_number'] = ['nullable', 'string', 'max:32'];
        }

        $validated = $request->validate($rules);

        $user->update([
            'name' => $validated['name'],
            'email' => $validated['email'],
        ]);

        if ($user->person) {
            $user->person->update([
                'first_name' => $validated['first_name'] ?? $user->person->first_name,
                'last_name' => $validated['last_name'] ?? $user->person->last_name,
                'email' => array_key_exists('person_email', $validated)
                    ? ($validated['person_email'] !== '' && $validated['person_email'] !== null
                        ? $validated['person_email']
                        : null)
                    : $user->person->email,
                'phone' => $validated['phone'] ?? $user->person->phone,
                'address' => $validated['address'] ?? $user->person->address,
                'document_number' => $validated['document_number'] ?? $user->person->document_number,
            ]);
        }

        return $this->redirectToProfile($request)->with('status', 'Datos del perfil actualizados correctamente.');
    }

    public function updatePassword(Request $request)
    {
        $validated = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        $request->user()->update([
            'password' => $validated['password'],
        ]);

        return $this->redirectToProfile($request)->with('status', 'Contraseña actualizada correctamente.');
    }
}
