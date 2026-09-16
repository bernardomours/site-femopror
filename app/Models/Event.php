<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Event extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'title',
        'slug',
        'description',
        'event_date',
        'opening_date',
        'location',
        'price',
        'requires_receipt',
        'custom_fields',
        'status',
        'is_congress',
        'image',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'id' => 'integer',
            'event_date' => 'datetime',
            'opening_date' => 'datetime',
            'price' => 'decimal:2',
            'requires_receipt' => 'boolean',
            'is_congress' => 'boolean',
            'custom_fields' => 'array',
        ];
    }

    public function registrations(): HasMany
    {
        return $this->hasMany(Registration::class);
    }

    public function congressSubscriptions(): HasMany
    {
        return $this->hasMany(CongressSubscription::class);
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', 'published');
    }

    /**
     * Já abriu a janela de inscrição? `opening_date` em branco significa "abre
     * junto com a publicação".
     */
    public function registrationHasOpened(): bool
    {
        return $this->opening_date === null || now()->greaterThanOrEqualTo($this->opening_date);
    }

    /**
     * A pergunta que toda tela de inscrição precisa fazer antes de gravar.
     * Rascunho e encerrado não aceitam inscrição — nem pela URL direta.
     */
    public function acceptsRegistrations(): bool
    {
        return $this->status === 'published' && $this->registrationHasOpened();
    }

    public function isFree(): bool
    {
        return (float) $this->price <= 0;
    }

    /**
     * Comprovante só é exigido quando a inscrição custa alguma coisa. Evento
     * gratuito pedindo PIX era um beco sem saída no formulário.
     */
    public function requiresReceipt(): bool
    {
        return $this->requires_receipt && ! $this->isFree();
    }

    /**
     * Normaliza `custom_fields` para uso na tela e no cálculo do preço.
     *
     * Cada pergunta ganha uma chave estável derivada do texto (`key`), usada
     * para indexar as respostas no formulário. As opções voltam já separadas
     * em rótulo e acréscimo, para que o preço não precise ser reextraído por
     * regex do que o cliente mandou de volta.
     *
     * @return array<int, array{key: string, question: string, type: string, options: array<int, array{label: string, surcharge: float}>}>
     */
    public function customFieldDefinitions(): array
    {
        $definicoes = [];

        foreach ($this->custom_fields ?? [] as $indice => $campo) {
            $pergunta = trim((string) ($campo['question'] ?? ''));

            if ($pergunta === '') {
                continue;
            }

            $tipo = $campo['type'] ?? 'text';

            if (! in_array($tipo, ['text', 'select', 'checkbox'], true)) {
                $tipo = 'text';
            }

            $definicoes[] = [
                'key' => $indice.'-'.(Str::slug($pergunta) ?: 'campo'),
                'question' => $pergunta,
                'type' => $tipo,
                'options' => $tipo === 'text' ? [] : self::parseOptions($campo['options'] ?? ''),
            ];
        }

        return $definicoes;
    }

    /**
     * "Camisa P (+30,00), Camisa M (+30)" vira rótulo + acréscimo.
     * O acréscimo sai daqui e só daqui: nunca do que o navegador devolveu.
     *
     * A separação NÃO é um explode(',') simples: a vírgula decimal do preço
     * mora dentro dos parênteses. Com o explode, "M (+30,00)" virava duas
     * opções — "M (+30" e "00)" — e o acréscimo sumia. Quem escrevesse o valor
     * do jeito brasileiro perdia os 30 reais sem nenhum aviso.
     *
     * @return array<int, array{label: string, surcharge: float}>
     */
    public static function parseOptions(string $bruto): array
    {
        $opcoes = [];

        // Vírgula que não está dentro de parênteses.
        $pedacos = preg_split('/,(?![^(]*\))/', $bruto) ?: [];

        foreach ($pedacos as $pedaco) {
            $rotulo = trim($pedaco);

            if ($rotulo === '') {
                continue;
            }

            $acrescimo = 0.0;

            if (preg_match('/\(\+?[^0-9]*(\d+(?:[.,]\d+)?)[^)]*\)/', $rotulo, $captura)) {
                $acrescimo = (float) str_replace(',', '.', $captura[1]);
            }

            $opcoes[] = ['label' => $rotulo, 'surcharge' => $acrescimo];
        }

        return $opcoes;
    }
}
