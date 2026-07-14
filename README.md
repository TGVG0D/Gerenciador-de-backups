# 📦 GERENCIADOR DE BACKUPS
## Especificação Técnica de Software (SRS)

| Campo | Valor |
|---------|---------|
| Projeto | Gerenciador de Backups |
| Tipo | Aplicação Web PHP Single-Tenant |
| Versão da Documentação | 2.0 |
| Data | 13/07/2026 |
| Baseado na Documentação | Atualizada em 10/07/2026 :contentReference[oaicite:0]{index=0} |
| Status | Produção |

---

# 1. PROJETO & ARQUITETURA CORE

## 1.1 Visão Geral

O **Gerenciador de Backups** é uma aplicação web PHP de arquitetura **Single-Tenant** destinada ao gerenciamento centralizado de links de backup.

A aplicação fornece:

- Autenticação local baseada em bcrypt;
- Suporte opcional a autenticação de dois fatores (TOTP);
- CRUD completo de backups;
- Categorias hierárquicas;
- Persistência em arquivos JSON criptografados;
- Atualização automática baseada em Git;
- Sistema de logs criptografados;
- Integração opcional com Discord Webhook.

---

## 1.2 Arquitetura Geral

```text
┌─────────────────────┐
│      Browser        │
└──────────┬──────────┘
           │
           ▼
┌─────────────────────┐
│     Apache/PHP      │
└──────────┬──────────┘
           │
    ┌──────┼──────┐
    ▼      ▼      ▼
 Auth   API REST  UI
 Layer   Layer    Layer
    │      │       │
    └──┬───┴───┬───┘
       ▼       ▼
   Crypto   AutoUpdate
    Layer     Layer
       │
       ▼
Encrypted JSON Storage
```

---

## 1.3 Mapeamento de Diretórios

```tree
Backups/
├── .env
├── .env.exemple
├── .gitignore
├── .htaccess
│
├── index.php
├── login.php
├── settings.php
├── logs.php
├── 404.php
│
├── auth.php
├── crypto.php
├── totp.php
├── logger.php
│
├── api.php
├── api_categorias.php
├── auto_update.php
│
├── script.js
├── style.css
│
├── dados.json
├── categorias.json
├── activity.log
├── update.log
└── last_update.txt
```

---

## 1.4 Camadas do Sistema

| Camada | Arquivos | Responsabilidade |
|----------|----------|----------|
| Autenticação | auth.php, totp.php | Login, sessão e 2FA |
| Criptografia | crypto.php | AES-256-CBC, PBKDF2 |
| Persistência | dados.json, categorias.json | Armazenamento |
| API | api.php, api_categorias.php | CRUD REST |
| Interface | script.js, style.css | Renderização |
| Logs | logger.php | Auditoria |
| Atualização | auto_update.php | Deploy Pull-Based |
| Configuração | .env, .htaccess | Segredos e regras |

---

# 2. MODELO DE DADOS & CRIPTOGRAFIA

## 2.1 Arquitetura Criptográfica

### Componentes

| Componente | Origem |
|------------|---------|
| Salt | random_bytes(16) |
| Pepper | Constante interna |
| Master Key | DATA_ENCRYPTION_KEY |
| KDF | PBKDF2-SHA256 |
| Iterações | 100.000 |
| Cipher | AES-256-CBC |
| IV | random_bytes(16) |

---

## 2.2 Derivação de Chave

### Fórmula Matemática

\[
Input = DATA\_ENCRYPTION\_KEY \parallel PEPPER
\]

\[
DerivedKey =
PBKDF2_{SHA256}
(
Input,
Salt,
100000,
32
)
\]

Onde:

- Salt = 16 bytes aleatórios
- Comprimento da chave = 32 bytes
- Iterações = 100.000

---

## 2.3 Processo de Cifração

### Fórmula

\[
Ciphertext =
AES256CBC
(
Plaintext,
DerivedKey,
IV
)
\]

---

### Payload Persistido

```text
base64(
    Salt[16]
    ||
    IV[16]
    ||
    Ciphertext
)
```

---

## 2.4 Processo de Decifração

```text
Base64 Decode
       │
       ▼
Extrair Salt
Extrair IV
Extrair Ciphertext
       │
       ▼
PBKDF2(KEY || PEPPER)
       │
       ▼
AES-256-CBC Decrypt
       │
       ▼
JSON Decode
```

---

## 2.5 Compatibilidade Retroativa

### Dados Legados

Caso o arquivo possua:

```text
len(payload) <= 32
```

o sistema assume formato legado em JSON puro.

Após a próxima gravação:

```text
JSON Legado
      ▼
Leitura
      ▼
Recriptografia automática
      ▼
Novo formato AES-256-CBC
```

---

## 2.6 JSON Schema — dados.json

```json
[
  {
    "id": "string",
    "nome": "string",
    "link": "string",
    "data": "YYYY-MM-DD",
    "tamanho": "string",
    "informacao": "string",
    "categoriaId": "string|null",
    "senha": "string",
    "timestamp": 1720000000
  }
]
```

---

## 2.7 JSON Schema — categorias.json

```json
[
  {
    "id": "cat_001",
    "nome": "string",
    "parentId": "string|null"
  }
]
```

---

## 2.8 JSON Schema — activity.log

```json
[
  {
    "timestamp": "2026-07-10 20:15:00",
    "level": "SUCCESS",
    "event": "backup_created",
    "message": "string",
    "ip": "127.0.0.1",
    "extra": {}
  }
]
```

---

# 3. PROTOCOLOS DE API (RESTFUL)

## Requisitos Obrigatórios

Todas as rotas exigem:

### Sessão Ativa

```php
$_SESSION['logged_in'] === true
```

### Header AJAX

```http
X-Requested-With: XMLHttpRequest
```

### Mesma Origem

Validação obrigatória:

```text
Origin == Host
Referer == Host
```

---

# 3.1 API DE BACKUPS

Base URL:

```text
/api
```

## GET /api

| Campo | Valor |
|---------|---------|
| Método | GET |
| Rota | /api |
| Payload | Nenhum |
| Resposta | 200 |
| Sessão | Obrigatória |

### Retorno

```json
[
  {
    "id": "abc123"
  }
]
```

---

## POST /api

| Campo | Valor |
|---------|---------|
| Método | POST |
| Rota | /api |
| Resposta | 201 |
| Sessão | Obrigatória |

### Payload

```json
{
  "nome": "...",
  "link": "...",
  "data": "YYYY-MM-DD",
  "tamanho": "...",
  "informacao": "...",
  "categoriaId": "...",
  "senha": "..."
}
```

### Validações

| Campo | Validação |
|---------|---------|
| nome | htmlspecialchars + strip_tags + trim |
| data | htmlspecialchars + strip_tags + trim |
| tamanho | htmlspecialchars + strip_tags + trim |
| informacao | htmlspecialchars + strip_tags + trim |
| senha | htmlspecialchars + strip_tags + trim |
| link | FILTER_SANITIZE_URL |

---

## PUT /api

| Campo | Valor |
|---------|---------|
| Método | PUT |
| Rota | /api |
| Resposta | 200 |
| Sessão | Obrigatória |

Mesmo schema do POST com inclusão de:

```json
{
  "id": "abc123"
}
```

---

## DELETE /api

| Campo | Valor |
|---------|---------|
| Método | DELETE |
| Rota | /api |
| Resposta | 200 |
| Sessão | Obrigatória |

Payload:

```json
{
  "id": "abc123"
}
```

---

# 3.2 API DE CATEGORIAS

Base URL:

```text
/api_categorias
```

---

## GET /api_categorias

| Método | Rota | Payload | Resposta |
|----------|----------|----------|----------|
| GET | /api_categorias | Nenhum | 200 |

---

## POST /api_categorias

Payload:

```json
{
  "nome": "Produção",
  "parentId": null
}
```

---

## PUT /api_categorias

Payload:

```json
{
  "id": "cat_001",
  "nome": "Novo Nome",
  "parentId": null
}
```

---

## DELETE /api_categorias

Payload:

```json
{
  "id": "cat_001"
}
```

### Regra

```text
Delete Cascade
```

Ao remover uma categoria principal:

```text
Categoria Pai
   ▼
Subcategorias
   ▼
Exclusão automática
```

---

# 4. INTERFACE & EXPERIÊNCIA DO USUÁRIO (UX/UI)

## 4.1 Estado Global

```javascript
let allBackups = [];
let allCategories = [];
let currentFilterId = null;
let currentSearchQuery = '';
```

| Variável | Responsabilidade |
|-----------|-----------|
| allBackups | Cache de backups |
| allCategories | Cache de categorias |
| currentFilterId | Categoria selecionada |
| currentSearchQuery | Busca ativa |

---

## 4.2 Fluxo de Inicialização

```text
DOMContentLoaded
      │
      ▼
init()
      │
      ├── loadCategories()
      │
      └── loadBackups()
```

---

## 4.3 Fluxo de Filtragem

```text
renderBackups()
      │
      ├─ Filtro Categoria
      │
      └─ Filtro Texto
```

A busca é:

```text
Case Insensitive
```

Campos indexados:

- nome
- informacao

---

## 4.4 Proteção XSS

Todas as strings inseridas no DOM passam por:

```javascript
escapeHTML()
```

Mapeamentos:

```javascript
&  → &amp;
<  → &lt;
>  → &gt;
"  → &quot;
'  → &#39;
```

---

## 4.5 Senha ZIP

### Estado Oculto

```text
🔑 ••••••
```

### Estado Visível

```text
🔑 senha_real
```

---

## 4.6 Renderização Condicional

```javascript
backup.senha
    ? exibe bloco
    : oculta bloco
```

---

## 4.7 Modal de Cadastro

Campo:

```html
<input type="password">
```

Alternância:

```text
password ⇄ text
```

---

# 5. POLÍTICA RÍGIDA DE SEGURANÇA

| Vetor de Ataque | Mitigação |
|-----------------|------------|
| XSS Refletido | htmlspecialchars() |
| XSS DOM | escapeHTML() |
| XSS Persistente | Sanitização Backend |
| CSRF | Validação Origin + Referer |
| Session Fixation | session_regenerate_id(true) |
| Session Hijacking | cookie_httponly |
| Timing Attack | hash_equals() |
| Timing Attack Login | sleep(1) |
| Enumeração de Arquivos | ErrorDocument 403 → 404 |
| Acesso Direto | FilesMatch |
| Vazamento de Dados | AES-256-CBC |
| Vazamento de Segredos | .gitignore |

---

## 5.1 Proteção Apache

```apache
<FilesMatch "^\.env|dados\.json|categorias\.json|totp\.php|\.htaccess$">
    Require all denied
</FilesMatch>
```

---

## 5.2 Sessões

```php
ini_set('session.cookie_httponly',1);
ini_set('session.use_only_cookies',1);

session_regenerate_id(true);
```

---

## 5.3 Senhas

```php
password_hash()
password_verify()
```

Algoritmo:

```text
bcrypt
```

---

# 6. MICROSISTEMAS AUTÔNOMOS

# 6.1 AUTO-UPDATE (PULL-BASED)

## Algoritmo Sequencial

```text
1. Ler last_update.txt

2. Verificar:
   Agora - ÚltimaExecução >= 12h ?

3. NÃO
      ▼
      Exit

4. SIM
      ▼

5. Atualizar timestamp

6. Ler:
   AUTO_UPDATE_REPO
   PROTECTED_FILES

7. Atualizar .git/info/exclude

8. git fetch

9. Comparar commits

10. Existem commits novos?

11. NÃO
       ▼
       Log

12. SIM
       ▼

13. git reset --hard FETCH_HEAD

14. Registrar update.log

15. Registrar activity.log

16. Enviar Discord Webhook
```

---

## Arquivos Protegidos

```text
.env
dados.json
categorias.json
activity.log
update.log
last_update.txt
```

---

# 6.2 SISTEMA DE LOGS CRIPTOGRAFADOS

## Fluxo

```text
Evento
   ▼
logger.php
   ▼
AES-256-CBC
   ▼
activity.log
   ▼
Discord Webhook (Opcional)
```

---

## Estrutura da Entrada

```json
{
  "timestamp": "",
  "level": "",
  "event": "",
  "message": "",
  "ip": "",
  "extra": {}
}
```

---

## Política de Retenção

```text
Máximo: 500 entradas
```

Ao exceder:

```text
remove(entries_antigas)
```

---

## Integração Discord

| Level | Cor | Emoji |
|---------|---------|---------|
| SUCCESS | Verde | ✅ |
| INFO | Azul | ℹ️ |
| WARNING | Laranja | ⚠️ |
| ERROR | Vermelho | ❌ |

---

# 7. VARIÁVEIS DE AMBIENTE

| Variável | Obrigatória | Finalidade |
|-----------|-----------|-----------|
| APP_USER_HASH | Sim | Usuário bcrypt |
| APP_PASS_HASH | Sim | Senha bcrypt |
| TOTP_SECRET | Não | 2FA |
| DATA_ENCRYPTION_KEY | Sim | Chave mestra |
| AUTO_UPDATE_REPO | Não | Repositório Git |
| PROTECTED_FILES | Não | Lista protegida |
| DISCORD_WEBHOOK_URL | Não | Integração Discord |

---

# 8. RETROCOMPATIBILIDADE

## Credenciais Legadas

Suportado:

```env
APP_USER=
APP_PASS=
```

Fluxo:

```text
Login Legado
      ▼
Autenticação
      ▼
password_hash()
      ▼
APP_USER_HASH
APP_PASS_HASH
```

---

## Dados Legados

Suportado:

```json
{}
```

sem criptografia.

Migração:

```text
Leitura
   ▼
Detecta formato antigo
   ▼
Próxima gravação
   ▼
AES-256-CBC
```

---

## Logs Legados

Formato suportado:

```text
JSON por linha
```

Migração automática:

```text
Leitura
   ▼
Conversão
   ▼
Criptografia
```

---

# STATUS FINAL DA ESPECIFICAÇÃO

| Item | Status |
|--------|--------|
| Arquitetura | ✅ Documentada |
| APIs | ✅ Documentadas |
| Criptografia | ✅ Formalizada |
| Segurança | ✅ Formalizada |
| UX/UI | ✅ Documentada |
| Auto-Update | ✅ Documentado |
| Logs | ✅ Documentados |
| Retrocompatibilidade | ✅ Documentada |
