# Site FEMOPROR

Portal da Federação de Mocidades do Presbitério Oeste Rio-Grandense — site público,
inscrições em eventos e os dois painéis administrativos (diretoria e UMPs locais).

O mapa completo do projeto (regras de negócio, decisões de arquitetura e armadilhas
conhecidas) está em **[CLAUDE.md](CLAUDE.md)**.

## Rodando localmente

Requer PHP 8.4, Composer e Node.

```bash
composer install
cp .env.example .env
php artisan key:generate
touch database/database.sqlite
php artisan migrate --seed
npm install && npm run build
```

O seeder cria um admin de desenvolvimento (`admin@femopror.test`) e as igrejas da federação.
Ele se recusa a rodar com `APP_ENV=production`.

```bash
php artisan serve      # ou use o Herd
```

## Testes

```bash
php artisan test
```

Os assets precisam estar compilados (`npm run build`): sem `public/build`, todo teste que
renderiza uma view falha com `ViteManifestNotFoundException`.

## Endereços

| Rota | Quem acessa |
|---|---|
| `/` | público |
| `/eventos/{id}` | público (inscrição exige login) |
| `/dashboard` | participante logado |
| `/ump` | presidente de UMP (usuário com `church_id`) |
| `/area-da-diretoria` | diretoria (`is_admin`) |

## Primeiro administrador em produção

```bash
php artisan femopror:criar-admin
```
