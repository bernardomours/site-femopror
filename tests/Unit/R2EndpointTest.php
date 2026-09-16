<?php

namespace Tests\Unit;

use App\Support\R2Endpoint;
use PHPUnit\Framework\TestCase;

class R2EndpointTest extends TestCase
{
    public function test_remove_o_bucket_do_fim_do_endpoint(): void
    {
        // É assim que o campo "S3 API" vem do painel da Cloudflare.
        $this->assertSame(
            'https://abc123.r2.cloudflarestorage.com',
            R2Endpoint::normalize('https://abc123.r2.cloudflarestorage.com/femopror-comprovantes', 'femopror-comprovantes'),
        );
    }

    public function test_aceita_barra_no_final(): void
    {
        $this->assertSame(
            'https://abc123.r2.cloudflarestorage.com',
            R2Endpoint::normalize('https://abc123.r2.cloudflarestorage.com/femopror-comprovantes/', 'femopror-comprovantes'),
        );
    }

    public function test_endpoint_ja_correto_fica_intacto(): void
    {
        $this->assertSame(
            'https://abc123.r2.cloudflarestorage.com',
            R2Endpoint::normalize('https://abc123.r2.cloudflarestorage.com', 'femopror-comprovantes'),
        );
    }

    public function test_nao_corta_nome_parecido_que_nao_e_o_bucket(): void
    {
        // "comprovantes" termina igual, mas não é o bucket inteiro.
        $this->assertSame(
            'https://abc123.r2.cloudflarestorage.com/outra-coisa',
            R2Endpoint::normalize('https://abc123.r2.cloudflarestorage.com/outra-coisa', 'femopror-comprovantes'),
        );
    }

    public function test_sem_endpoint_ou_sem_bucket_devolve_o_que_recebeu(): void
    {
        $this->assertNull(R2Endpoint::normalize(null, 'bucket'));
        $this->assertSame('https://exemplo.com', R2Endpoint::normalize('https://exemplo.com', null));
        $this->assertSame('', R2Endpoint::normalize('', 'bucket'));
    }
}
