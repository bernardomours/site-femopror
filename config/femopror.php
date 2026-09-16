<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Recebimento por PIX
    |--------------------------------------------------------------------------
    |
    | Estava tudo escrito dentro do componente de inscrição, então trocar de
    | tesoureiro exigia mexer na view e fazer deploy. O payload PIX é montado a
    | partir daqui.
    |
    | A chave vai no formato exigido pelo BR Code. Para telefone, é o formato
    | internacional: +5584999999999.
    |
    */

    'pix' => [
        'key' => env('PIX_KEY', '+5584991350289'),
        'receiver' => env('PIX_RECEIVER', 'Adson Avelino'),
        'city' => env('PIX_CITY', 'Mossoro'),
        // Só o que aparece na tela para quem vai copiar manualmente.
        'display_key' => env('PIX_DISPLAY_KEY', '84991350289'),
        'display_label' => env('PIX_DISPLAY_LABEL', 'Celular'),
        'display_owner' => env('PIX_DISPLAY_OWNER', 'Federação de Mocidades do PROR / Adson (Tesouraria)'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Onde ficam os arquivos enviados
    |--------------------------------------------------------------------------
    |
    | Comprovantes de PIX e relatórios das UMPs. Um lugar só para essa decisão:
    | em desenvolvimento é `local` (storage/app/private), em produção é `r2`
    | (Cloudflare R2). Os dois são privados e servem por URL assinada, então a
    | troca é só de variável de ambiente — nenhum código muda.
    |
    | Trocar o disco NÃO move o que já foi enviado: os arquivos antigos
    | continuam no disco anterior. Se já houver comprovante gravado, use
    | `php artisan femopror:migrar-arquivos local r2` antes de virar a chave.
    |
    */

    'uploads' => [
        'disk' => env('UPLOADS_DISK', 'local'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Contato e redes
    |--------------------------------------------------------------------------
    */

    'contact' => [
        'email' => 'femopror@gmail.com',
        'instagram' => 'https://www.instagram.com/femopror/',
        'youtube' => 'https://www.youtube.com/@FEMOPROR',
    ],

    /*
    |--------------------------------------------------------------------------
    | Diretoria da federação
    |--------------------------------------------------------------------------
    |
    | A diretoria das UMPs locais vem da tabela `boards`. A da própria federação
    | não tem registro correspondente (não existe uma Church "FEMOPROR"), e
    | estava escrita à mão no meio da home — sete nomes e sete links soltos em
    | HTML. Aqui, a virada de ano é editar uma lista.
    |
    */

    'board_year' => 2026,

    'board' => [
        ['role' => 'Presidente', 'name' => 'Ezequiel Meira', 'instagram' => 'https://www.instagram.com/ezequiel.meira/'],
        ['role' => 'Vice-Presidente', 'name' => 'Constanzza Nascimento', 'instagram' => 'https://www.instagram.com/constzza/'],
        ['role' => 'Sec. Executivo', 'name' => 'Clara Ohana', 'instagram' => 'https://www.instagram.com/clarohanaa/'],
        ['role' => '1º Secretário', 'name' => 'Clara Manuella', 'instagram' => 'https://www.instagram.com/claravaleo/'],
        ['role' => '2º Secretário', 'name' => 'Bernardo Moura', 'instagram' => 'https://www.instagram.com/bernardomours/'],
        ['role' => 'Tesoureiro', 'name' => 'Adson Avelino', 'instagram' => 'https://www.instagram.com/adsonavelino/'],
        ['role' => 'Sec. Presbiterial', 'name' => 'Antônio Alex', 'instagram' => null],
    ],

];
