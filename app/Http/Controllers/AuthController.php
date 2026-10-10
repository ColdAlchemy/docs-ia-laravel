<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function formEntrar()
    {
        return view('auth.entrar');
    }

    public function entrar(Request $request): RedirectResponse
    {
        $credenciais = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ], [
            'email.required' => 'Informe o e-mail.',
            'email.email' => 'Esse e-mail não parece válido.',
            'password.required' => 'Informe a senha.',
        ]);

        if (! Auth::attempt($credenciais, $request->boolean('lembrar'))) {
            throw ValidationException::withMessages(['email' => 'E-mail ou senha incorretos.']);
        }

        $request->session()->regenerate();

        return redirect()->intended(route('documentos.index'));
    }

    public function formCadastrar()
    {
        return view('auth.cadastrar');
    }

    public function cadastrar(Request $request): RedirectResponse
    {
        $dados = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:150', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::min(8)],
        ], [
            'name.required' => 'Informe seu nome.',
            'email.required' => 'Informe o e-mail.',
            'email.email' => 'Esse e-mail não parece válido.',
            'email.unique' => 'Já existe uma conta com esse e-mail.',
            'password.required' => 'Crie uma senha.',
            'password.min' => 'A senha precisa ter pelo menos 8 caracteres.',
            'password.confirmed' => 'As senhas não são iguais.',
        ]);

        $user = User::create($dados);
        $espaco = $user->espacos()->create(['nome' => 'Espaço de '.$user->name, 'dono_id' => $user->id]);

        Auth::login($user);
        $request->session()->regenerate();
        $request->session()->put('espaco_id', $espaco->id);

        return redirect()->route('documentos.index')->with('ok', 'Conta criada. Seu primeiro espaço de trabalho já está pronto.');
    }

    public function sair(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('entrar');
    }
}
