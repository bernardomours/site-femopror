<?php

namespace App\Console\Commands;

use App\Mail\InscricaoRecebida;
use App\Models\Church;
use App\Models\Event;
use App\Models\Registration;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Manda um e-mail de inscrição de verdade, para conferir antes de a Copa abrir.
 *
 * Usa o mesmo Mailable que o site usa — não um "teste 123". O que costuma
 * quebrar não é o envio em si, é o conteúdo: acento virando símbolo, valor
 * formatado errado, link apontando para localhost. Isso só aparece olhando a
 * mensagem que chega na caixa de entrada.
 */
class TestarEmail extends Command
{
    protected $signature = 'femopror:testar-email {para : E-mail de destino}';

    protected $description = 'Envia um e-mail de inscrição de teste para conferir a configuração de SMTP';

    public function handle(): int
    {
        $para = $this->argument('para');

        if (! filter_var($para, FILTER_VALIDATE_EMAIL)) {
            $this->components->error("[{$para}] não é um e-mail válido.");

            return self::FAILURE;
        }

        $mailer = config('mail.default');
        $de = config('mail.from.address');

        $this->newLine();
        $this->line("  Mailer:  <options=bold>{$mailer}</>");
        $this->line("  De:      {$de}");
        $this->line("  Para:    {$para}");
        $this->line('  APP_URL: '.config('app.url'));
        $this->newLine();

        if (in_array($mailer, ['log', 'array'], true)) {
            $this->components->warn("MAIL_MAILER={$mailer}: nada sai de verdade.");
            $this->line('  Com [log] a mensagem vai para storage/logs/laravel.log.');
            $this->line('  Para enviar de verdade, configure SMTP no .env (veja o .env.example).');
            $this->newLine();

            if (! $this->confirm('Quer gerar mesmo assim, só para conferir o conteúdo?', true)) {
                return self::FAILURE;
            }
        }

        if (str_contains((string) config('app.url'), 'localhost') && $mailer === 'smtp') {
            $this->components->warn('APP_URL aponta para localhost: os botões do e-mail vão levar para lá.');
        }

        // Um mailer SMTP autenticado costuma exigir que o remetente seja a
        // própria conta. Gmail reescreve ou recusa quando não bate.
        $usuario = config('mail.mailers.smtp.username');
        if ($mailer === 'smtp' && filled($usuario) && $usuario !== 'null' && filter_var($usuario, FILTER_VALIDATE_EMAIL) && $usuario !== $de) {
            $this->components->warn("MAIL_FROM_ADDRESS ({$de}) é diferente do MAIL_USERNAME ({$usuario}).");
            $this->line('  O Gmail reescreve ou recusa quando não são o mesmo endereço.');
        }

        $inscricao = $this->inscricaoDeExemplo($para);

        try {
            $this->line('  <fg=gray>›</> enviando...');
            Mail::to($para)->send(new InscricaoRecebida($inscricao));
        } catch (Throwable $e) {
            $this->newLine();
            $this->components->error('Falhou: '.$e->getMessage());
            $this->explicar($e->getMessage());

            return self::FAILURE;
        }

        $this->newLine();

        if ($mailer === 'log') {
            $this->info('Gerado no log: storage/logs/laravel.log (nada foi enviado).');
        } else {
            $this->info("Enviado para {$para}.");
            $this->line('  Confira a caixa de entrada E a de spam.');
            $this->line('  Olhe se os acentos aparecem certo e se o botão leva para o site (não para localhost).');
        }

        return self::SUCCESS;
    }

    /**
     * Uma inscrição só em memória: testar e-mail não pode sujar o banco com
     * inscrição de mentira, que depois apareceria na lista da tesouraria.
     */
    private function inscricaoDeExemplo(string $para): Registration
    {
        $inscricao = new Registration([
            'name' => 'Participante de Teste',
            'email' => $para,
            'phone' => '84999999999',
            'payment_status' => 'pending',
            'amount_paid' => 60,
            'custom_answers' => [
                'Quais esportes você vai disputar?' => ['Futsal', 'Xadrez'],
                'Qual o tamanho da sua camisa?' => 'G',
            ],
        ]);

        $inscricao->setRelation('event', Event::query()->latest('event_date')->first() ?? new Event([
            'title' => 'Copa FEMOPROR',
            'event_date' => now()->addMonth(),
            'location' => 'Ginásio da IP Central de Mossoró',
        ]));

        $inscricao->setRelation('church', Church::query()->first() ?? new Church(['name' => 'Igreja Presbiteriana Central']));

        return $inscricao;
    }

    private function explicar(string $erro): void
    {
        $pistas = [
            'Username and Password not accepted' => 'O Gmail recusou a senha. Ela precisa ser uma "Senha de app" (16 letras, sem espaços), não a senha normal da conta — e a verificação em duas etapas precisa estar ligada.',
            'authentication failed' => 'Usuário ou senha do SMTP recusados. No Gmail, use uma Senha de app.',
            'Connection could not be established' => 'Não conseguiu conectar no servidor SMTP. Confira MAIL_HOST e MAIL_PORT; em hospedagem compartilhada, a saída na porta 587 às vezes é bloqueada.',
            'Connection timed out' => 'O servidor SMTP não respondeu. Provável bloqueio de saída na porta — teste a 465 com MAIL_SCHEME=smtps.',
            'stream_socket_enable_crypto' => 'Falha no TLS. Confira MAIL_SCHEME (tls na porta 587, smtps na 465).',
            'Sender address rejected' => 'O servidor recusou o remetente. MAIL_FROM_ADDRESS precisa ser um endereço que essa conta pode usar.',
            'Domain not found' => 'O domínio do MAIL_HOST não resolve. Confira se não há erro de digitação.',
        ];

        foreach ($pistas as $trecho => $explicacao) {
            if (stripos($erro, $trecho) !== false) {
                $this->line('  <fg=yellow>Provável causa:</> '.$explicacao);

                return;
            }
        }
    }
}
