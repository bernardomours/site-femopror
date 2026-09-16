<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Delegate extends Model
{
    use HasFactory;

    protected $fillable = [
        'congress_subscription_id',
        'user_id',
        'name',
        'email',
        'type',
    ];

    /**
     * O nome bate com a FK e com o que o dashboard carrega. A relação existia
     * só como `subscription()`, e `with('congressSubscription.church')` no
     * dashboard derrubava a página de todo mundo que era delegado de verdade.
     */
    public function congressSubscription(): BelongsTo
    {
        return $this->belongsTo(CongressSubscription::class, 'congress_subscription_id');
    }

    /** Apelido mantido para o código que já chamava assim. */
    public function subscription(): BelongsTo
    {
        return $this->congressSubscription();
    }

    /**
     * Preenchido quando o delegado já tem conta no portal. Continua nullable
     * porque a UMP cadastra a delegação antes de o jovem criar o acesso — daí
     * o e-mail seguir sendo guardado e servir de "vale" até a conta existir.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    protected static function booted(): void
    {
        // A UMP digita o e-mail; se já houver conta, o vínculo é fechado na
        // hora. O e-mail sozinho não serve de chave permanente: a pessoa pode
        // trocá-lo no perfil e sumiria com a própria inscrição de delegado.
        static::saving(function (self $delegate) {
            if ($delegate->user_id === null && filled($delegate->email)) {
                $delegate->user_id = User::whereRaw('LOWER(email) = ?', [mb_strtolower($delegate->email)])->value('id');
            }
        });
    }

    /**
     * Chamado quando uma conta nova aparece: amarra as delegações que a UMP já
     * tinha cadastrado com aquele e-mail.
     */
    public static function linkToNewUser(User $user): void
    {
        static::whereNull('user_id')
            ->whereRaw('LOWER(email) = ?', [mb_strtolower($user->email)])
            ->update(['user_id' => $user->id]);
    }
}
