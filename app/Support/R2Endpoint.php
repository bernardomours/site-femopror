<?php

namespace App\Support;

/**
 * Tira o nome do bucket do fim do endpoint do R2.
 *
 * O painel da Cloudflare mostra o campo "S3 API" já com o bucket no final:
 *
 *   https://<conta>.r2.cloudflarestorage.com/femopror-comprovantes
 *
 * Copiar assim é o caminho natural, e o estrago é traiçoeiro porque quase tudo
 * continua funcionando: gravar, ler e gerar link assinado resolvem para o lugar
 * certo. Só a LISTAGEM quebra — o SDK concatena o bucket de novo e pede
 *
 *   .../femopror-comprovantes/femopror-comprovantes/?list-type=2
 *
 * devolvendo NoSuchKey. Como listar só acontece em manutenção (o
 * `femopror:migrar-arquivos`, por exemplo), isso só apareceria no pior momento.
 *
 * Normalizar aqui deixa o .env tolerante às duas formas.
 */
class R2Endpoint
{
    public static function normalize(?string $endpoint, ?string $bucket): ?string
    {
        if (blank($endpoint) || blank($bucket)) {
            return $endpoint;
        }

        $endpoint = rtrim($endpoint, '/');
        $sufixo = '/'.trim($bucket, '/');

        if (str_ends_with($endpoint, $sufixo)) {
            return substr($endpoint, 0, -strlen($sufixo));
        }

        return $endpoint;
    }
}
