<?php

namespace App\Support;

/**
 * As perguntas que todo congresso da federação faz.
 *
 * Elas viram `events.custom_fields` do evento, exatamente como se tivessem sido
 * digitadas no cadastro — não existe caminho paralelo de renderização. É isso
 * que faz tudo o que já depende de `custom_fields` continuar valendo de graça:
 * a validação contra as opções, o cálculo de acréscimo e os gráficos do painel
 * de acompanhamento, que desenham uma coluna por pergunta.
 *
 * **O que NÃO está aqui, e por quê:** nome, e-mail, WhatsApp e igreja. Os
 * quatro já são campos fixos do formulário de inscrição e vêm pré-preenchidos
 * do cadastro da pessoa. Repeti-los como pergunta personalizada faria o
 * participante responder duas vezes e deixaria a igreja gravada em dois lugares
 * — e é `registrations.church_id` que alimenta o gráfico "inscritos por igreja".
 */
class PerguntasPadraoDoCongresso
{
    /**
     * @return list<array{question: string, type: string, help: string, required: bool, options: list<array{label: string, surcharge: float|int}>}>
     */
    public static function campos(): array
    {
        return [
            [
                'question' => 'Você vai como delegado ou visitante?',
                // Três opções curtas: no celular, botão de rádio é bem melhor
                // que abrir uma lista suspensa.
                'type' => 'radio',
                'help' => '',
                'required' => true,
                /*
                 * "Diretoria ou secretário (FEMOPROR)" é a terceira categoria
                 * real: quem está a serviço da federação não é delegado da UMP
                 * local nem visitante.
                 */
                'options' => [
                    ['label' => 'Delegado', 'surcharge' => 0],
                    ['label' => 'Visitante', 'surcharge' => 0],
                    ['label' => 'Diretoria ou secretário (FEMOPROR)', 'surcharge' => 0],
                ],
            ],
            [
                'question' => 'Você possui alguma alergia ou restrição alimentar? Se sim, qual?',
                'type' => 'text',
                'help' => 'Se não tiver, escreva "nenhuma".',
                // A cozinha precisa saber antes. Em branco, só se descobre no dia.
                'required' => true,
                'options' => [],
            ],
        ];
    }
}
