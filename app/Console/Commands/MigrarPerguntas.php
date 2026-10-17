<?php

namespace App\Console\Commands;

use App\Models\Event;
use App\Models\Registration;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Converte as perguntas do formato antigo (string com o preço dentro do
 * rótulo) para o novo (rótulo e valor em campos próprios).
 *
 *     Futsal(R$5,00)   →   label: "Futsal", surcharge: 5.00
 *
 * E converte junto as **respostas já gravadas**, porque elas guardam o rótulo
 * por extenso: sem isso, "Futsal(R$5,00)" deixaria de casar com a opção
 * "Futsal" e o gráfico do painel racharia em duas colunas para a mesma coisa.
 *
 * Roda à mão, com `--dry-run` primeiro. Não é migration de banco de propósito:
 * mexe em conteúdo, o resultado depende de como cada pessoa digitou os rótulos
 * e merece um par de olhos antes de aplicar.
 */
class MigrarPerguntas extends Command
{
    protected $signature = 'femopror:migrar-perguntas
                            {--dry-run : Mostra o que mudaria, sem gravar nada}
                            {--event= : Converte só um evento, pelo id}';

    protected $description = 'Converte as opções das perguntas para o formato com rótulo e valor separados';

    public function handle(): int
    {
        $simulacao = (bool) $this->option('dry-run');

        $eventos = Event::query()
            ->whereNotNull('custom_fields')
            ->when($this->option('event'), fn ($q, $id) => $q->whereKey($id))
            ->orderBy('id')
            ->get();

        if ($eventos->isEmpty()) {
            $this->info('Nenhum evento com perguntas cadastradas.');

            return self::SUCCESS;
        }

        /*
         * O que mudaria é calculado ANTES de pedir qualquer coisa. Perguntar
         * "fez backup?" para depois anunciar que não havia nada a fazer é o
         * tipo de atrito que faz a pessoa parar de rodar o comando.
         */
        $pendentes = $eventos
            ->map(fn (Event $evento) => ['evento' => $evento] + $this->converter($evento))
            ->filter(fn (array $r) => $r['renomeadas'] !== [] || $r['mudouFormato'])
            ->values();

        if ($pendentes->isEmpty()) {
            $this->info('Tudo já está no formato novo. Nada a fazer.');

            return self::SUCCESS;
        }

        if ($simulacao) {
            $this->warn('SIMULAÇÃO — nada será gravado.');
            $this->newLine();
        } else {
            $this->warn('Isto altera perguntas e respostas já gravadas.');

            if (! $this->confirm('Você fez backup do banco?')) {
                $this->error('Faça o backup e rode de novo.');

                return self::FAILURE;
            }
        }

        $eventosMexidos = 0;
        $respostasMexidas = 0;
        $suspeitas = [];

        foreach ($pendentes as $resultado) {
            $evento = $resultado['evento'];
            $eventosMexidos++;

            $this->line("<fg=cyan>[{$evento->id}]</> {$evento->title}");

            foreach ($resultado['renomeadas'] as $de => $info) {
                $this->line(sprintf('    "%s"  →  "%s"  + R$ %s', $de, $info['para'], number_format($info['valor'], 2, ',', '.')));

                if ($info['suspeita']) {
                    $suspeitas[] = "[{$evento->id}] \"{$de}\" → \"{$info['para']}\"";
                }
            }

            $afetadas = $simulacao
                ? $this->contarRespostas($evento, $resultado['renomeadas'])
                : $this->aplicar($evento, $resultado);

            $respostasMexidas += $afetadas;

            $this->line("    <fg=gray>inscrições com resposta a ajustar: {$afetadas}</>");
            $this->newLine();
        }

        if ($suspeitas !== []) {
            /*
             * O parêntese com número sempre foi lido como preço — inclusive
             * quando não era. "Camiseta (tamanho 2)" já virava acréscimo de
             * R$ 2,00 antes desta conversão; aqui isso só fica visível. Por
             * isso a lista: é para alguém olhar, não para o comando adivinhar.
             */
            $this->newLine();
            $this->warn('Confira estes rótulos — o número entre parênteses pode não ser preço:');

            foreach ($suspeitas as $linha) {
                $this->line("    {$linha}");
            }
        }

        $this->newLine();
        $this->info(sprintf(
            '%s: %d evento(s), %d inscrição(ões) com respostas.',
            $simulacao ? 'Seriam convertidos' : 'Convertidos',
            $eventosMexidos,
            $respostasMexidas,
        ));

        if ($simulacao) {
            $this->line('Rode sem --dry-run para aplicar.');
        }

        return self::SUCCESS;
    }

    /**
     * @return array{campos: array<int, array<string, mixed>>, renomeadas: array<string, array{para: string, valor: float, suspeita: bool}>, mudouFormato: bool}
     */
    private function converter(Event $evento): array
    {
        $campos = [];
        $renomeadas = [];
        $mudouFormato = false;

        foreach ($evento->custom_fields ?? [] as $campo) {
            $opcoesBrutas = $campo['options'] ?? [];

            if (! is_string($opcoesBrutas)) {
                // Já está no formato novo.
                $campos[] = $campo;

                continue;
            }

            $mudouFormato = true;
            $novas = [];

            foreach (Event::parseOptions($opcoesBrutas) as $opcao) {
                $limpo = self::limparRotulo($opcao['label']);

                if ($limpo !== $opcao['label']) {
                    $renomeadas[$opcao['label']] = [
                        'para' => $limpo,
                        'valor' => $opcao['surcharge'],
                        // Sem "R$" nem "+" no parêntese, o número pode ser
                        // parte do nome e não preço.
                        'suspeita' => ! preg_match('/\(\s*(R\$|\+)/iu', $opcao['label']),
                    ];
                }

                $novas[] = ['label' => $limpo, 'surcharge' => $opcao['surcharge']];
            }

            $campo['options'] = $novas;
            $campos[] = $campo;
        }

        return ['campos' => $campos, 'renomeadas' => $renomeadas, 'mudouFormato' => $mudouFormato];
    }

    /**
     * Tira do rótulo o parêntese que carrega o preço.
     *
     * Só mexe quando há dígito lá dentro: "Diretoria ou secretário (FEMOPROR)"
     * fica intacto, que é como o sistema já tratava — parêntese sem número
     * nunca foi acréscimo.
     */
    public static function limparRotulo(string $rotulo): string
    {
        $limpo = preg_replace('/\s*\([^)]*\d[^)]*\)\s*$/u', '', $rotulo);

        return trim($limpo) !== '' ? trim($limpo) : $rotulo;
    }

    /** @param  array<string, array{para: string, valor: float, suspeita: bool}>  $renomeadas */
    private function contarRespostas(Event $evento, array $renomeadas): int
    {
        if ($renomeadas === []) {
            return 0;
        }

        return Registration::query()
            ->where('event_id', $evento->id)
            ->whereNotNull('custom_answers')
            ->get(['id', 'custom_answers'])
            ->filter(fn (Registration $i) => $this->reescrever($i->custom_answers ?? [], $renomeadas) !== ($i->custom_answers ?? []))
            ->count();
    }

    /** @param  array{campos: array<int, array<string, mixed>>, renomeadas: array<string, array{para: string, valor: float, suspeita: bool}>}  $resultado */
    private function aplicar(Event $evento, array $resultado): int
    {
        return DB::transaction(function () use ($evento, $resultado): int {
            $evento->update(['custom_fields' => $resultado['campos']]);

            if ($resultado['renomeadas'] === []) {
                return 0;
            }

            $ajustadas = 0;

            Registration::query()
                ->where('event_id', $evento->id)
                ->whereNotNull('custom_answers')
                ->get(['id', 'custom_answers'])
                ->each(function (Registration $inscricao) use ($resultado, &$ajustadas) {
                    $antes = $inscricao->custom_answers ?? [];
                    $depois = $this->reescrever($antes, $resultado['renomeadas']);

                    if ($depois === $antes) {
                        return;
                    }

                    $inscricao->update(['custom_answers' => $depois]);
                    $ajustadas++;
                });

            return $ajustadas;
        });
    }

    /**
     * Troca o rótulo antigo pelo novo dentro das respostas gravadas.
     *
     * @param  array<string, mixed>  $respostas
     * @param  array<string, array{para: string, valor: float, suspeita: bool}>  $renomeadas
     * @return array<string, mixed>
     */
    private function reescrever(array $respostas, array $renomeadas): array
    {
        foreach ($respostas as $pergunta => $valor) {
            if (is_array($valor)) {
                $respostas[$pergunta] = array_map(
                    fn ($v) => is_string($v) ? ($renomeadas[$v]['para'] ?? $v) : $v,
                    $valor,
                );

                continue;
            }

            if (is_string($valor) && isset($renomeadas[$valor])) {
                $respostas[$pergunta] = $renomeadas[$valor]['para'];
            }
        }

        return $respostas;
    }
}
