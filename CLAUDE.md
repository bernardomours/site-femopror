# Site FEMOPROR

Portal da **Federação de Mocidades do Presbitério Oeste Rio-Grandense** (Mossoró/RN).
Um site público com a história, a diretoria, as UMPs locais e os materiais para download —
e, por trás dele, o sistema que recebe as **inscrições em eventos**: tanto a do jovem que se
inscreve sozinho quanto a **delegação oficial que cada UMP envia para o congresso**.

> **Este arquivo é o mapa do projeto.** Sempre que o código mudar de forma relevante
> (schema, regra de negócio, decisão de arquitetura), atualize-o na mesma tarefa — é ele que
> mantém o contexto entre sessões.

## Stack

PHP 8.4 · Laravel 13 · **Livewire 4** (componentes de arquivo único, com o prefixo `⚡`) ·
Filament 5 (dois painéis) · Tailwind 3 + Alpine · SQLite em dev · Hostinger em produção.

---

## Os três públicos

| Quem | Onde entra | O que faz |
|---|---|---|
| **Visitante / jovem** | Site público + `/dashboard` | Se inscreve em evento, acompanha o pagamento |
| **Presidente de UMP local** | Painel `/ump` | Envia a inscrição do congresso: comprovante, relatórios e delegação |
| **Diretoria da federação** | Painel `/area-da-diretoria` | Cadastra eventos, confere comprovantes, aprova inscrições |

Quem é quem sai de duas colunas em `users`: **`is_admin`** (diretoria) e
**`is_church_president`** (presidente local). `User::canAccessPanel()` responde **por
painel** — `admin` exige `is_admin`; `ump` exige admin ou `isChurchPresident()`.

> Isso já foi um bug grave: o método devolvia `is_admin` para os dois painéis, então o `/ump`
> ficava inacessível justamente para quem ele foi feito. O único contorno era promover o
> presidente local a admin da federação — que enxerga todas as igrejas, todos os comprovantes
> e a delegação de todo mundo. Coberto por `SegurancaTest`.

### `church_id` não é cargo — e por isso existe `is_church_president`

As duas coisas já foram a mesma coluna. **`users.church_id` é a igreja de que a pessoa faz
parte**, ela mesma escolhe em `/profile`, e serve para preencher as inscrições.
**`is_church_president` é o cargo**, marcado pela diretoria no `UserResource`, e é a única
coisa que abre o painel `/ump`.

Enquanto ter igreja bastava para entrar no `/ump`, expor esse campo no perfil daria a
qualquer jovem o poder de enviar a inscrição do congresso em nome daquela igreja. A migration
que separou as duas marcou como presidente quem já tinha igreja e não era admin, preservando
o acesso das contas existentes.

`is_admin` e `is_church_president` **não são mass assignable** de propósito (ver `#[Fillable]`
em `User`): são as duas colunas que decidem acesso a painel, e só o `UserResource` as grava.
`church_id` e `phone` são fillable porque pertencem ao usuário.

---

## Padrão de segurança (seguir em todo formulário novo)

1. **Nada que decide permissão vem do cliente.** `church_id` e `status` da inscrição de
   congresso são definidos em `mutateFormDataBeforeCreate()`, a partir de `auth()->user()`.
   Campo `Hidden` do Filament **não protege nada**: o estado dele vive no Livewire e é
   adulterável por request forjada — dava para mandar `status: aprovado` e se autoaprovar.
2. **Método de componente Livewire é endereçável mesmo sem estar em tela.** Esconder o botão
   com `@auth` na view não protege; a trava tem que ser `abort_unless()` dentro do método.
3. **Todo escopo por igreja passa pelo `getEloquentQuery()`** do Resource — é por ali que o
   Filament também resolve o `{record}` do Edit, então a URL direta de outra UMP dá 404.
4. **Dinheiro é calculado no servidor.** Ver "O preço não vem do navegador".
5. **Comprovante e relatório não moram em disco público.** Ver "Arquivos privados".

---

## Modelo de dados

```
Church ──< Board            (a diretoria da UMP local; uma ativa por vez)
       ──< User             (presidente local: users.church_id)
       ──< Registration
       ──< CongressSubscription ──< Delegate ──o User
                                ──< CongressDocument

Event ──< Registration      (inscrição individual, pelo site)
      ──< CongressSubscription  (delegação, pelo painel /ump)

Download                    (materiais públicos; sem vínculo)
```

### Decisões que não são óbvias no schema

- **`events.is_congress`** decide se a página do evento oferece "Sou Visitante / Sou
  Delegado" e se ele entra na contagem do painel. Isso já foi
  `str_contains($title, 'congresso')`: renomear o evento desligava o fluxo inteiro em
  silêncio.
- **`registrations (event_id, user_id)` é unique.** A checagem de duplicidade só existia na
  view; duplo clique ou request forjada criavam linhas repetidas.
- **`registrations.amount_paid`** congela quanto o sistema cobrou naquela inscrição. É contra
  esse número que a tesouraria confere o comprovante — reajustar o preço do evento depois não
  pode mudar o que foi cobrado de quem já se inscreveu.
- **`delegates.user_id`** é o vínculo real com a conta; `delegates.email` continua existindo
  porque a UMP cadastra a delegação **antes** de o jovem criar o acesso. O e-mail funciona
  como vale: `User::booted()` fecha o vínculo quando a conta nasce, e `Delegate::booted()`
  fecha quando o delegado é cadastrado depois. Só com e-mail, trocar o endereço no perfil
  fazia a pessoa perder a própria inscrição de vista.
- **Uma inscrição de congresso por UMP por evento** — a trava é de aplicação
  (`CreateCongressSubscription`), não índice único: apagar uma inscrição levaria delegados e
  documentos junto em cascata, então não dá para deduplicar o histórico sem perder dado.
- **Uma diretoria ativa por igreja** — garantido por `Board::booted()`, que desativa as
  outras ao salvar. A home mostra `$church->boards->first()` e antes dependia da sorte.

---

## O preço não vem do navegador

Cada evento pode ter **perguntas personalizadas** (`events.custom_fields`), e uma opção pode
cobrar a mais: `Camisa M (+30,00)`.

`Event::customFieldDefinitions()` normaliza esse JSON e é a **única** fonte do que existe:
para cada campo devolve uma `key` estável, o texto da pergunta e as opções já separadas em
`label` + `surcharge`. O total sai de casar o rótulo escolhido com o acréscimo cadastrado.

Duas armadilhas que isso resolve:

- **O acréscimo era extraído por regex da resposta que o cliente devolvia.** Como
  `custom_answers` é propriedade pública do componente, dava para mandar qualquer texto,
  forjar um valor menor, gerar o PIX com esse valor e pagar a menos. Hoje a resposta é
  validada com `Rule::in()` contra as opções do evento.
- **A separação das opções não é `explode(',')`.** A vírgula decimal mora dentro dos
  parênteses: `M (+30,00)` virava duas opções, "M (+30" e "00)", e os 30 reais sumiam sem
  aviso. `Event::parseOptions()` divide só nas vírgulas fora de parênteses.

**As respostas são indexadas pela `key` no formulário e pelo texto da pergunta no banco.** No
`wire:model`, pergunta com ponto virava aninhamento de array e pergunta com aspas quebrava o
HTML; no banco, o texto é o que o painel e o dashboard exibem.

---

## Arquivos privados

| Vai para o disco `local` (privado) | Vai para o disco `public` |
|---|---|
| Comprovante de PIX da inscrição (`receipts/`) | Imagem de capa do evento |
| Comprovante da UMP (`congress-receipts/`) | Foto da diretoria |
| Relatórios da UMP (`congress-documents/`) | Materiais de download |

Tudo isso morava no disco público, acessível por URL direta sem autenticação: comprovante
bancário, relatório de tesouraria e lista de delegados.

O disco `local` só é servido com **URL assinada** (`ServeFile` do Laravel). O driver local não
sabe gerar essa URL sozinho — quem ensina é `AppServiceProvider::enableTemporaryUrlsForPrivateFiles()`,
que aponta `temporaryUrl()` para a rota `storage.local`. Sem esse callback, `temporaryUrl()`
lança exceção e o Filament cai de volta no `url()` público.

Links gerados valem **30 minutos**. É um link não adivinhável e que expira, não uma
autorização por usuário — quem receber o link dentro da janela abre o arquivo. Se um dia isso
não bastar, o passo seguinte é uma rota própria que confira o dono do registro.

---

## Módulos

### Site público — `/`
`⚡home-page.blade.php`. Três consultas, uma por bloco: eventos publicados, downloads ativos
e igrejas da federação com a diretoria ativa.

A ordenação das igrejas ignora o prefixo ("Igreja Presbiteriana do Planalto" ordena por
"planalto") e vive em `Church::sortableName()`, em PHP. Era um `orderByRaw` com cinco
`REPLACE` aninhados escrito dentro do Blade.

**A diretoria da federação vem de `config/femopror.php`**, não do banco: a tabela `boards`
pertence a uma `Church`, e não existe um registro de igreja para a federação em si. Os sete
nomes estavam em HTML no meio da página. A virada de ano é editar uma lista.

Menu: os links somem abaixo de `lg` e viram sanfona. Antes eram quatro links e um botão num
`flex gap-8` sem nenhum breakpoint — no celular a barra estourava.

### Página do evento — `/eventos/{id}`
`⚡event-show.blade.php`. Faz o trabalho pesado do site.

- `mount()` recusa evento em **rascunho** com 404. Encerrado e "ainda vai abrir" carregam a
  página, mas mostram o painel de aviso em vez do formulário.
- `register()` revalida tudo no servidor: login, `Event::acceptsRegistrations()`,
  duplicidade, rate limit (5 tentativas / 5 min por usuário) e o índice único como última
  linha contra corrida.
- **Comprovante só é exigido quando o evento cobra** (`Event::requiresReceipt()`). Evento
  gratuito pedindo PIX era um beco sem saída.
- Telefone entra com máscara e é normalizado para dígitos antes de gravar. O campo tinha
  `maxlength="11"` e cortava o número de quem digitava "(84) 99135-0289".

**PIX:** `App\Support\PixPayload` monta o BR Code (EMV®QRCPS + CRC-16/CCITT-FALSE) a partir de
`config/femopror.php`. Chave, tesoureiro e cidade estavam escritos na view.

> O **QR Code ainda é gerado por `api.qrserver.com`**, um serviço externo. O payload não
> carrega segredo (a chave PIX é mostrada na própria página), então o problema é só de
> dependência: se o serviço cair, o QR não aparece — por isso o `onerror` deixa o copia-e-cola
> visível. Gerar local exigiria uma lib de QR; não foi feito para não entrar uma dependência
> que não dá para validar de ponta a ponta aqui, e um QR de pagamento errado é caro.

### O funil de quem chega pela página do evento

A pessoa cai na Copa pelo Instagram, decide se inscrever e **não tem conta**. Esse é o
caminho principal do site, e ele estava quebrado: a página do evento tinha um botão só,
apontando para `/login` sem levar nada, e o `intended()` do Laravel não tinha o que ler na
sessão — a pessoa entrava e caía no `/dashboard`, tendo que achar o evento de novo.

Agora a página do evento mostra **dois caminhos** ("Criar minha conta" e "Já tenho conta"),
os dois com `?redirect=/eventos/{id}`. `App\Support\IntendedUrl` grava isso na sessão no
`create()` dos dois controllers, e o `store()` de ambos volta por `intended()` — o cadastro
tinha um `redirect(dashboard)` fixo que ignorava tudo.

**Só caminho relativo é aceito** (`IntendedUrl::sanitize()`). `?redirect=https://evil.com` num
link que sai do nosso domínio é open redirect clássico: parece confiável, e joga a pessoa
numa cópia da tela de login em outro servidor. `//evil.com` e `/\evil.com` também são
recusados — os dois viram domínio externo no navegador.

Trocar entre "entrar" e "criar conta" preserva o destino, e o subtítulo muda para avisar que
ela volta para a inscrição.

### Arquivos enviados: Cloudflare R2 em produção

`config('femopror.uploads.disk')` é o **único** lugar que decide onde o comprovante cai:
`local` em desenvolvimento, `r2` em produção. Nenhum código sabe qual é — todos os
`FileUpload` do Filament e o `store()` da inscrição leem dali.

R2 é S3-compatível, então usa o driver `s3` com `region: auto`, `use_path_style_endpoint` e o
endpoint da conta. O bucket é **privado**: o acesso sai por URL assinada de 30 minutos, que o
R2 suporta nativamente (no disco `local` quem gera é o callback do `AppServiceProvider`).

> **Trocar o disco não move o que já foi enviado.** Os arquivos antigos ficam no disco
> anterior e o painel passa a procurá-los no lugar errado — comprovante sumido, sem erro na
> tela. Rode `php artisan femopror:migrar-arquivos local r2` (tem `--dry-run`) **antes** de
> virar a chave. O comando copia, não move: o original fica até você conferir.

`php artisan femopror:testar-r2` valida a conexão do jeito que o sistema usa: grava, lê,
**gera o link assinado e baixa por ele**, e confere que o bucket não responde sem assinatura.
Testar só a escrita não serve — se o upload funciona mas a URL assinada não, o botão "Ver
PIX" aparece no painel e quebra na hora de usar. O comando traduz os erros mais comuns do R2
(chave errada, bucket inexistente, endpoint com o bucket no fim, token sem permissão).

**O disco desliga o checksum automático do SDK** (`request_checksum_calculation` e
`response_checksum_validation` em `when_required`). Desde a versão 3.3xx o SDK da AWS manda
`x-amz-checksum-crc32` em todo upload, e o R2 devolve um "NotImplemented" genérico que não dá
nenhuma pista da causa. A integridade continua garantida pela assinatura SigV4.

### E-mails de inscrição

Dois, e são momentos diferentes:

| Quando | Classe | O que diz |
|---|---|---|
| A pessoa envia a inscrição | `InscricaoRecebida` | "chegou aqui, a tesouraria vai conferir o PIX" |
| A tesouraria clica em "Confirmar Pagamento" | `InscricaoConfirmada` | "sua vaga está garantida" |

Os dois saem por **`App\Support\SafeMail`**, que engole a falha e manda para o log. A razão é
específica: quando o e-mail sai, **a inscrição já está gravada**. Deixar a exceção subir
transformaria "não avisamos por e-mail" em "a pessoa levou erro na tela e achou que não se
inscreveu" — e ela tentaria de novo, agora esbarrando no índice único. No painel, a
notificação avisa a tesouraria quando o envio falhou, para não achar que a pessoa foi
notificada.

Envio é **síncrono** (sem fila): é um e-mail por inscrição, e uma fila em hospedagem
compartilhada precisaria de worker por cron. Por isso `MAIL_TIMEOUT=10` — um SMTP travado
sem timeout segura a tela de inscrição até o PHP morrer.

Layout em `resources/views/emails/`: tabela e estilo inline de propósito. Gmail e Outlook
descartam `<style>` no head e ignoram flexbox.

### Perfil — `/profile`

Além de nome, e-mail e senha, guarda **igreja** e **WhatsApp**. Os dois são opcionais e
existem por um motivo prático: eram pedidos de novo a cada inscrição, sempre iguais.

O cadastro segue curto de propósito (nome, e-mail, senha) — pedir tudo na porta de entrada
espanta gente. O perfil é onde a pessoa completa depois, e o dashboard mostra um aviso
discreto enquanto faltar algo.

O fluxo fecha nos dois sentidos:

- `⚡event-show` **preenche** o formulário com o que está na conta (`mount()`);
- quem se inscreve com o perfil ainda em branco tem o perfil **completado** ao final
  (`guardarNoPerfil()`), mas só o que estava vazio — sobrescrever a igreja que a pessoa
  escolheu seria mexer no cadastro dela sem pedir. Coberto por `PerfilTest`.

A inscrição continua guardando a própria cópia de igreja e telefone: é o dado daquele evento,
naquele dia, e não muda se a pessoa editar o perfil depois.

### Painel do participante — `/dashboard`
`DashboardController`. Mostra as inscrições individuais e as delegações.

> Isto era uma closure na rota que carregava `with('congressSubscription.church')` sobre uma
> relação que o model **não definia**: todo delegado de verdade tomava 500 ao abrir o painel.
> Só não aparecia porque quem não é delegado nunca disparava o eager load. Regressão coberta
> por `PainelParticipanteTest`.

`verified` saiu do middleware: `User` não implementa `MustVerifyEmail`, então o middleware
passava direto — parecia uma proteção que não existia. **Ninguém verifica e-mail neste site**,
e `MAIL_MAILER=log` significa que nem recuperação de senha sai.

### Painel `/ump` (Filament)
Uma tela: a inscrição do congresso da própria UMP. Escopo por `getEloquentQuery()`.
Depois de **aprovada**, fica somente leitura (`CongressSubscription::isEditableByChurch()`) —
mexer no comprovante ou na delegação reescreveria o que a secretaria já conferiu. Sem
exclusão, nem individual nem em massa: apagar leva delegados e documentos em cascata.

### Painel `/area-da-diretoria` (Filament)
Eventos, inscrições, igrejas, diretorias, downloads, usuários e a análise das inscrições de
congresso.

- **Confirmar Pagamento** mostra quanto o sistema cobrou daquela pessoa, para conferir contra
  o comprovante.
- `UserResource` não deixa ninguém se excluir nem remover o **último administrador** — sem
  isso dava para trancar todo mundo fora do painel, e o caminho de volta seria o SSH.
  `User::booted()` repete a trava no model, para pegar o que não passa pelo Resource.
- O widget de estatísticas recorta pelo **congresso mais recente**. Somava todos os
  congressos de todos os anos, então o número só crescia e nunca respondia "quantos vêm
  neste?".

---

## Testes

`php artisan test` — SQLite em memória.

| Arquivo | Cobre |
|---|---|
| `SegurancaTest` | acesso a cada painel por papel, `church_id`/`status` forjados na inscrição da UMP, escopo entre igrejas, inscrição aprovada travada, duplicidade, último admin, `is_admin` fora do mass assignment, seeder recusado em produção |
| `InscricaoEventoTest` | inscrição por deslogado, rascunho/encerrado/ainda-não-aberto, duplicidade, comprovante só quando cobra, preço no servidor, resposta forjada, valor congelado, telefone normalizado, comprovante no disco privado |
| `PainelParticipanteTest` | dashboard de delegado (regressão do 500), vínculo sobrevivendo à troca de e-mail, delegação amarrada no cadastro, isolamento entre participantes |
| `PaginaInicialTest` | home responde, rascunho escondido, ícone inválido não derruba a página, diretoria ativa, ordenação das igrejas, meta de compartilhamento |
| `PerfilTest` | igreja e telefone gravados e normalizados, opcionais, validação, prefill da inscrição, perfil completado sem sobrescrever |
| `FluxoInscricaoCopaTest` | o caminho inteiro de um evento avulso: os dois botões para quem está deslogado, criar conta e voltar para o evento, entrar e voltar, alternar sem perder o destino, open redirect recusado, esportes somando no valor, QR invalidado ao mudar de esporte, comprovante no disco privado, os dois e-mails, falha de SMTP não derrubando a inscrição |
| `PixPayloadTest` | estrutura do BR Code, valor, normalização de acento/tamanho, CRC |

Factories: `UserFactory` (`admin()`, `ofChurch()` = presidente, `memberOfChurch()` = só
membro), `EventFactory` (`draft()`, `closed()`,
`congress()`, `free()`, `openingLater()`), `ChurchFactory`, `BoardFactory`,
`RegistrationFactory`.

> As factories geradas pelo Blueprint listavam colunas que não existem (`registrations` e
> `boards` como se fossem campos, `year_start`/`secretary_name` em `Board`): qualquer
> `create()` morria no SQL. Foram reescritas.

---

## Convenções de UI

O site público tem a linguagem dele (verde chapado, caixa alta espaçada, cartões grandes). As
telas de **conta** — login, cadastro, recuperação, perfil, painel do participante — seguem um
sistema próprio, mais sóbrio, porque são formulário e não vitrine:

- **Sem gradiente e sem sombra pesada.** O que separa um bloco do outro é um fio de 1px
  (`border-gray-200`), não elevação.
- **Raio contido:** `rounded-lg` em campo e botão, `rounded-xl` no cartão. Nada de
  `rounded-full` ou `rounded-3xl` — arredondamento demais é o que dá cara de template.
- **Cor só onde significa:** verde no botão de ação e no estado ativo; o resto é cinza.
- **Foco é anel fino** (`ring-1`), não halo.
- Página de ajustes em **duas colunas**: título e explicação à esquerda, campos à direita,
  blocos separados por `divide-y`.

Os componentes do Breeze foram reescritos nesse sistema (`text-input`, `select-input`,
`input-label`, `primary/secondary/danger-button`, `input-error`, `nav-link`,
`responsive-nav-link`, `dropdown-link`) — vinham com indigo, que não é cor do projeto.

**`x-text-input` e `x-select-input` pintam o próprio estado de erro** quando recebem `name`:
não repita a lógica de borda vermelha em cada formulário.

## Armadilhas já encontradas (não repetir)

- **Campo `Hidden` do Filament não é campo do servidor.** Ver o padrão de segurança.
- **Método de componente Livewire é público de verdade**, mesmo sem estar em tela nenhuma.
- **Relação inexistente no `with()` só explode quando há resultado.** `Builder::get()` só
  chama o eager load se a consulta devolveu linhas — foi o que escondeu o 500 do dashboard de
  delegado por tanto tempo.
- **`match` sem `default` derruba a tela inteira** com `UnhandledMatchError` no primeiro
  valor fora da lista. Vale para `badge()->color()` e `formatStateUsing()` no Filament.
- **`@svg()` com nome de ícone vindo do banco** derruba a página se o nome não existir.
  `Download::iconName()` faz o fallback.
- **Timezone.** É `America/Fortaleza`, não UTC: em UTC, `now()` fica 3h à frente e um evento
  marcado para abrir às 08:00 abria às 05:00 de Mossoró.
- **`explode(',')` em lista com valor monetário** quebra no separador decimal.
- **Migration com `down()` vazio** não dá rollback e quebra o re-run. Uma já foi corrigida
  por migration nova (editar a original não teria efeito: ela já rodou em produção).
- **Teste sem `Storage::fake` do disco CERTO grava no bucket de produção.** Com
  `UPLOADS_DISK=r2` no `.env`, um `Storage::fake('local')` não protege nada: o componente usa
  `config('femopror.uploads.disk')` e escreve no R2 de verdade. Já aconteceu — sobraram 7
  arquivos lá. Por isso o `phpunit.xml` fixa `UPLOADS_DISK=local`, e os testes usam
  `Storage::fake(config('femopror.uploads.disk'))`, nunca um nome de disco na mão.
- **Valor congelado na tela envelhece.** O QR do PIX era gerado uma vez e ficava lá: marcar
  mais um esporte subia o preço exibido e o QR continuava cobrando o valor antigo. Qualquer
  coisa derivada do preço precisa morrer quando o preço muda (`updatedRespostas()`).
- **`@php use ... @endphp` numa view Blade quebra com "unexpected token use"**: o `use` não
  fica no topo do arquivo compilado. Referencie pelo nome completo (`\App\Support\X::`).
- **Heredoc do bash come barra invertida.** `'/\\'` num `cat <<'PHP'` chegou no arquivo como
  `'/\'` e deixou a string aberta. Para código com escape, use a ferramenta de edição.
- **Input com `wire:model` não renderiza `value` sozinho.** O estado viaja no snapshot e o
  Livewire aplica no init, então funciona — mas o campo pisca vazio no primeiro paint. Onde o
  valor vem preenchido (nome, e-mail, telefone, igreja na inscrição), o `value`/`@selected`
  também é escrito no HTML.
- **`php artisan test` precisa de `public/build`.** Sem os assets do Vite compilados, todo
  teste que renderiza view morre com `ViteManifestNotFoundException` — e o erro não deixa
  óbvio que o problema é só `npm run build`.

---

## Antes de subir para produção

`AppServiceProvider::hardenProduction()` já força duas coisas quando `APP_ENV=production`:

- **quebra o boot se `APP_DEBUG=true`** — em produção isso transforma qualquer exceção numa
  página com stack trace e o conteúdo do `.env`, credenciais do banco inclusive;
- **`URL::forceScheme('https')`** — sem isso um link `http://` gerado em qualquer canto faz o
  navegador mandar o cookie de sessão em texto claro.

O que ainda depende de configuração no servidor:

- `SESSION_ENCRYPT=true` e `SESSION_SECURE_COOKIE=true`
- `APP_URL` com o domínio real em https
- **SMTP de verdade em `MAIL_*`** — com `log`, nem a confirmação de inscrição nem a
  recuperação de senha chegam a ninguém. Ver os dois caminhos comentados no `.env.example`
- **`UPLOADS_DISK=r2` + as quatro chaves `R2_*`**, e `femopror:migrar-arquivos` antes de virar
  a chave se já houver comprovante gravado
- backup do banco antes de rodar migrations
- `public/` como docroot (nunca a raiz do projeto, que exporia o `.env`)

### Primeiro acesso

```bash
php artisan femopror:criar-admin
```

Pede a senha interativamente (nunca como argumento, que ficaria no `~/.bash_history` e
apareceria no `ps`), exige 12 caracteres e confirmação.

**Nunca `db:seed` em produção.** O `DatabaseSeeder` é ferramenta de desenvolvimento e agora
lança exceção se `APP_ENV=production` — ele criava um admin com e-mail `a@a` e senha `123`.

### Deploy

`git pull` sozinho **não basta**: `vendor/`, `public/build/` e as migrations ficam fora do
git. A ordem é backup do banco → `composer install --no-dev` → `npm ci && npm run build` →
`php artisan migrate --force` → `optimize:clear` e recache.

> A migration `add_unique_registration_per_user_and_event` **apaga duplicatas** de inscrição
> antes de criar o índice (preserva sempre a de pagamento confirmado; no empate, a mais
> antiga). Faça o backup antes.

## Comandos

```bash
php artisan migrate
php artisan test
npm run build
```
