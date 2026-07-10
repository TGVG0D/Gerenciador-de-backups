# 📦 Gerenciador de Backups — Documentação Completa

> Documentação gerada em 08/07/2026. Cobre toda a arquitetura, segurança, APIs e fluxos da aplicação.

---

## 📋 Índice

1. [Visão Geral](#visão-geral)
2. [Estrutura de Arquivos](#estrutura-de-arquivos)
3. [Requisitos](#requisitos)
4. [Configuração Inicial](#configuração-inicial)
5. [Arquitetura](#arquitetura)
6. [Sistema de Autenticação](#sistema-de-autenticação)
7. [Sistema de Criptografia](#sistema-de-criptografia)
8. [APIs REST](#apis-rest)
9. [Frontend](#frontend)
10. [Segurança](#segurança)
11. [Webhook GitHub (Auto-Update)](#webhook-github)
12. [Referência de Variáveis de Ambiente](#variáveis-de-ambiente)
13. [Fluxos de Uso](#fluxos-de-uso)

---

## Visão Geral

O **Gerenciador de Backups** é uma aplicação web PHP single-tenant (usuário único) para organizar e acessar links de backups. O sistema permite criar, editar, excluir e categorizar backups com hierarquia de categorias. Toda a interface é protegida por autenticação com suporte opcional a **2FA (Two-Factor Authentication)** via TOTP (Google Authenticator). Os dados são armazenados em arquivos JSON criptografados no servidor.

### Funcionalidades Principais
- ✅ Login seguro com hash bcrypt
- ✅ 2FA opcional via TOTP (Google Authenticator)
- ✅ CRUD completo de backups
- ✅ Categorias e subcategorias hierárquicas
- ✅ Filtragem por categoria e busca em tempo real
- ✅ Criptografia AES-256-CBC com Salt + Pepper + PBKDF2 nos dados
- ✅ Atualização automática via GitHub Webhook
- ✅ Interface dark mode com glassmorphism
- ✅ URLs limpas sem extensão `.php`
- ✅ Proteção contra acesso direto a arquivos sensíveis

---

## Estrutura de Arquivos

```
Backups/
├── .env                  ← Variáveis de ambiente (credenciais, chaves)
├── .env.exemple          ← Modelo de exemplo do .env
├── .gitignore            ← Arquivos ignorados pelo Git
├── .htaccess             ← Regras Apache: segurança + URLs limpas
├── .git/                 ← Repositório Git
│
├── index.php             ← Página principal (requer login)
├── login.php             ← Página de login (Etapa 1 + Etapa 2 se 2FA)
├── profile.php           ← Página de perfil (alterar credenciais + 2FA)
├── 404.php               ← Página de erro 404 personalizada
│
├── auth.php              ← Core: sessão, login, criptografia, helpers
├── totp.php              ← Implementação TOTP (RFC 6238) nativa
│
├── api.php               ← API REST de backups (GET/POST/PUT/DELETE)
├── api_categorias.php    ← API REST de categorias (GET/POST/PUT/DELETE)
├── webhook.php           ← Endpoint GitHub Webhook (auto-update)
│
├── script.js             ← JavaScript frontend: AJAX, renderização, modais
├── style.css             ← Estilos globais (dark mode, glassmorphism)
│
├── dados.json            ← Banco de dados de backups (criptografado)
├── categorias.json       ← Banco de dados de categorias (criptografado)
└── webhook.log           ← Log de execuções do webhook (gerado automaticamente)
```

---

## Requisitos

| Componente | Versão mínima |
|---|---|
| PHP | 7.4+ (recomendado 8.x) |
| Apache | 2.4+ com `mod_rewrite` e `mod_headers` |
| Extensão PHP `openssl` | Obrigatório (criptografia) |
| Extensão PHP `hash` | Obrigatório (PBKDF2, HMAC) |
| Extensão PHP `json` | Obrigatório |
| Git | Qualquer versão (para webhook) |

> **XAMPP**: Todas as extensões necessárias já vêm ativadas por padrão.

---

## Configuração Inicial

### 1. Criar o arquivo `.env`
Copie `env.exemple` para `.env` e configure:

```ini
# Credenciais (geradas automaticamente ao trocar via perfil)
APP_USER_HASH=<bcrypt hash do usuário>
APP_PASS_HASH=<bcrypt hash da senha>

# 2FA (opcional - gerado ao ativar pelo perfil)
TOTP_SECRET=<chave base32 do authenticator>

# Criptografia dos dados (gerado automaticamente na 1ª gravação)
DATA_ENCRYPTION_KEY=<hex 64 chars>

# Webhook GitHub (gerado automaticamente na 1ª requisição ao webhook.php)
WEBHOOK_SECRET=<hex 40 chars>

# Branch a monitorar (padrão: main)
WEBHOOK_BRANCH=main
```

> Se o `.env` não existir ou estiver incompleto, a aplicação tenta ler variáveis legadas (`APP_USER`, `APP_PASS`) e migra automaticamente para hash.

### 2. Permissões de arquivo
No servidor Linux, garanta que o Apache possa escrever nos JSONs e no `.env`:

```bash
chown -R www-data:www-data /var/www/html/Backups/
chmod 644 .env dados.json categorias.json
```

---

## Arquitetura

```
Browser
  │
  ├─ GET  index.php        → Requer sessão → carrega HTML + script.js
  │        │
  │        └─ JS faz fetch → api.php (AJAX apenas)
  │                        → api_categorias.php (AJAX apenas)
  │
  ├─ GET/POST login.php    → auth.php processa login
  │
  ├─ GET/POST profile.php  → auth.php helpers → perfil e 2FA
  │
  └─ POST webhook.php      → GitHub → git pull automático
```

### Camadas
| Camada | Arquivos | Responsabilidade |
|---|---|---|
| **Autenticação** | `auth.php`, `totp.php` | Sessão, login, 2FA, criptografia |
| **Páginas** | `index.php`, `login.php`, `profile.php` | UI renderizada pelo PHP |
| **API** | `api.php`, `api_categorias.php` | CRUD via fetch/AJAX |
| **Frontend** | `script.js`, `style.css` | Interatividade, renderização dinâmica |
| **Integração** | `webhook.php` | GitHub auto-deploy |
| **Dados** | `dados.json`, `categorias.json` | Persistência (JSON cifrado) |
| **Config** | `.env`, `.htaccess` | Segredos e regras do servidor |

---

## Sistema de Autenticação

### Fluxo de Login

```
[Formulário login.php]
       │
       ▼
[auth.php — POST username+password]
       │
       ├── Lê .env
       │
       ├── Tem APP_USER_HASH? ──── Sim ──► password_verify(username, hash)
       │                                   password_verify(password, hash)
       │
       └── Não (legado) ──────────────► Compara texto puro → migra para hash
                                         (auto-upgrade na primeira autenticação)
                                         ↓
                                  Login OK?
                                       │
                           ┌───────────┴───────────┐
                     TOTP ativo?                 Não
                           │                       │
                    $_SESSION['2fa_pending']   $_SESSION['logged_in'] = true
                           │                       │
                    [Tela 2FA]              redirect → index.php
                           │
                    SimpleTOTP::verify()
                           │
                    $_SESSION['logged_in'] = true
                    redirect → index.php
```

### Proteção de Sessão

```php
ini_set('session.cookie_httponly', 1);  // JS não acessa o cookie
ini_set('session.use_only_cookies', 1); // Sem session ID na URL
session_regenerate_id(true);             // Previne session fixation
sleep(1);                                // Rate-limit em falha de login
```

### 2FA — TOTP (RFC 6238)

Implementado em `totp.php` sem dependências externas.

| Detalhe | Valor |
|---|---|
| Algoritmo | HMAC-SHA1 |
| Período | 30 segundos |
| Dígitos | 6 |
| Tolerância | ±1 janela (±30s de defasagem de relógio) |
| Armazenamento | `TOTP_SECRET` no `.env` (chave base32) |
| Compatível com | Google Authenticator, Authy, qualquer app TOTP |

**Ativação do 2FA:**
1. Sistema gera secret base32 aleatório
2. Exibe QR Code via `api.qrserver.com` (URL `otpauth://totp/...`)
3. Usuário escaneia e digita código de 6 dígitos
4. Sistema verifica com `SimpleTOTP::verify()` antes de salvar

---

## Sistema de Criptografia

Esta é a camada de segurança mais robusta do projeto, implementada em `auth.php`.

### Diagrama de Cifração

```
Entrada: array PHP (dados a salvar)
          │
          ▼
    json_encode() → JSON string pura
          │
          ▼
┌─────────────────────────────────────────────┐
│            Derivação de Chave               │
│                                             │
│  Salt ←── random_bytes(16) (novo a cada save)  │
│  PEPPER ← constante hardcoded em auth.php   │
│  KEY ←── DATA_ENCRYPTION_KEY do .env        │
│                                             │
│  Input PBKDF2 = KEY . PEPPER                │
│  Chave Derivada = hash_pbkdf2(              │
│      'sha256',                              │
│      input,                                 │
│      salt,                                  │
│      iterations=100.000,                    │
│      length=32 bytes                        │
│  )                                          │
└─────────────────────────────────────────────┘
          │
          ▼
┌─────────────────────────────────────────────┐
│              Cifração AES-256-CBC           │
│                                             │
│  IV ←── random_bytes(16) (novo a cada save) │
│  Ciphertext = openssl_encrypt(              │
│      plaintext, 'AES-256-CBC',              │
│      chave_derivada, OPENSSL_RAW_DATA, IV   │
│  )                                          │
└─────────────────────────────────────────────┘
          │
          ▼
  base64_encode( Salt[16] | IV[16] | Ciphertext )
          │
          ▼
  Grava no arquivo .json (string base64 opaca)
```

### Diagrama de Decifração

```
Lê arquivo .json → string base64
          │
          ▼
  base64_decode() → bytes brutos
          │
          ├── len ≤ 32? → Fallback (arquivo antigo em JSON puro)
          │
          ▼
  Extrai: Salt (bytes 0-15), IV (bytes 16-31), Ciphertext (bytes 32+)
          │
          ▼
  Deriva mesma chave (Salt + PEPPER + KEY → PBKDF2)
          │
          ▼
  openssl_decrypt() → JSON puro
          │
          ▼
  json_decode() → array PHP
```

### Por que é seguro?

| Componente | Proteção oferecida |
|---|---|
| **Salt aleatório** | Dois saves idênticos geram ciphertexts diferentes |
| **Pepper hardcoded** | Invasão do servidor + `.env` não basta para descriptografar |
| **PBKDF2 (100k iterações)** | Ataques de força bruta tornam-se computacionalmente inviáveis |
| **IV aleatório** | Elimina padrões no AES-CBC |
| **AES-256-CBC** | Cifra simétrica de padrão militar |
| **Fallback retrocompatível** | Dados antigos (JSON puro) continuam legíveis e são re-criptografados no próximo save |

> ⚠️ **Ponto de falha único**: A `DATA_ENCRYPTION_KEY` no `.env`. Sem ela, os dados são irrecuperáveis. **Faça backup do `.env`.**

---

## APIs REST

Ambas as APIs são acessíveis **apenas via AJAX** (header `X-Requested-With: XMLHttpRequest`). Requisições diretas pelo navegador retornam 404. Ambas exigem sessão autenticada.

### `api.php` — Backups

**Base URL:** `/api` (sem extensão, graças ao `.htaccess`)

#### `GET /api`
Retorna todos os backups (descriptografados).

**Resposta:**
```json
[
  {
    "id": "abc123",
    "nome": "Backup Site X",
    "link": "https://...",
    "data": "2026-07-08",
    "tamanho": "500 MB",
    "informacao": "Backup completo do banco de dados",
    "categoriaId": "cat_xyz",
    "timestamp": 1720000000
  }
]
```

#### `POST /api`
Cria um novo backup.

**Body JSON:**
```json
{
  "nome": "...",
  "link": "https://...",
  "data": "YYYY-MM-DD",
  "tamanho": "...",
  "informacao": "...",
  "categoriaId": "cat_..."
}
```

**Validações backend:**
- `nome`, `data`, `tamanho`, `informacao` → `htmlspecialchars` + `strip_tags` + `trim`
- `link` → `FILTER_SANITIZE_URL`
- ID gerado com `uniqid()`
- Timestamp UNIX adicionado automaticamente
- Inserido no **início** do array (mais recente primeiro)

#### `PUT /api`
Atualiza um backup existente. Body inclui `id` + campos editáveis. Mesmas validações do POST.

#### `DELETE /api`
Remove um backup pelo ID.

**Body JSON:** `{ "id": "abc123" }`

---

### `api_categorias.php` — Categorias

**Base URL:** `/api_categorias`

As categorias suportam **hierarquia de dois níveis**: categoria principal e subcategoria.

#### `GET /api_categorias`
Retorna todas as categorias.

**Resposta:**
```json
[
  { "id": "cat_001", "nome": "Produção", "parentId": null },
  { "id": "cat_002", "nome": "Banco de Dados", "parentId": "cat_001" }
]
```

#### `POST /api_categorias`
Cria uma categoria.

**Body JSON:**
```json
{ "nome": "Nome da Categoria", "parentId": "cat_001" }
```
`parentId` é `null` para categorias principais.

#### `PUT /api_categorias`
Atualiza nome e/ou pai de uma categoria.

#### `DELETE /api_categorias`
Remove uma categoria **e todas as suas subcategorias** (delete em cascata).

---

## Frontend

Todo o JavaScript está em `script.js`. A UI usa o padrão **SPA-like**: o PHP renderiza o shell HTML e o JS gerencia o estado e os dados dinamicamente.

### Inicialização

```
DOMContentLoaded
  └─ init()
       ├─ loadCategories() → GET /api_categorias
       │    ├─ renderCategoriesSidebar()
       │    ├─ renderCategoriesModalList()
       │    └─ populateCategorySelects()
       └─ loadBackups() → GET /api
            └─ renderBackups(allBackups)
```

### Estado Global

```javascript
let allBackups = [];       // Todos os backups carregados
let allCategories = [];    // Todas as categorias carregadas
let currentFilterId = null;  // Categoria selecionada (null = todas)
let currentSearchQuery = ''; // Texto de busca atual
```

### Renderização de Backups

`renderBackups()` aplica dois filtros em sequência:
1. **Filtro de categoria**: inclui backups da categoria selecionada **e suas subcategorias**
2. **Filtro de busca**: filtra por `nome` e `informacao` (case-insensitive)

### Modais

Dois modais gerenciados pelo JS:
- `#modal-add` — Adicionar/Editar backup (reutiliza o mesmo formulário)
- `#modal-cat` — Gerenciar categorias (criar, listar, editar, excluir)

Fecha ao clicar no overlay (fora do conteúdo) ou no botão `×`.

### Segurança XSS no Frontend

Função `escapeHTML()` sanitiza toda string antes de inserir no DOM:
```javascript
function escapeHTML(str) {
    return str.replace(/[&<>'"]/g, tag => ({
        '&': '&amp;', '<': '&lt;', '>': '&gt;',
        "'": '&#39;', '"': '&quot;'
    }[tag] || tag));
}
```

---

## Design Visual

Arquivo: `style.css`

### Design System

| Token CSS | Valor | Uso |
|---|---|---|
| `--bg-color` | `#0f172a` | Fundo escuro principal |
| `--text-color` | `#f8fafc` | Texto principal |
| `--primary-color` | `#3b82f6` | Azul (botões, destaques) |
| `--primary-hover` | `#2563eb` | Azul escuro (hover) |
| `--glass-bg` | `rgba(30,41,59,0.7)` | Cards e containers |
| `--glass-border` | `rgba(255,255,255,0.1)` | Bordas sutis |
| `--danger-color` | `#ef4444` | Vermelho (exclusão, logout) |

### Componentes Visuais

- **Glassmorphism** (`.glass`): backdrop-filter blur + borda translúcida
- **Cartões de backup** com barra lateral colorida animada no hover (`::before`)
- **Gradientes radiais** fixos no background (`rgba` azul + roxo)
- **Tipografia**: Google Fonts Inter (300, 400, 500, 600)
- **Animações**: transições CSS de 0.3s em todos os elementos interativos
- **Modal**: aparece com fade-in + slide-up (`transform: translateY`)
- **Responsivo**: grid auto-fill nos cards, form-row colapsa para 1 coluna em `≤768px`

---

## Segurança

### Camadas de Proteção

#### 1. Nível Apache (`.htaccess`)
```apache
# Bloqueia acesso direto a arquivos sensíveis → retorna 404
<FilesMatch "^\.env|dados\.json|categorias\.json|totp\.php|\.htaccess$">
    Require all denied
</FilesMatch>

# Erros 403 → redirecionados como 404 (evita enumerar arquivos)
ErrorDocument 403 /Backups/404
```

#### 2. Nível PHP — Validação de Origem nas APIs
```php
// Bloqueia acesso direto (não-AJAX)
$isAjax = strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
if (!$isAjax) { http_response_code(404); include '404.php'; exit; }

// Bloqueia requisições de outros domínios
if (parse_url($origin, PHP_URL_HOST) !== $host) { /* 404 */ }
if (parse_url($referer, PHP_URL_HOST) !== $host) { /* 404 */ }
```

#### 3. Nível PHP — Autenticação de Sessão
```php
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    http_response_code(404); include '404.php'; exit;
}
```

#### 4. Sanitização de Inputs
- Campos texto: `htmlspecialchars(strip_tags(trim(...)))`
- URLs: `filter_var(..., FILTER_SANITIZE_URL)`
- Frontend: `escapeHTML()` para inserção no DOM

#### 5. Senhas com bcrypt
- `password_hash($value, PASSWORD_DEFAULT)` → bcrypt com salt automático
- `password_verify()` → timing-safe
- `hash_equals()` nos códigos TOTP → evita timing attacks

#### 6. Proteção contra timing attacks
- `sleep(1)` em tentativas de login falhas
- `hash_equals()` em comparações de hash

#### 7. Dados em repouso (AES-256-CBC)
- `dados.json` e `categorias.json` são cifrados (ilegíveis sem a chave)

#### 8. Gitignore de dados sensíveis
```gitignore
.env               # Chaves e hashes
dados.json         # Dados dos backups
auth.php           # Código de autenticação
totp.php           # Código TOTP
*.log              # Logs (incluindo webhook.log)
```

---

## Webhook GitHub

Arquivo: `webhook.php`

Permite que cada `git push` na branch monitorada **atualize o servidor automaticamente**.

### Fluxo de Execução

```
GitHub Push Event
      │
      ▼
POST /webhook.php
      │
      ├─ 1. Valida método: deve ser POST
      │
      ├─ 2. Lê WEBHOOK_SECRET do .env
      │       └─ Se não existir → gera automaticamente e salva
      │
      ├─ 3. Verifica header X-Hub-Signature-256
      │       HMAC-SHA256(payload, secret) == signature?
      │       └─ Não → HTTP 403
      │
      ├─ 4. Verifica branch
      │       ref == "refs/heads/main"? (ou WEBHOOK_BRANCH do .env)
      │       └─ Não → HTTP 200 + {"status":"ignored"}
      │
      ├─ 5. Executa: git pull origin main
      │
      ├─ 6. Grava log em webhook.log com:
      │       - Timestamp
      │       - Hash do commit (8 chars)
      │       - Autor do push
      │       - Mensagem do commit
      │       - Output do git pull
      │
      └─ 7. Retorna HTTP 200 + JSON com status e output
```

### Configuração no GitHub

1. Acesse o repositório no GitHub
2. `Settings` → `Webhooks` → `Add webhook`
3. Configure:
   - **Payload URL**: `https://seu-dominio.com/webhook`
   - **Content type**: `application/json`
   - **Secret**: valor de `WEBHOOK_SECRET` no `.env`
   - **Which events**: `Just the push event`
4. Clique em `Add webhook`

### Exemplo de `webhook.log`

```
[2026-07-08 20:08:57] Branch: main | Commit: a1b2c3d4
Autor: seunome
Mensagem: fix: corrige validação de backup
Git output:
From https://github.com/user/repo
   a1b2c3d..e5f6g7h  main -> origin/main
Updating a1b2c3d..e5f6g7h
Fast-forward
 api.php | 4 ++--
------------------------------------------------------------
```

> ⚠️ **Permissão necessária**: O usuário do Apache/XAMPP deve ter permissão para executar `git` e gravar na pasta do projeto. Verifique com `git config --global --add safe.directory /caminho/do/projeto` se necessário.

---

## Variáveis de Ambiente

Arquivo: `.env` (nunca commitado no Git)

| Variável | Obrigatório | Gerada como | Descrição |
|---|---|---|---|
| `APP_USER_HASH` | ✅ | `password_hash()` bcrypt | Hash do nome de usuário |
| `APP_PASS_HASH` | ✅ | `password_hash()` bcrypt | Hash da senha |
| `APP_USER` | ❌ Legado | texto puro | Usuário legado (migrado automaticamente) |
| `APP_PASS` | ❌ Legado | texto puro | Senha legada (migrado automaticamente) |
| `TOTP_SECRET` | ❌ Opcional | base32 aleatório | Chave do Google Authenticator |
| `DATA_ENCRYPTION_KEY` | ✅ | `bin2hex(random_bytes(32))` | Chave mestra de criptografia dos JSONs |
| `WEBHOOK_SECRET` | ✅ | `bin2hex(random_bytes(20))` | Secret para validar o GitHub Webhook |
| `WEBHOOK_BRANCH` | ❌ Opcional | texto | Branch a monitorar (padrão: `main`) |

---

## Fluxos de Uso

### Primeiro Acesso (sem .env)
1. Acessa `/login`
2. O arquivo `.env` ainda tem `APP_USER`/`APP_PASS` em texto puro
3. Login bem-sucedido → sistema migra automaticamente para `APP_USER_HASH`/`APP_PASS_HASH`
4. Na primeira gravação de backup → `DATA_ENCRYPTION_KEY` é gerada e salva no `.env`

### Login Normal (sem 2FA)
```
/login → POST username + password → session['logged_in'] = true → /index
```

### Login com 2FA Ativo
```
/login → POST username + password
       → OK → session['2fa_pending'] = true
       → Tela 2FA exibida
       → POST totp_code
       → SimpleTOTP::verify() OK
       → session['logged_in'] = true → /index
```

### Ativar 2FA
```
/profile → Sistema gera secret base32
         → Exibe QR Code (api.qrserver.com)
         → Usuário escaneia com Google Authenticator
         → Digita código de 6 dígitos
         → POST action=enable_2fa + totp_secret + verify_code
         → SimpleTOTP::verify() → salva TOTP_SECRET no .env
```

### Adicionar Backup
```
/index → Clica "+ Adicionar Backup"
       → Preenche formulário no modal
       → JS faz POST /api com JSON
       → api.php: sanitiza, adiciona id + timestamp, decryptData() + encryptData()
       → dados.json atualizado (criptografado)
       → JS recarrega lista
```

### Filtrar por Categoria
```
/index → Clica em categoria na sidebar
       → JS: currentFilterId = cat.id
       → renderBackups() filtra allBackups por categoriaId
             (inclui também subcategorias da categoria selecionada)
       → Grid atualizado sem nova requisição HTTP
```

### Atualização Automática via GitHub
```
developer → git commit + git push
           → GitHub dispara POST /webhook
           → webhook.php valida HMAC-SHA256
           → git pull origin main executado no servidor
           → Código atualizado automaticamente
           → Log registrado em webhook.log
```

---

## Extensões Futuras Sugeridas

- **Multi-usuário**: Adicionar tabela de usuários em vez de `.env` único
- **Export/Import**: Exportar backups para CSV ou JSON descriptografado
- **Notificações**: Alertar via e-mail ou Telegram quando um backup antigo não foi atualizado
- **Histórico de versões**: Manter histórico de edições de cada backup
- **Tags**: Sistema de tags além de categorias hierárquicas
- **2FA por e-mail**: Opção de enviar código por e-mail além do TOTP

---


