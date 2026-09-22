<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use App\Models\Usuario;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Login por `login` OU `email` + senha (coluna `senha`, ver Usuario::getAuthPassword()).
 * Só usuário ativo entra. 5 tentativas por minuto por identificador+IP.
 */
class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return [
            'acesso' => ['required', 'string', 'max:255'],
            'senha' => ['required', 'string'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return ['acesso' => 'login ou e-mail', 'senha' => 'senha'];
    }

    public function autenticar(): void
    {
        $this->garantirSemBloqueio();

        $acesso = trim((string) $this->input('acesso'));
        $campo = filter_var($acesso, FILTER_VALIDATE_EMAIL) ? 'email' : 'login';

        $credenciais = [
            $campo => $campo === 'login' ? mb_strtolower($acesso) : $acesso,
            'password' => $this->input('senha'),
            'ativo' => true,
        ];

        if (! Auth::attempt($credenciais, $this->boolean('lembrar'))) {
            RateLimiter::hit($this->chaveThrottle());

            throw ValidationException::withMessages([
                'acesso' => 'As credenciais informadas não conferem, ou o usuário está inativo.',
            ]);
        }

        RateLimiter::clear($this->chaveThrottle());

        /** @var Usuario $usuario */
        $usuario = Auth::user();
        $usuario->forceFill(['ultimo_login_em' => now()])->saveQuietly();
    }

    protected function garantirSemBloqueio(): void
    {
        if (! RateLimiter::tooManyAttempts($this->chaveThrottle(), 5)) {
            return;
        }

        event(new Lockout($this));

        $segundos = RateLimiter::availableIn($this->chaveThrottle());

        throw ValidationException::withMessages([
            'acesso' => "Muitas tentativas de login. Tente novamente em {$segundos} segundos.",
        ]);
    }

    protected function chaveThrottle(): string
    {
        return Str::transliterate(Str::lower((string) $this->input('acesso')).'|'.$this->ip());
    }
}
