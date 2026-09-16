<?php

namespace App\Support;

/**
 * Monta o "copia e cola" do PIX (BR Code, padrão EMV®QRCPS do Banco Central).
 *
 * O algoritmo é o mesmo que já rodava dentro do componente de inscrição — só
 * saiu de lá. Chave, nome do recebedor e cidade vinham escritos no meio da
 * view, então trocar de tesoureiro exigia mexer no código e fazer deploy;
 * agora vêm de config/femopror.php.
 */
class PixPayload
{
    public static function make(float $valor, ?string $chave = null, ?string $recebedor = null, ?string $cidade = null): string
    {
        $chave ??= (string) config('femopror.pix.key');
        $recebedor ??= (string) config('femopror.pix.receiver');
        $cidade ??= (string) config('femopror.pix.city');

        $valorStr = number_format($valor, 2, '.', '');

        $merchantAccount = '0014br.gov.bcb.pix01'.self::len($chave).$chave;

        // O BR Code não aceita acento nem caractere especial nestes dois campos.
        $nome = substr(preg_replace('/[^A-Za-z0-9 ]/', '', $recebedor) ?? '', 0, 25);
        $cidadeLimpa = substr(preg_replace('/[^A-Za-z0-9 ]/', '', $cidade) ?? '', 0, 15);

        $payload = '000201'                                        // payload format indicator
            .'26'.self::len($merchantAccount).$merchantAccount     // merchant account information
            .'52040000'                                            // merchant category code
            .'5303986'                                             // moeda: BRL
            .'54'.self::len($valorStr).$valorStr                   // valor da transação
            .'5802BR'                                              // país
            .'59'.self::len($nome).$nome                           // nome do recebedor
            .'60'.self::len($cidadeLimpa).$cidadeLimpa             // cidade
            .'62070503***'                                         // additional data (txid livre)
            .'6304';                                               // início do CRC

        return $payload.self::crc16($payload);
    }

    /** Todo campo do BR Code é precedido do seu tamanho em dois dígitos. */
    private static function len(string $valor): string
    {
        return str_pad((string) strlen($valor), 2, '0', STR_PAD_LEFT);
    }

    /** CRC-16/CCITT-FALSE, exigido pela especificação. */
    private static function crc16(string $payload): string
    {
        $polinomio = 0x1021;
        $resultado = 0xFFFF;

        for ($i = 0; $i < strlen($payload); $i++) {
            $resultado ^= (ord($payload[$i]) << 8);

            for ($bit = 0; $bit < 8; $bit++) {
                if (($resultado <<= 1) & 0x10000) {
                    $resultado ^= $polinomio;
                }

                $resultado &= 0xFFFF;
            }
        }

        return strtoupper(str_pad(dechex($resultado), 4, '0', STR_PAD_LEFT));
    }
}
