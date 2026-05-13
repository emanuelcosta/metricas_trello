# Métricas Trello — Contexto do Projeto para Claude Code

## Visão Geral

Aplicação PHP single-file que gera gráficos de **burndown** e **burnup** a partir de JSONs exportados do Trello. Usada por equipes ágeis para visualizar progresso de sprints.

- **Stack:** PHP 8.2 + Bootstrap 5 + Chart.js 4 (UMD) + Apache (XAMPP)
- **Persistência:** Arquivos JSON (sem banco relacional)
- **Arquivo principal:** `index.php` (~1150 linhas, controller + view)
- **Raiz:** `c:\xampp_82\htdocs\metricas_trello`

---

## Estrutura de Arquivos

```
metricas_trello/
├── index.php                  ← controller + view (toda a lógica)
├── dados.json                 ← JSON de referência/fallback
├── app_config.json            ← configuração ativa
├── uploads_index.json         ← índice dos arquivos enviados
├── commit_message_config.json ← convenção de commits
├── assets/
│   ├── bootstrap/bootstrap.min.css
│   └── chartjs/chart.umd.min.js
└── uploads/                   ← JSONs enviados pelos usuários
```

---

## Seções do index.php

| Linhas | Seção |
|--------|-------|
| 1–10 | Inicialização de paths (`$baseDir`, `$uploadsDir`, etc.) |
| 12–184 | Funções auxiliares (`loadConfig`, `saveConfig`, `buildFileLabel`, `saveUploadsIndex`) |
| 186–367 | Handlers POST (upload, refresh Trello, salvar config de listas) |
| 369–500 | Carregamento e resolução do arquivo JSON ativo |
| 502–800 | Cálculo de demandas e séries temporais |
| 802–840 | Aplicação do filtro de datas |
| 842–1050 | Renderização HTML |
| 1050–1100 | Script Chart.js (compressSeries + inicialização dos gráficos) |

---

## Regras de Negócio

### Contagem de demandas
- Card **sem checklist** = 1 demanda
- Card **com checklist** = N demandas (um por checkItem, não por card)
- Apenas cards em listas configuradas (`pending_list_ids` + `completed_list_ids`) são contados

### Conclusão
- Card sem checklist: concluído se `card.idList` ∈ `completed_list_ids`
- CheckItem: concluído quando `state === 'complete'`
- Datas de conclusão vêm de ações `updateCheckItemStateOnCard` ou `updateCard`

### Filtro de datas
- Formato: `YYYY-MM-DD`, inclusivo em ambas as extremidades
- Afeta apenas os gráficos, não os contadores totais
- Interpola série existente — não recalcula do zero

---

## Padrões de Código — OBRIGATÓRIOS

### 1. Cabeçalho do arquivo
```php
declare(strict_types=1);
```

### 2. Validação de entrada
```php
// ✅ correto
$input = isset($_GET['key']) ? trim((string)$_GET['key']) : '';

// ❌ errado
$input = $_GET['key'];
```

### 3. Validação de array
```php
// ✅ correto
if (isset($arr['key']) && is_string($arr['key'])) {
    $val = trim($arr['key']);
}

// ❌ errado — não valida tipo
if (!empty($arr['key'])) { $val = $arr['key']; }
```

### 4. Saída HTML — XSS prevention
```php
// ✅ correto
echo htmlspecialchars($val, ENT_QUOTES, 'UTF-8');

// ❌ errado
echo $val;
```

### 5. JSON encode/decode
```php
// ✅ correto
json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
json_decode($raw, true);   // sempre true → array

// ❌ errado
json_encode($data);        // sem flags
json_decode($raw);         // retorna objeto
```

### 6. Paths
```php
// ✅ correto
$path = $baseDir . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'file.json';

// ❌ errado
$path = $baseDir . '/uploads/file.json';
```

### 7. Datas — sempre GMT
```php
// ✅ correto
$now = gmdate('c');                // ISO-8601 GMT para config

// ❌ errado
date('Y-m-d H:i:s');             // usa timezone do servidor
```

---

## Tratamento de Erros — Comportamento Esperado

| Cenário | Ação correta |
|---------|-------------|
| Arquivo não é `.json` | Mostrar erro, não redirecionar |
| JSON inválido | Mostrar erro, não redirecionar |
| Upload falha ao mover | Mostrar erro, não redirecionar |
| shortUrl inválida | Mostrar erro, permitir retry |
| Timeout no download (30s) | Mostrar erro, permitir retry |
| Lista pending = lista completed | Mostrar erro com mensagem clara |
| Data inválida (`YYYY-MM-DD`) | Mostrar erro, limpar filtro |
| `start_date > end_date` | Mostrar erro, limpar filtro |

**Nunca use redirect em caso de erro.** Erros são exibidos inline na página.

---

## Schema de Configuração

### `app_config.json`
```json
{
  "latest_uploaded_file": "uploads/trello_YYYY-MM-DD_HH-mm-ss_ID.json",
  "updated_at": "ISO-8601-GMT",
  "board_lists_index": { "1": { "id": "...", "name": "..." } },
  "demand_lists": {
    "pending_list_ids": ["..."],
    "completed_list_ids": ["..."]
  },
  "demand_fields": {
    "todo_list_id": "",
    "done_list_id": "",
    "board_id": ""
  }
}
```

### `uploads_index.json`
```json
{
  "atualizado_em": "DD/MM/YYYY HH:MM:SS",
  "indice": "id_trello",
  "arquivos": {
    "BOARD_ID": [
      {
        "id_trello": "...",
        "short_url": "https://trello.com/b/...",
        "nome_arquivo": "trello_...",
        "data": "DD/MM/YYYY",
        "hora": "HH:MM:SS",
        "data_atualizacao_json": "DD/MM/YYYY HH:MM:SS"
      }
    ]
  }
}
```

---

## Fluxo de Requisições

**GET** → carregar config → listar arquivos → resolver arquivo → parsear JSON → calcular demandas → renderizar HTML+JS

**POST upload** → validar → mover para `uploads/` com timestamp → atualizar configs → redirect `?upload=ok`

**POST refresh** → validar shortUrl → download (30s timeout, SSL permissivo) → validar JSON → salvar → atualizar configs → redirect `?refreshed=ok`

**POST config listas** → validar (pending ≠ completed) → salvar → redirect `?config_lists=ok`

---

## Pontos de Extensão Aprovados

**Baixo risco:** novos tipos de gráfico, exportação CSV, estatísticas extras (velocidade, taxa de conclusão)

**Risco moderado:** integração API Trello v3, sincronização periódica, multi-board

**Alto risco (discutir antes):** BD relacional, WebSocket, ML, integrações externas

---

## Convenção de Commits

```
Formato: {tipo}: {ação no futuro do pretérito}
Tipos:   feat | fix | refactor | docs | chore | test

Exemplos:
  feat: adicionaria exportação em CSV
  fix: corrigiria cálculo de demandas sem checklist
  refactor: extrairia lógica de datas para função auxiliar
```

---

## Checklist — Toda Implementação

- [ ] `declare(strict_types=1)` presente
- [ ] Entradas validadas com `isset()` + verificação de tipo
- [ ] Valores de usuário com `trim()`
- [ ] Saída HTML com `htmlspecialchars($v, ENT_QUOTES, 'UTF-8')`
- [ ] `json_encode` com `JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE`
- [ ] `json_decode($raw, true)` (com `true`)
- [ ] Paths com `DIRECTORY_SEPARATOR`
- [ ] Datas em GMT com `gmdate()`
- [ ] Arquivo `dados.json` como fallback
- [ ] Erros exibidos inline (sem redirect em falha)
- [ ] `PROJECT_FULL_CONTEXT.md` atualizado se arquitetura mudar
