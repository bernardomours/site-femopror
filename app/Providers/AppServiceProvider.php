<?php

namespace App\Providers;

use Carbon\Carbon;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\URL;
use Livewire\Livewire;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->pinLivewireTemporaryUploadDisk();
    }

    /**
     * O arquivo em trânsito fica no disco local, nunca no bucket.
     *
     * O Livewire olha `filesystems.default` para decidir onde guardar o upload
     * temporário. Se isso apontar para um disco `s3` (o nosso R2), ele troca de
     * estratégia: o navegador passa a enviar o arquivo DIRETO para o bucket, por
     * URL pré-assinada. E aí o upload só funciona com CORS configurado no bucket
     * — que o R2 não traz por padrão. O navegador bloqueia o envio em silêncio, e
     * para quem está usando o sintoma é exatamente "escolhi o arquivo e não
     * aconteceu nada".
     *
     * Fixar em `local` remove essa dependência: o arquivo chega ao servidor pelo
     * mesmo caminho em desenvolvimento e em produção, e só vai para o R2 depois,
     * no `store()`, já pelo backend. O temporário é apagado pelo próprio Livewire.
     */
    private function pinLivewireTemporaryUploadDisk(): void
    {
        config([
            'livewire.temporary_file_upload.disk' => env('LIVEWIRE_TEMPORARY_FILE_UPLOAD_DISK', 'local'),
        ]);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->hardenProduction();
        $this->enableTemporaryUrlsForPrivateFiles();
        $this->throttleLivewireRequests();

        Carbon::setLocale(config('app.locale'));
    }

    /**
     * Duas coisas que não dá para confiar na configuração do servidor.
     */
    private function hardenProduction(): void
    {
        if (! $this->app->isProduction()) {
            return;
        }

        // Com APP_DEBUG ligado, qualquer exceção vira uma página com stack
        // trace e o conteúdo do .env — credenciais do banco inclusive.
        if (config('app.debug')) {
            throw new RuntimeException(
                'APP_DEBUG=true em produção expõe credenciais na tela de erro. Ajuste o .env antes de subir.'
            );
        }

        // Sem isso, um link http:// gerado em qualquer canto faz o navegador
        // mandar o cookie de sessão em texto claro.
        URL::forceScheme('https');
    }

    /**
     * Teto para o endpoint que executa as ações do Livewire.
     *
     * `/livewire/update` só vinha com `web` — nenhum limite. É por ele que
     * passam mount, mudança de campo e chamada de método, então sem teto um
     * script consegue martelar o banco à vontade. As ações de gravar já se
     * limitam por usuário; isto aqui é o teto por IP, folgado o bastante para
     * não atrapalhar quem está usando o painel de verdade (o Filament dispara
     * várias requisições por interação).
     *
     * O upload temporário tem limite próprio do Livewire (60/min).
     */
    private function throttleLivewireRequests(): void
    {
        Livewire::setUpdateRoute(
            fn ($handle) => Route::post('/livewire/update', $handle)
                ->middleware(['web', 'throttle:240,1'])
                ->name('livewire.update')
        );
    }

    /**
     * Comprovantes de PIX e relatórios das UMPs moram no disco privado
     * (`storage/app/private`), que o Laravel só serve com URL assinada.
     *
     * O driver local não sabe gerar essa URL sozinho: sem o callback abaixo,
     * `temporaryUrl()` lança exceção e o Filament cai no `url()` público. Aqui
     * ele passa a apontar para a rota `storage.local`, que valida a assinatura
     * e recusa o acesso depois que o link expira.
     */
    private function enableTemporaryUrlsForPrivateFiles(): void
    {
        Storage::disk('local')->buildTemporaryUrlsUsing(
            fn (string $path, $expiration, array $options = []) => URL::temporarySignedRoute(
                'storage.local',
                $expiration,
                [...$options, 'path' => $path],
                absolute: false,
            )
        );
    }
}
