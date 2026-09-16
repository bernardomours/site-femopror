<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Filesystem Disk
    |--------------------------------------------------------------------------
    |
    | Here you may specify the default filesystem disk that should be used
    | by the framework. The "local" disk, as well as a variety of cloud
    | based disks are available to your application for file storage.
    |
    */

    'default' => env('FILESYSTEM_DISK', 'local'),

    /*
    |--------------------------------------------------------------------------
    | Filesystem Disks
    |--------------------------------------------------------------------------
    |
    | Below you may configure as many filesystem disks as necessary, and you
    | may even configure multiple disks for the same driver. Examples for
    | most supported storage drivers are configured here for reference.
    |
    | Supported drivers: "local", "ftp", "sftp", "s3"
    |
    */

    'disks' => [

        'local' => [
            'driver' => 'local',
            'root' => storage_path('app/private'),
            'serve' => true,
            'throw' => false,
            'report' => false,
        ],

        'public' => [
            'driver' => 'local',
            'root' => storage_path('app/public'),
            'url' => rtrim(env('APP_URL', 'http://localhost'), '/').'/storage',
            'visibility' => 'public',
            'throw' => false,
            'report' => false,
        ],

        /*
         * Cloudflare R2. É S3-compatível, então usa o mesmo driver — o que muda
         * é o endpoint (a conta é um subdomínio de r2.cloudflarestorage.com) e
         * a região, que no R2 é sempre "auto".
         *
         * `visibility => private` é o ponto: comprovante de PIX não fica em
         * endereço público. O acesso sai por URL assinada (o R2 suporta as
         * presigned URLs do S3 nativamente), gerada só para quem está logado
         * no painel — igual ao que o disco `local` já fazia.
         */
        'r2' => [
            'driver' => 's3',
            'key' => env('R2_ACCESS_KEY_ID'),
            'secret' => env('R2_SECRET_ACCESS_KEY'),
            'region' => 'auto',
            'bucket' => env('R2_BUCKET'),
            // Tolera o endpoint copiado do painel com o bucket no fim — ver R2Endpoint.
            'endpoint' => \App\Support\R2Endpoint::normalize(env('R2_ENDPOINT'), env('R2_BUCKET')),
            'use_path_style_endpoint' => true,
            'visibility' => 'private',

            // Mesma razão do disco `s3`: reportar sim, lançar não.
            'throw' => false,
            'report' => true,

            /*
             * Desde a versão 3.3xx o SDK da AWS manda `x-amz-checksum-crc32` em
             * todo PutObject (`when_supported`, o padrão). O R2 não implementa
             * todos os algoritmos que a AWS assume, e a falha aparece como um
             * "NotImplemented" ou "InvalidRequest" genérico no upload — sem
             * nenhuma pista de que o problema é o checksum.
             *
             * `when_required` só calcula quando a operação realmente exige. O
             * upload continua íntegro: a assinatura SigV4 já cobre o conteúdo.
             */
            'request_checksum_calculation' => 'when_required',
            'response_checksum_validation' => 'when_required',
        ],

        /*
         * Neste projeto o disco `s3` é usado para falar com o Cloudflare R2
         * (que é S3-compatível), com as variáveis AWS_*. Ele tem os mesmos
         * ajustes do disco `r2` acima — os dois caminhos funcionam, escolha
         * pelo UPLOADS_DISK.
         */
        's3' => [
            'driver' => 's3',
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),

            /*
             * O padrão é `auto` (o valor que o R2 usa) porque sem região o SDK
             * nem constrói o cliente: ele lança "Missing required client
             * configuration options: region". Com `throw => false`, que era o
             * padrão daqui, isso sumia em silêncio — o upload simplesmente não
             * acontecia e nada aparecia na tela.
             *
             * Apontando para a AWS de verdade, defina AWS_DEFAULT_REGION
             * (us-east-1, sa-east-1...): `auto` só vale no R2.
             */
            'region' => env('AWS_DEFAULT_REGION', 'auto'),
            'bucket' => env('AWS_BUCKET'),
            'url' => env('AWS_URL'),
            // Tolera o endpoint copiado do painel com o bucket no fim — ver R2Endpoint.
            'endpoint' => \App\Support\R2Endpoint::normalize(env('AWS_ENDPOINT'), env('AWS_BUCKET')),
            'use_path_style_endpoint' => env('AWS_USE_PATH_STYLE_ENDPOINT', false),

            /*
             * Falha de upload vai para o log (report), mas NAO lança: com throw,
             * um exists() em arquivo inexistente vira exceção e quebra leituras
             * legítimas (o femopror:migrar-arquivos faz exatamente isso).
             *
             * Quem garante que a inscrição não é gravada sem comprovante é o
             * próprio componente, que recusa quando o store() devolve false.
             */
            'throw' => false,
            'report' => true,

            // Ver a nota no disco `r2`: o checksum automático do SDK quebra no R2.
            'request_checksum_calculation' => 'when_required',
            'response_checksum_validation' => 'when_required',
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Symbolic Links
    |--------------------------------------------------------------------------
    |
    | Here you may configure the symbolic links that will be created when the
    | `storage:link` Artisan command is executed. The array keys should be
    | the locations of the links and the values should be their targets.
    |
    */

    'links' => [
        public_path('storage') => storage_path('app/public'),
    ],

];
