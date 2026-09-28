<?php

namespace App\Support;

use App\Models\Delegate;
use App\Models\Event;
use App\Models\Registration;
use Illuminate\Support\Collection;

/**
 * Os números de acompanhamento de um evento.
 *
 * O ponto desta classe é **não** ter nada específico de evento nenhum. A Copa
 * pergunta modalidade e restrição alimentar; o congresso pergunta outra coisa;
 * o próximo evento vai perguntar o que a diretoria inventar. Se cada um virasse
 * um widget escrito à mão, o painel nasceria desatualizado — e quem cadastra o
 * evento não mexe em código.
 *
 * Então a lista de gráficos é **derivada do próprio evento**: as perguntas
 * personalizadas (`custom_fields`) viram um gráfico cada, automaticamente. As
 * dimensões que existem em qualquer evento (igreja, e a delegação quando é
 * congresso) entram fixas.
 *
 * Fica aqui, e não no widget, pelo mesmo motivo das outras contas do projeto:
 * uma implementação só, testável sem subir o Filament.
 */
class PanoramaDoEvento
{
    private function __construct(
        public readonly Event $evento,
        private readonly Collection $inscricoes,
    ) {}

    public static function para(Event $evento): self
    {
        /*
         * Só as colunas usadas. `custom_answers` é JSON e a contagem acontece
         * em PHP: agrupar por chave de JSON em SQL não é portátil entre o
         * SQLite do dev e o MySQL de produção, e o volume aqui é de centenas de
         * linhas por evento, não de milhões.
         */
        $inscricoes = Registration::query()
            ->where('event_id', $evento->id)
            ->get(['id', 'church_id', 'payment_status', 'amount_paid', 'receipt_path', 'custom_answers']);

        return new self($evento, $inscricoes);
    }

    // ------------------------------------------------------------- totais

    /** @return array{inscritos: int, confirmados: int, aguardando: int, arrecadado: float, a_receber: float} */
    public function totais(): array
    {
        $confirmadas = $this->inscricoes->filter(fn (Registration $i) => $i->payment_status === 'paid');
        $canceladas = $this->inscricoes->filter(fn (Registration $i) => $i->payment_status === 'failed');

        return [
            'inscritos' => $this->inscricoes->count(),
            'confirmados' => $confirmadas->count(),
            'aguardando' => $this->inscricoes->count() - $confirmadas->count() - $canceladas->count(),
            'arrecadado' => (float) $confirmadas->sum(fn (Registration $i) => $i->valorCobrado()),
            // O que ainda não entrou no caixa: nem confirmado, nem cancelado.
            'a_receber' => (float) $this->inscricoes
                ->reject(fn (Registration $i) => in_array($i->payment_status, ['paid', 'failed'], true))
                ->sum(fn (Registration $i) => $i->valorCobrado()),
        ];
    }

    // ------------------------------------------------------------ gráficos

    /**
     * Cada item é um gráfico de colunas pronto para a tela.
     *
     * @return list<array{id: string, titulo: string, subtitulo: string, rotulos: list<string>, valores: list<int>}>
     */
    public function graficos(): array
    {
        $graficos = [$this->porIgreja()];

        if ($this->evento->is_congress) {
            $graficos[] = $this->porTipoDeDelegado();
        }

        foreach ($this->evento->customFieldDefinitions() as $campo) {
            $graficos[] = $this->porPergunta($campo);
        }

        // Gráfico sem nenhuma resposta não comunica nada — só ocupa espaço e
        // faz o painel parecer quebrado.
        return array_values(array_filter($graficos, fn (array $g) => $g['valores'] !== []));
    }

    private function porIgreja(): array
    {
        $nomes = \App\Models\Church::whereIn('id', $this->inscricoes->pluck('church_id')->filter()->unique())
            ->pluck('name', 'id');

        $contagem = $this->inscricoes
            ->countBy(fn (Registration $i) => $nomes[$i->church_id] ?? 'Sem igreja informada')
            ->sortDesc();

        return $this->montar(
            id: 'igreja',
            titulo: 'Inscritos por igreja',
            subtitulo: 'De onde vem cada participante',
            contagem: $contagem,
        );
    }

    /**
     * Quem é delegado oficial e quem é visitante. Só existe em congresso: a
     * delegação é credenciada pela UMP local, não pelo site.
     */
    private function porTipoDeDelegado(): array
    {
        $contagem = Delegate::query()
            ->whereHas('congressSubscription', fn ($q) => $q->where('event_id', $this->evento->id))
            ->get(['type'])
            ->countBy(fn (Delegate $d) => match ($d->type) {
                'delegado' => 'Delegados oficiais',
                'visitante' => 'Visitantes pela UMP',
                default => (string) $d->type,
            })
            ->sortDesc();

        return $this->montar(
            id: 'delegacao',
            titulo: 'Delegação oficial',
            subtitulo: 'Credenciados pelas UMPs locais',
            contagem: $contagem,
        );
    }

    /**
     * Um gráfico para uma pergunta personalizada.
     *
     * `custom_answers` é gravado **com o texto da pergunta como chave**, não com
     * a `key` estável. Renomear a pergunta depois de alguém se inscrever deixa
     * as respostas antigas órfãs — por isso a contagem parte do que está
     * gravado, e não da lista de opções: opção renomeada continua aparecendo,
     * com o rótulo que a pessoa realmente escolheu.
     *
     * @param  array{key: string, question: string, type: string, options: array<int, array{label: string, surcharge: float}>}  $campo
     */
    private function porPergunta(array $campo): array
    {
        $respostas = $this->inscricoes
            ->flatMap(function (Registration $inscricao) use ($campo) {
                $valor = ((array) $inscricao->custom_answers)[$campo['question']] ?? null;

                // Checkbox chega como lista; select e texto, como string.
                return collect(is_array($valor) ? $valor : [$valor])
                    ->map(fn ($v) => trim((string) $v))
                    ->filter();
            })
            ->countBy()
            ->sortDesc();

        /*
         * Campo de texto livre é resposta única por pessoa: o gráfico inteiro
         * viraria uma coluna de altura 1 para cada inscrito. As mais frequentes
         * é o que responde "o que aparece com frequência?" — que é a pergunta
         * real de quem organiza (alergia repetida, pedido repetido).
         */
        $limitado = $campo['type'] === 'text';

        if ($limitado) {
            $respostas = $respostas->take(10);
        }

        $semResposta = $this->inscricoes->count() - $this->inscricoes
            ->filter(fn (Registration $i) => filled(((array) $i->custom_answers)[$campo['question']] ?? null))
            ->count();

        $subtitulo = $limitado
            ? 'As 10 respostas mais frequentes'
            : 'Respostas marcadas na inscrição';

        if ($semResposta > 0) {
            $subtitulo .= ' · '.$semResposta.' sem resposta';
        }

        return $this->montar(
            id: $campo['key'],
            titulo: $campo['question'],
            subtitulo: $subtitulo,
            contagem: $respostas,
        );
    }

    private function montar(string $id, string $titulo, string $subtitulo, Collection $contagem): array
    {
        return [
            'id' => $id,
            'titulo' => $titulo,
            'subtitulo' => $subtitulo,
            'rotulos' => $contagem->keys()->map(fn ($r) => (string) $r)->values()->all(),
            'valores' => $contagem->values()->map(fn ($v) => (int) $v)->values()->all(),
        ];
    }
}
