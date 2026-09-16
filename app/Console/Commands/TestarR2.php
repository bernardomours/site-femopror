<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * Prova que o Cloudflare R2 está conectado, do jeito que o sistema usa.
 *
 * Não basta "conseguiu gravar": o desenho dos comprovantes depende de link
 * assinado que expira. Se o upload funciona mas a URL assinada não, o painel
 * mostra o botão "Ver PIX" e ele dá erro — o pior momento para descobrir.
 * Por isso o teste vai até baixar o arquivo pela URL gerada.
 */
class TestarR2 extends Command
{
    protected $signature = 'femopror:testar-r2 {--disk=r2 : Disco a testar}';

    protected $description = 'Verifica a conexão com o Cloudflare R2 (escrita, leitura, link assinado e remoção)';

    public function handle(): int
    {
        $disco = $this->option('disk');

        $this->newLine();
        $this->line("Testando o disco <options=bold>[{$disco}]</>");
        $this->newLine();

        if (! $this->conferirConfiguracao($disco)) {
            return self::FAILURE;
        }

        $caminho = 'testes/conexao-'.Str::random(12).'.txt';
        $conteudo = 'ok '.now()->toDateTimeString();

        try {
            $this->passo('Enviando arquivo de teste');
            Storage::disk($disco)->put($caminho, $conteudo);
            $this->ok("gravado em {$caminho}");

            $this->passo('Lendo de volta');
            $lido = Storage::disk($disco)->get($caminho);

            if ($lido !== $conteudo) {
                $this->falhou('o conteúdo lido não bate com o enviado');

                return self::FAILURE;
            }
            $this->ok('conteúdo confere');

            $this->passo('Gerando link assinado (30 min)');
            $url = Storage::disk($disco)->temporaryUrl($caminho, now()->addMinutes(30));
            $this->ok(Str::limit($url, 80));

            $this->passo('Baixando pelo link assinado');
            $baixado = @file_get_contents($url);

            if ($baixado === false) {
                $this->falhou('o link assinado não respondeu — o painel não conseguiria mostrar o comprovante');

                return self::FAILURE;
            }

            if ($baixado !== $conteudo) {
                $this->falhou('o link assinado respondeu, mas com conteúdo diferente');

                return self::FAILURE;
            }
            $this->ok('baixou o conteúdo certo');

            $this->passo('Conferindo que o bucket NÃO é público');
            $this->conferirPrivacidade($disco, $url);

            $this->passo('Removendo o arquivo de teste');
            Storage::disk($disco)->delete($caminho);
            $this->ok('removido');

        } catch (Throwable $e) {
            $this->falhou($e->getMessage());
            $this->newLine();
            $this->explicar($e->getMessage());

            // Não deixa lixo para trás se o erro veio depois da gravação.
            try {
                Storage::disk($disco)->delete($caminho);
            } catch (Throwable) {
                //
            }

            return self::FAILURE;
        }

        $this->newLine();
        $this->info('Tudo certo. O R2 está pronto para receber os comprovantes.');
        $this->line('Agora é só virar a chave: <options=bold>UPLOADS_DISK='.$disco.'</>');

        if (config('femopror.uploads.disk') !== $disco) {
            $this->newLine();
            $this->warn('Atenção: UPLOADS_DISK ainda está como ['.config('femopror.uploads.disk').'].');
            $this->line('Se já houver comprovante gravado, rode antes:');
            $this->line('  php artisan femopror:migrar-arquivos '.config('femopror.uploads.disk')." {$disco} --dry-run");
        }

        return self::SUCCESS;
    }

    private function conferirConfiguracao(string $disco): bool
    {
        $config = config("filesystems.disks.{$disco}");

        if (! $config) {
            $this->falhou("o disco [{$disco}] não existe em config/filesystems.php");

            return false;
        }

        $faltando = [];

        foreach (['key' => 'R2_ACCESS_KEY_ID', 'secret' => 'R2_SECRET_ACCESS_KEY', 'bucket' => 'R2_BUCKET', 'endpoint' => 'R2_ENDPOINT'] as $chave => $env) {
            if (blank($config[$chave] ?? null)) {
                $faltando[] = $env;
            }
        }

        if ($faltando !== []) {
            $this->falhou('faltam variáveis no .env: '.implode(', ', $faltando));

            return false;
        }

        $endpoint = (string) $config['endpoint'];
        $bucket = (string) $config['bucket'];

        // Erro comum: colar o endereço já com o bucket no fim. Como o disco usa
        // path-style, o bucket entra de novo e vira .../bucket/bucket/arquivo.
        if (str_contains($endpoint, "/{$bucket}")) {
            $this->falhou("o R2_ENDPOINT não deve incluir o nome do bucket ({$bucket}).");
            $this->line('  Use só: https://<ACCOUNT_ID>.r2.cloudflarestorage.com');

            return false;
        }

        if (! str_starts_with($endpoint, 'https://')) {
            $this->falhou('o R2_ENDPOINT precisa começar com https://');

            return false;
        }

        $this->ok('configuração presente · bucket ['.$bucket.']');

        return true;
    }

    /**
     * O bucket tem que estar fechado. Se o mesmo objeto abrir sem a assinatura,
     * o acesso público está ligado — e comprovante bancário fica exposto.
     */
    private function conferirPrivacidade(string $disco, string $urlAssinada): void
    {
        $semAssinatura = strtok($urlAssinada, '?');

        $contexto = stream_context_create(['http' => ['ignore_errors' => true, 'timeout' => 10]]);
        $resposta = @file_get_contents($semAssinatura, false, $contexto);

        $status = 0;
        foreach ($http_response_header ?? [] as $linha) {
            if (preg_match('#HTTP/\S+\s+(\d{3})#', $linha, $m)) {
                $status = (int) $m[1];
                break;
            }
        }

        if ($status >= 200 && $status < 300 && $resposta !== false) {
            $this->components->error('O bucket está PÚBLICO: o arquivo abriu sem assinatura.');
            $this->line('  Desligue "Public access" / o domínio público no painel do R2.');

            return;
        }

        $this->ok('bucket privado (sem assinatura, o acesso é negado)');
    }

    private function explicar(string $erro): void
    {
        $pistas = [
            'InvalidAccessKeyId' => 'O Access Key ID está errado. Confira se copiou o do token do R2 (não o da conta Cloudflare).',
            'SignatureDoesNotMatch' => 'O Secret Access Key está errado ou veio com espaço/quebra de linha. Gere o token de novo se não tiver guardado.',
            'NoSuchBucket' => 'O bucket não existe com esse nome, ou o token não foi liberado para ele.',
            'AccessDenied' => 'O token não tem permissão de escrita. Recrie com "Object Read & Write" nesse bucket.',
            'could not be resolved' => 'O endpoint não resolve. Confira o ACCOUNT_ID em https://<ACCOUNT_ID>.r2.cloudflarestorage.com',
            'cURL error 6' => 'O endpoint não resolve. Confira o ACCOUNT_ID no R2_ENDPOINT.',
            'cURL error 7' => 'Não conseguiu conectar. Pode ser firewall de saída do servidor bloqueando HTTPS.',
            'NotImplemented' => 'Costuma ser o checksum do SDK. A config do disco já usa request_checksum_calculation=when_required — confira se o config:cache não está servindo uma versão antiga (php artisan optimize:clear).',
        ];

        foreach ($pistas as $trecho => $explicacao) {
            if (str_contains($erro, $trecho)) {
                $this->line('  <fg=yellow>Provável causa:</> '.$explicacao);

                return;
            }
        }

        $this->line('  <fg=gray>Se o erro não estiver claro, me mande esta saída (sem as chaves).</>');
    }

    private function passo(string $texto): void
    {
        $this->line("  <fg=gray>›</> {$texto}...");
    }

    private function ok(string $texto): void
    {
        $this->line("    <fg=green>✓</> {$texto}");
    }

    private function falhou(string $texto): void
    {
        $this->line("    <fg=red>✗</> {$texto}");
    }
}
