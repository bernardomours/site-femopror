<?php

namespace Tests\Unit;

use App\Support\UploadLimit;
use PHPUnit\Framework\TestCase;

class UploadLimitTest extends TestCase
{
    public function test_nunca_anuncia_mais_do_que_o_php_aceita(): void
    {
        // A regra que importa: o formulário não pode prometer um tamanho que o
        // servidor descarta antes do Laravel ver.
        $limite = UploadLimit::maxKilobytes();

        $this->assertGreaterThan(0, $limite);
        $this->assertLessThanOrEqual($this->iniKb('upload_max_filesize'), $limite);
        $this->assertLessThanOrEqual(3072, $limite, 'o teto do projeto é 3 MB');
    }

    public function test_label_e_legivel(): void
    {
        $this->assertMatchesRegularExpression('/^\d+(,\d)? (KB|MB)$/', UploadLimit::label());
    }

    public function test_bytes_batem_com_kilobytes(): void
    {
        $this->assertSame(UploadLimit::maxKilobytes() * 1024, UploadLimit::maxBytes());
    }

    private function iniKb(string $chave): float
    {
        $valor = trim((string) ini_get($chave));

        if ($valor === '' || $valor === '0' || $valor === '-1') {
            return INF;
        }

        $numero = (float) $valor;

        return match (strtolower(substr($valor, -1))) {
            'g' => $numero * 1024 * 1024,
            'm' => $numero * 1024,
            'k' => $numero,
            default => $numero / 1024,
        };
    }
}
