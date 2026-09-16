<?php

namespace App\Support;

use Illuminate\Http\Request;

/**
 * Guarda para onde a pessoa voltar depois de entrar ou criar a conta.
 *
 * Quem clica em "criar conta" na página da Copa precisa voltar para a Copa, não
 * cair no dashboard e ter que procurar o evento de novo — era o que acontecia:
 * o link ia para /login sem levar nada, e o `intended()` do Laravel não tinha
 * nada na sessão para onde voltar.
 */
class IntendedUrl
{
    /**
     * Grava o destino na sessão se o `?redirect=` for um caminho interno.
     *
     * Só caminho relativo é aceito. `https://outro-site.com` num parâmetro de
     * URL é a receita clássica de open redirect: o link sai do nosso domínio
     * (parece confiável), a pessoa entra, e o site manda ela para uma cópia da
     * página de login em outro servidor.
     */
    public static function rememberFrom(Request $request): void
    {
        $destino = self::sanitize($request->query('redirect'));

        if ($destino !== null) {
            $request->session()->put('url.intended', $destino);
        }
    }

    /** Devolve o caminho seguro, ou null se não der para confiar. */
    public static function sanitize(mixed $valor): ?string
    {
        if (! is_string($valor) || $valor === '') {
            return null;
        }

        // Precisa começar com uma barra só: "//evil.com" é URL protocol-relative
        // e o navegador trata como domínio externo.
        if (! str_starts_with($valor, '/') || str_starts_with($valor, '//')) {
            return null;
        }

        // "/\evil.com" também escapa em alguns navegadores.
        if (str_starts_with($valor, '/\\')) {
            return null;
        }

        return $valor;
    }

    /**
     * Monta o link para login/cadastro preservando o destino atual, para
     * alternar entre as duas telas não perder de onde a pessoa veio.
     */
    public static function linkTo(string $rota): string
    {
        $destino = self::sanitize(request()->query('redirect'));

        return route($rota, $destino ? ['redirect' => $destino] : []);
    }

    /** A pessoa chegou aqui no meio de outro fluxo? */
    public static function hasDestination(): bool
    {
        return self::sanitize(request()->query('redirect')) !== null;
    }
}
