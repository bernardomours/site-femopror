<?php

namespace App\Providers;

use Carbon\Carbon;
use Illuminate\Support\Facades\URL;
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
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->hardenProduction();
        $this->enableTemporaryUrlsForPrivateFiles();

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
