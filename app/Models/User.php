<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use RuntimeException;

/**
 * `is_admin` e `is_church_president` ficam FORA do fillable de propósito: são as
 * duas colunas que decidem acesso a painel. Elas só são gravadas pelo
 * UserResource do Filament, que já exige um admin autenticado.
 *
 * `church_id` e `phone` são do próprio usuário e ele edita em /profile — por
 * isso a igreja, sozinha, não abre porta nenhuma.
 */
#[Fillable(['name', 'email', 'password', 'church_id', 'phone'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_admin' => 'boolean',
            'is_church_president' => 'boolean',
        ];
    }

    public function church(): BelongsTo
    {
        return $this->belongsTo(Church::class);
    }

    public function registrations(): HasMany
    {
        return $this->hasMany(Registration::class);
    }

    public function delegates(): HasMany
    {
        return $this->hasMany(Delegate::class);
    }

    protected static function booted(): void
    {
        // A UMP cadastra a delegação por e-mail antes de o jovem ter conta.
        // Quando a conta nasce, o vínculo é fechado.
        static::created(fn (self $user) => Delegate::linkToNewUser($user));

        // Última linha de defesa contra trancar todo mundo fora do painel. O
        // UserResource já esconde o botão; isto pega o caminho que não passa
        // por ele (exclusão em massa, tinker, código futuro).
        static::deleting(function (self $user) {
            if ($user->is_admin && static::where('is_admin', true)->count() <= 1) {
                throw new RuntimeException(
                    'Este é o último administrador da plataforma. Promova outro usuário antes de excluí-lo.'
                );
            }
        });
    }

    public function isAdmin(): bool
    {
        return (bool) $this->is_admin;
    }

    /**
     * Presidente de UMP: quem envia a inscrição do congresso pelo painel /ump.
     *
     * Repare que ter igreja NÃO basta. Qualquer jovem escolhe a própria igreja
     * em /profile — quem é presidente é decisão da diretoria, marcada no
     * UserResource. Enquanto as duas coisas eram a mesma coluna, expor a igreja
     * no perfil daria acesso ao painel da UMP para todo mundo.
     */
    public function isChurchPresident(): bool
    {
        return $this->is_church_president && $this->church_id !== null;
    }

    /**
     * São dois painéis com públicos diferentes, então a pergunta tem que ser
     * respondida por painel. Antes isto devolvia `is_admin` para os dois, o que
     * deixava o /ump inacessível para quem ele foi feito — e só dava para
     * contornar promovendo o presidente local a admin da federação, que enxerga
     * todas as igrejas.
     */
    public function canAccessPanel(Panel $panel): bool
    {
        return match ($panel->getId()) {
            'admin' => $this->isAdmin(),
            'ump' => $this->isAdmin() || $this->isChurchPresident(),
            default => false,
        };
    }
}
