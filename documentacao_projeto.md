# 📦 Gerenciador de Backups — Documentação Completa

> Documentação atualizada em 10/07/2026. Cobre toda a arquitetura, segurança, APIs e fluxos da aplicação.

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
11. [Atualização Automática (Pull-Based)](#atualização-automática)
12. [Sistema de Logs (Criptografado)](#sistema-de-logs)
13. [Referência de Variáveis de Ambiente](#variáveis-de-ambiente)
14. [Fluxos de Uso](#fluxos-de-uso)

---

## Visão Geral

O **Gerenciador de Backups** é uma aplicação web PHP single-tenant (usuário único) para organizar e acessar links de backups. O sistema permite criar, editar, excluir e categorizar backups com hierarquia de categorias. Toda a interface é protegida por autenticação com suporte opcional a **2FA (Two-Factor Authentication)** via TOTP (Google Authenticator). Os dados são armazenados em arquivos JSON criptografados no servidor.

### Funcionalidades Principais
- ✅ Login seguro com hash bcrypt
- ✅ 2FA opcional via TOTP (Google Authenticator)
- ✅ CRUD completo de backups
- ✅ Categorias e subcategorias hierárquicas
- ✅ Filtragem por categoria e busca em tempo real
- ✅ Criptografia AES-256-CBC com Salt + Pepper + PBKDF2 nos dados, categorias E logs
- ✅ Senha opcional para arquivos ZIP protegidos (visualização com máscara)
- ✅ Atualização automática silenciosa (Pull-Based) a cada 12h
- ✅ Sistema de logs criptografado com Discord Webhook
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
├── settings.php          ← Página de configurações (alterar credenciais + 2FA)
├── 404.php               ← Página de erro 404 personalizada
│
├── auth.php              ← Autenticação, sessão e rotas de login
├── totp.php              ← Implementação TOTP (RFC 6238) nativa
├── crypto.php            ← Criptografia AES-256-CBC, PBKDF2, read/write env
│
├── api.php               ← API REST de backups (GET/POST/PUT/DELETE)
├── api_categorias.php    ← API REST de categorias (GET/POST/PUT/DELETE)
├── auto_update.php       ← Sistema de atualização autônomo (pull a cada 12h)
│
├── script.js             ← JavaScript frontend: AJAX, renderização, modais
├── style.css             ← Estilos globais (dark mode, glassmorphism)
│
├── dados.json            ← Banco de dados de backups (criptografado)
├── categorias.json       ← Banco de dados de categorias (criptografado)
├── activity.log          ← Log de atividades do painel (criptografado)
└── update.log            ← Log de execuções do autoupdate (criptografado)
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

# Atualização Automática (Configurado pelo painel)
AUTO_UPDATE_REPO=https://github.com/TGVG0D/Gerenciador-de-backups.git
PROTECTED_FILES=.env, dados.json, categorias.json, activity.log, update.log, last_update.txt
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
  ├─ GET/POST settings.php → auth.php helpers → configurações e 2FA
  │
  └─ POST webhook.php      → GitHub → git pull automático
```

### Camadas
| Camada | Arquivos | Responsabilidade |
|---|---|---|
| **Autenticação** | `auth.php`, `totp.php` | Sessão, login, 2FA |
| **Criptografia** | `crypto.php` | Funções core de Salt+Pepper, PBKDF2 e AES-256 |
| **Páginas** | `index.php`, `login.php`, `settings.php` | UI renderizada pelo PHP |
| **API** | `api.php`, `api_categorias.php` | CRUD via fetch/AJAX |
| **Frontend** | `script.js`, `style.css` | Interatividade, renderização dinâmica |
| **Integração** | `auto_update.php` | GitHub pull auto-deploy a cada 12h |
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

Esta é a camada de segurança mais robusta do projeto, implementada em `crypto.php`.

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
    "senha": "minhasenha123",
    "timestamp": 1720000000
  }
]
```

> **Campo `senha`**: Contém a senha do arquivo ZIP, se existir. Se o backup não tiver senha, o campo será uma string vazia `""`. Este campo é **criptografado em repouso** junto com todos os demais dados no `dados.json` via AES-256-CBC.

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
  "categoriaId": "cat_...",
  "senha": "senhaOpcional"
}
```

> **Campo `senha` (opcional)**: Se preenchido, armazena a senha do arquivo ZIP de backup. O campo é sanitizado com `htmlspecialchars(strip_tags(trim(...)))` no backend. Se enviado vazio ou omitido, será salvo como string vazia `""`.

**Validações backend:**
- `nome`, `data`, `tamanho`, `informacao`, `senha` → `htmlspecialchars` + `strip_tags` + `trim`
- `link` → `FILTER_SANITIZE_URL`
- `senha` → Se vazio ou omitido, salvo como `""` (string vazia)
- ID gerado com `uniqid()`
- Timestamp UNIX adicionado automaticamente
- Inserido no **início** do array (mais recente primeiro)

#### `PUT /api`
Atualiza um backup existente. Body inclui `id` + campos editáveis. Mesmas validações do POST. Alterações de cada campo são registradas no log (inclusive senha alterada).

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

### Senha do ZIP nos Cards

Quando um backup possui uma senha de ZIP, o card exibe uma seção especial:

```
┌──────────────────────────────────────────┐
│  Backup Site X                    500 MB │
│  📁 Produção                             │
│  08/07/2026                              │
│                                          │
│  🔑 •••••• [👁 Ver]                       │  ← Senha mascarada
│                                          │
│  Backup completo do banco...             │
│                                          │
│  [Acessar Link]     [✏️] [🗑️]            │
└──────────────────────────────────────────┘
```

#### Comportamento da Máscara de Senha

| Estado | Texto exibido | Botão |
|---|---|---|
| **Oculta** (padrão) | `••••••` (6 bullets) | `👁 Ver` |
| **Visível** | Senha real em azul (`#3b82f6`) | `🙈 Ocultar` |

#### Implementação Frontend (`script.js`)

```javascript
// Renderização condicional: só exibe se backup.senha existir
const senhaHtml = backup.senha ? `
    <span class="senha-mask">••••••</span>
    <span class="senha-real" style="display:none">${escapeHTML(backup.senha)}</span>
    <button onclick="toggleSenhaCard(this)">👁 Ver</button>
` : '';
```

```javascript
// Alterna visibilidade da senha no card
window.toggleSenhaCard = function(btn) {
    const mask = container.querySelector('.senha-mask');
    const real = container.querySelector('.senha-real');
    if (real.style.display === 'none') {
        mask.style.display = 'none';
        real.style.display = 'inline';
        btn.innerHTML = '🙈 Ocultar';
    } else {
        mask.style.display = 'inline';
        real.style.display = 'none';
        btn.innerHTML = '👁 Ver';
    }
}
```

#### Campo no Formulário de Backup (`index.php`)

O campo de senha no modal de adicionar/editar backup usa `type="password"` com botão de toggle:

```html
<label>🔑 Senha do ZIP <span>(opcional — preencha se o arquivo tem senha)</span></label>
<input type="password" id="senha" name="senha" placeholder="Deixe vazio se não há senha">
<button type="button" onclick="toggle">👁</button>  <!-- Alterna password/text -->
```

| Detalhe | Valor |
|---|---|
| Tipo do campo | `password` (oculto por padrão) |
| Botão de toggle | Alterna entre `type="password"` e `type="text"` |
| Obrigatório | ❌ Não — campo totalmente opcional |
| Sanitização backend | `htmlspecialchars(strip_tags(trim(...)))` |
| Armazenamento | Criptografado junto com os demais dados no `dados.json` (AES-256-CBC) |
| Ao editar | O campo é preenchido com a senha salva (`backup.senha`) |
| Se vazio | Salvo como string vazia `""`, card não exibe seção de senha |

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
- `dados.json`, `categorias.json` e `activity.log` são cifrados (ilegíveis sem a chave + pepper)

#### 8. Gitignore de dados sensíveis
```gitignore
.env               # Chaves e hashes
dados.json         # Dados dos backups
auth.php           # Código de autenticação
totp.php           # Código TOTP
*.log              # Logs (incluindo webhook.log)
```

---

## Atualização Automática

Arquivo: `auto_update.php`

O sistema verifica automaticamente se há atualizações no repositório oficial a cada **12 horas**, de forma silenciosa e transparente, sem necessidade de configurar Webhooks no GitHub.

### Como Funciona

```
Usuário acessa /index.php
      │
      ▼
<script> dispara fetch('auto_update.php') (fire-and-forget)
      │
      ▼
auto_update.php
      │
      ├─ 1. Lê last_update.txt → contém timestamp da última verificação
      │       └─ Se diferença < 12h → exit (nada a fazer)
      │
      ├─ 2. Atualiza last_update.txt com timestamp atual
      │
      ├─ 3. Lê AUTO_UPDATE_REPO e PROTECTED_FILES do .env
      │
      ├─ 4. Escreve lista de arquivos protegidos em .git/info/exclude
      │
      ├─ 5. git fetch <repo_url> main
      │
      ├─ 6. git rev-list HEAD...FETCH_HEAD --count
      │       └─ Se 0 commits novos → registra no log e exit
      │
      ├─ 7. Se há commits novos:
      │       └─ git reset --hard FETCH_HEAD
      │
      ├─ 8. Grava resultado no update.log
      │
      └─ 9. Chama logEvent() → registra no activity.log + Discord
```

### Configuração pelo Painel

No painel de **Configurações** (`settings.php`), o usuário pode configurar:

| Campo | Descrição | Padrão |
|---|---|---|
| **URL do Repositório** | URL HTTPS do repositório Git público | `https://github.com/TGVG0D/Gerenciador-de-backups.git` |
| **Arquivos Protegidos** | Lista de arquivos que NUNCA serão sobrescritos | `.env, dados.json, categorias.json, activity.log, update.log, last_update.txt` |

### Exemplo de `update.log`

```
[2026-07-10 20:00:00] Atualização executada! Commits novos: 3
Git Fetch Output:
From https://github.com/TGVG0D/Gerenciador-de-backups
   a1b2c3d..e5f6g7h  main       -> FETCH_HEAD
Git Reset Output:
HEAD is now at e5f6g7h fix: corrige validação
------------------------------------------------------------
```

> ⚠️ **Permissão necessária**: O usuário do Apache/XAMPP deve ter permissão para executar `git` e gravar na pasta do projeto.

---

## Sistema de Logs

Arquivo: `logger.php`

O sistema de logs registra **todas as ações importantes** (login, criação/edição/exclusão de backups e categorias, alterações de perfil, deploy automático) de duas formas:

### 1. Log Local Criptografado (`activity.log`)

Os logs são armazenados como um **array JSON criptografado** usando o mesmo sistema AES-256-CBC + Salt + Pepper + PBKDF2 dos dados. Isso garante que mesmo com acesso direto ao servidor, os logs são ilegíveis.

**Formato de cada entrada:**
```json
{
  "timestamp": "2026-07-10 20:15:00",
  "level": "SUCCESS",
  "event": "backup_created",
  "message": "Novo backup adicionado: Backup Site X",
  "ip": "127.0.0.1",
  "extra": { "Tamanho": "500 MB" }
}
```

| Campo | Tipo | Descrição |
|---|---|---|
| `timestamp` | string | Data e hora no formato `Y-m-d H:i:s` |
| `level` | enum | `SUCCESS`, `INFO`, `WARNING` ou `ERROR` |
| `event` | string | Identificador do evento (ex: `backup_created`, `login_success`) |
| `message` | string | Descrição legível do evento |
| `ip` | string | Endereço IP do cliente |
| `extra` | object | Dados adicionais variáveis (ex: campos alterados) |

**Limite de armazenamento:** Máximo de **500 entradas** no `activity.log`. Quando ultrapassado, as mais antigas são descartadas automaticamente.

**Retrocompatibilidade:** Se o `activity.log` contiver logs antigos em texto puro (JSON por linha), o sistema os lê normalmente e os re-criptografa no próximo evento.

### 2. Discord Webhook (Notificação em Tempo Real)

Se `DISCORD_WEBHOOK_URL` estiver configurado no `.env`, cada evento também é enviado como um **embed** no Discord.

| Nível | Cor | Emoji |
|---|---|---|
| SUCCESS | Verde (`#2ECC71`) | ✅ |
| INFO | Azul (`#3498DB`) | ℹ️ |
| WARNING | Laranja (`#E67E22`) | ⚠️ |
| ERROR | Vermelho (`#E74C3C`) | ❌ |

### Visualização (`logs.php`)

A página de logs (`/logs`) descriptografa o `activity.log` e exibe as entradas em ordem cronológica reversa (mais recentes primeiro).

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
| `DATA_ENCRYPTION_KEY` | ✅ | `bin2hex(random_bytes(32))` | Chave mestra de criptografia dos JSONs e logs |
| `AUTO_UPDATE_REPO` | ❌ Opcional | texto (URL) | URL do repositório Git para auto-update (padrão: repositório oficial) |
| `PROTECTED_FILES` | ❌ Opcional | texto (lista CSV) | Arquivos protegidos contra sobrescrita pelo auto-update |
| `DISCORD_WEBHOOK_URL` | ❌ Opcional | texto (URL) | URL do webhook Discord para notificações em tempo real |

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

### Adicionar Backup (com Senha do ZIP)
```
/index → Clica "+ Adicionar Backup"
       → Preenche formulário no modal
       → (Opcional) Preenche campo "Senha do ZIP"
       → JS faz POST /api com JSON (inclui campo "senha")
       → api.php: sanitiza TODOS os campos (incluindo senha), adiciona id + timestamp
       → decryptData() do dados.json atual → adiciona backup → encryptData()
       → dados.json atualizado (criptografado com Salt+Pepper+PBKDF2+AES-256-CBC)
       → JS recarrega lista
       → Se senha existir: card exibe 🔑 com máscara ••••••
```

### Filtrar por Categoria
```
/index → Clica em categoria na sidebar
       → JS: currentFilterId = cat.id
       → renderBackups() filtra allBackups por categoriaId
             (inclui também subcategorias da categoria selecionada)
       → Grid atualizado sem nova requisição HTTP
```

### Atualização Automática (Pull-Based)
```
Usuário acessa /index
       → <script> dispara fetch('auto_update.php')
       → auto_update.php verifica last_update.txt
       → Se 12h passaram: git fetch + compara commits
       → Se há commits novos: git reset --hard FETCH_HEAD
       → Código atualizado (exceto arquivos protegidos)
       → Log registrado em update.log + activity.log + Discord
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


