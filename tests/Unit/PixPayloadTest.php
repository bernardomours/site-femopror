<?php

namespace Tests\Unit;

use App\Support\PixPayload;
use PHPUnit\Framework\TestCase;

class PixPayloadTest extends TestCase
{
    public function test_payload_segue_a_estrutura_do_br_code(): void
    {
        $payload = PixPayload::make(50.00, '+5584991350289', 'Adson Avelino', 'Mossoro');

        $this->assertStringStartsWith('000201', $payload);
        $this->assertStringContainsString('br.gov.bcb.pix', $payload);
        $this->assertStringContainsString('5303986', $payload);   // BRL
        $this->assertStringContainsString('5802BR', $payload);
        $this->assertStringContainsString('540550.00', $payload); // campo 54, tamanho 05, valor "50.00"
        $this->assertMatchesRegularExpression('/6304[0-9A-F]{4}$/', $payload);
    }

    public function test_valor_entra_com_duas_casas_e_ponto(): void
    {
        $this->assertStringContainsString('54047.75', PixPayload::make(7.75, '+5584991350289', 'A', 'B'));
        $this->assertStringContainsString('540510.00', PixPayload::make(10.0, '+5584991350289', 'A', 'B'));
        $this->assertStringContainsString('54071234.50', PixPayload::make(1234.5, '+5584991350289', 'A', 'B'));
    }

    public function test_acento_e_tamanho_sao_normalizados_nos_campos_de_texto(): void
    {
        // O BR Code não aceita acento; nome tem limite de 25 e cidade de 15.
        $payload = PixPayload::make(10.0, 'chave@example.com', 'José da Conceição Ávila Sobrinho', 'São Gonçalo do Amarante');

        $this->assertStringNotContainsString('é', $payload);
        $this->assertStringContainsString('5925Jos da Conceio vila Sobr', $payload);
        $this->assertStringContainsString('6015So Gonalo do A', $payload);
    }

    public function test_crc_muda_quando_o_valor_muda(): void
    {
        $a = substr(PixPayload::make(10.0, '+5584991350289', 'A', 'B'), -4);
        $b = substr(PixPayload::make(20.0, '+5584991350289', 'A', 'B'), -4);

        $this->assertNotSame($a, $b);
    }
}
