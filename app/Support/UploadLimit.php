<?php

namespace App\Support;

/**
 * Qual é, de verdade, o maior arquivo que dá para enviar.
 *
 * O formulário dizia "até 3 MB" e validava `max:3072`, mas quem manda no
 * assunto é o `php.ini`: com `upload_max_filesize = 2M`, uma foto de 2,5 MB é
 * descartada pelo PHP **antes** de o Laravel existir. Não há validação que
 * pegue isso — a requisição chega vazia, e a pessoa fica olhando o spinner sem
 * mensagem nenhuma. Foto de celular passa de 2 MB com frequência.
 *
 * Aqui o limite anunciado e o limite validado saem do mesmo lugar que o limite
 * real, então a tela nunca promete o que o servidor não aceita.
 */
class UploadLimit
{
    /** Teto que o projeto quer, independente do servidor. */
    private const TETO_KB = 3072;

    /** Maior upload aceito, em kilobytes. */
    public static function maxKilobytes(): int
    {
        $limites = array_filter([
            self::iniParaKilobytes('upload_max_filesize'),
            self::iniParaKilobytes('post_max_size'),
            self::TETO_KB,
        ]);

        return (int) min($limites);
    }

    /** Para mostrar na tela: "2 MB", "3 MB", "800 KB". */
    public static function label(): string
    {
        $kb = self::maxKilobytes();

        if ($kb >= 1024) {
            $mb = $kb / 1024;

            return rtrim(rtrim(number_format($mb, 1, ',', ''), '0'), ',').' MB';
        }

        return $kb.' KB';
    }

    /** Para o JavaScript conferir antes de tentar enviar. */
    public static function maxBytes(): int
    {
        return self::maxKilobytes() * 1024;
    }

    /**
     * "2M", "8M", "512K", "1G" ou um número em bytes viram kilobytes.
     * `post_max_size = 0` significa "sem limite" — devolve null para sair da conta.
     */
    private static function iniParaKilobytes(string $chave): ?int
    {
        $valor = trim((string) ini_get($chave));

        if ($valor === '' || $valor === '0' || $valor === '-1') {
            return null;
        }

        $unidade = strtolower(substr($valor, -1));
        $numero = (float) $valor;

        $bytes = match ($unidade) {
            'g' => $numero * 1024 * 1024 * 1024,
            'm' => $numero * 1024 * 1024,
            'k' => $numero * 1024,
            default => $numero,
        };

        return (int) floor($bytes / 1024);
    }
}
