# BoitaTech

Plataforma de monitoramento ambiental com foco em visualização geoespacial, denúncias cidadãs, ecopontos e curadoria de notícias ambientais.

## Sobre o projeto

O BoitaTech integra dados ambientais em mapas 2D/3D e disponibiliza ferramentas para:

- monitoramento de focos e camadas ambientais;
- recebimento de denúncias públicas com consentimento LGPD;
- consulta de ecopontos de coleta;
- leitura de notícias ambientais (BoitaNews).

## Tecnologias utilizadas

- PHP 8.3+
- Laravel 13
- PostgreSQL (principal e geoespacial)
- Vite 8
- JavaScript ES6+
- Leaflet
- CesiumJS
- Tailwind CSS

## Requisitos

- PHP 8.3 ou superior
- Composer 2.7+
- Node.js 20+ e npm 10+
- PostgreSQL 14+ (recomendado 15/16)
- Extensões PHP recomendadas: `pdo_pgsql`, `mbstring`, `openssl`, `fileinfo`, `intl`, `ctype`, `json`

## Instalação completa

### 1) Clonar projeto

1. `git clone <url-do-repositorio>`
2. `cd boitatech`

### 2) Backend (Laravel)

1. `composer install`
2. `copy .env.example .env` (Windows) ou `cp .env.example .env` (Linux/macOS)
3. Ajustar variáveis no `.env`
4. `php artisan key:generate`
5. `php artisan migrate --force`
6. `php artisan db:seed --force`

### 3) Frontend (Vite)

1. `npm install`
2. `npm run build` (produção) ou `npm run dev` (desenvolvimento)

### 4) Storage

1. `php artisan storage:link`

### 5) Limpeza e cache

1. `php artisan optimize:clear`
2. `php artisan config:cache`
3. `php artisan route:cache`
4. `php artisan view:cache`

## Configuração PostgreSQL

Crie um banco com UTF-8 e permissões para o usuário da aplicação.

Configuração mínima no `.env`:

- `DB_CONNECTION=pgsql`
- `DB_HOST=127.0.0.1`
- `DB_PORT=5432`
- `DB_DATABASE=boitatech`
- `DB_USERNAME=seu_usuario`
- `DB_PASSWORD=sua_senha`

Para ambiente com conexão geoespacial dedicada (mesmo servidor ou outro), configure também:

- `DB_PGSQL_HOST`
- `DB_PGSQL_PORT`
- `DB_PGSQL_DATABASE`
- `DB_PGSQL_USERNAME`
- `DB_PGSQL_PASSWORD`

## Como rodar o projeto

Terminal 1:

1. `php artisan serve`

Terminal 2:

1. `npm run dev`

Aplicação disponível em: `http://127.0.0.1:8000`

## Ambiente limpo para apresentação

Para remover denúncias existentes, limpar uploads de denúncias e limpar cache:

1. `php artisan boitatech:prepare-presentation`

Para limpar e reexecutar seeders:

1. `php artisan boitatech:prepare-presentation --seed`

## Funcionalidades

- **Mapa ambiental 2D (Leaflet):** hotspots, heatmap e filtros temporais.
- **Mapa ambiental 3D (Cesium):** visualização de camadas ambientais e operações em escala Brasil.
- **Denúncias:** cadastro público com validação e confirmação.
- **Ecopontos:** consulta de locais de coleta e materiais aceitos.
- **BoitaNews:** feed curado de notícias ambientais.

## Estrutura do projeto

- `app/Http/Controllers` – controladores web e API
- `app/Services` – serviços de domínio e integrações
- `app/Models` – modelos Eloquent
- `app/Console/Commands` – comandos artisan operacionais
- `database/migrations` – versionamento de banco
- `database/seeders` – dados iniciais
- `resources/js` – frontend modular (mapas, denúncias, notícias)
- `resources/views` – templates Blade





