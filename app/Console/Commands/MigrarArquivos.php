<?php

namespace App\Console\Commands;

use App\Models\CongressDocument;
use App\Models\CongressSubscription;
use App\Models\Registration;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Copia os arquivos já enviados de um disco para outro.
 *
 * Trocar `UPLOADS_DISK=local` por `r2` muda só para onde os PRÓXIMOS arquivos
 * vão. O que já estava gravado continua no disco antigo, e o painel passaria a
 * procurá-lo no lugar errado — comprovante sumido, sem erro visível.
 *
 * Copia, não move: o original fica onde está até você conferir que deu certo.
 */
class MigrarArquivos extends Command
{
    protected $signature = 'femopror:migrar-arquivos
                            {origem : Disco de onde ler (ex: local)}
                            {destino : Disco para onde copiar (ex: r2)}
                            {--dry-run : Só lista o que seria copiado}';

    protected $description = 'Copia comprovantes e documentos já enviados de um disco para outro';

    public function handle(): int
    {
        $origem = $this->argument('origem');
        $destino = $this->argument('destino');

        if ($origem === $destino) {
            $this->error('Origem e destino são o mesmo disco.');

            return self::FAILURE;
        }

        foreach ([$origem, $destino] as $disco) {
            if (! array_key_exists($disco, config('filesystems.disks', []))) {
                $this->error("O disco [{$disco}] não existe em config/filesystems.php.");

                return self::FAILURE;
            }
        }

        $seco = (bool) $this->option('dry-run');

        if ($seco) {
            $this->warn('Modo simulação: nada será copiado.');
        }

        $copiados = 0;
        $faltando = 0;
        $falhas = 0;

        foreach ($this->caminhos() as $caminho) {
            if (! Storage::disk($origem)->exists($caminho)) {
                $this->line("  <fg=yellow>ausente na origem</> {$caminho}");
                $faltando++;

                continue;
            }

            if (Storage::disk($destino)->exists($caminho)) {
                $this->line("  <fg=gray>já existe no destino</> {$caminho}");

                continue;
            }

            if ($seco) {
                $this->line("  copiaria {$caminho}");
                $copiados++;

                continue;
            }

            try {
                // Stream, para não carregar um PDF inteiro na memória.
                Storage::disk($destino)->writeStream($caminho, Storage::disk($origem)->readStream($caminho));
                $this->line("  <fg=green>copiado</> {$caminho}");
                $copiados++;
            } catch (Throwable $e) {
                $this->line("  <fg=red>falhou</> {$caminho} — {$e->getMessage()}");
                $falhas++;
            }
        }

        $this->newLine();
        $this->info("Copiados: {$copiados} · Ausentes na origem: {$faltando} · Falhas: {$falhas}");

        if ($falhas > 0) {
            $this->warn('Houve falhas. NÃO troque o UPLOADS_DISK enquanto elas não forem resolvidas.');

            return self::FAILURE;
        }

        if (! $seco) {
            $this->line("Agora pode virar a chave: UPLOADS_DISK={$destino}");
        }

        return self::SUCCESS;
    }

    /** Todos os caminhos de arquivo guardados no banco. */
    private function caminhos(): iterable
    {
        yield from Registration::whereNotNull('receipt_path')->pluck('receipt_path');
        yield from CongressSubscription::whereNotNull('receipt_path')->pluck('receipt_path');
        yield from CongressDocument::whereNotNull('file_path')->pluck('file_path');
    }
}
