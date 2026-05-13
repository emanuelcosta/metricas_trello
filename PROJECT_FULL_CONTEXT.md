# CONTEXTO COMPLETO DO PROJETO - Métricas Trello

**Última atualização:** 2026-05-12  
**Versão:** 1.0  
**Ambiente:** PHP 8.2 + Apache (XAMPP)

---

## 1. VISÃO GERAL

### Nome e Propósito
- **Nome:** Métricas Trello
- **Objetivo:** Gerar gráficos de burndown e burnup a partir de JSON exportado do Trello
- **Público:** Equipes que usam Trello como ferramenta de gestão ágil

### Tecnologia
- **Backend:** PHP 8.2
- **Frontend:** Bootstrap 5 + Chart.js 4 (UMD)
- **Banco de dados:** Arquivos JSON (sem BD relacional)
- **Servidor:** Apache (XAMPP)
- **Diretório raiz:** `c:\xampp_82\htdocs\metricas_trello`

---

## 2. ARQUITETURA E ESTRUTURA DE ARQUIVOS

### Hierarquia do Projeto
```
metricas_trello/
├── index.php                    [Arquivo principal - controler + view]
├── dados.json                   [Arquivo JSON padrão de referência]
├── app_config.json              [Configuração ativa da aplicação]
├── uploads_index.json           [Índice dos arquivos enviados]
├── PROJECT_FULL_CONTEXT.md      [Este arquivo]
├── project_context.md           [Contexto anterior - será descontinuado]
├── json_structure_context.md    [Estrutura do JSON do Trello]
├── commit_message_config.json   [Padrão de commits]
├── assets/
│   ├── bootstrap/
│   │   └── bootstrap.min.css
│   └── chartjs/
│       └── chart.umd.min.js
└── uploads/
    ├── trello_2026-05-12_20-34-47_sZKIkLob_-_sigo.json
    ├── trello_2026-05-12_20-41-24_8vE9MR7k_-_almoxarifado.json
    ├── trello_2026-05-12_20-42-13_8vE9MR7k_-_almoxarifado__1_.json
    ├── trello_2026-05-12_22-29-44_clohx19c_-_jumbsofware.json
    └── trello_20260512_201301_Lgwv271P_-_licitacao__1_.json
```

---

## 3. FLUXO DE DADOS

### 1. Entrada
- **Upload de JSON:** Usuário envia arquivo JSON exportado do Trello
- **Seleção de arquivo:** Usuário escolhe qual arquivo usar (padrão ou anterior)
- **Atualização do Trello:** Refazer download de board do Trello via shortUrl

### 2. Processamento (em `index.php`)
1. Carregar JSON selecionado
2. Extrair dados: cards, checklists, actions, lists
3. Mapear relacionamentos
4. Calcular demandas (cards + checklist items)
5. Rastrear datas de criação, conclusão, movimentação
6. Gerar séries de dados por data
7. Aplicar filtro de datas (se houver)
8. Comprimir série para máximo 20 pontos (performance)

### 3. Saída
- **Gráficos:** Burnup (barras) + Burndown (barras)
- **Índices:** Contadores de demandas (total, concluídas, abertas)
- **Metadados:** ID do board, URL, membros, última atividade

---

## 4. ESTRUTURA DO `index.php`

### Seções Principais

#### A. Inicialização (linhas 1-10)
```php
$baseDir = __DIR__;
$defaultJsonPath = $baseDir . DIRECTORY_SEPARATOR . 'dados.json';
$uploadsDir = $baseDir . DIRECTORY_SEPARATOR . 'uploads';
$configPath = $baseDir . DIRECTORY_SEPARATOR . 'app_config.json';
$uploadsIndexPath = $baseDir . DIRECTORY_SEPARATOR . 'uploads_index.json';
```

#### B. Funções Auxiliares (linhas 12-184)
1. `loadConfig(string $path): array`
   - Carrega JSON de configuração
   - Retorna array vazio se não existir

2. `saveConfig(string $path, array $config): bool`
   - Salva configuração em JSON formatado
   - Usa `JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE`

3. `buildFileLabel(string $relativePath): string`
   - Formata nome de arquivo para exibição
   - Suporta dois padrões de timestamp:
     - `trello_YYYY-MM-DD_HH-mm-ss_...json`
     - `trello_YYYYMMDD_HHMMSS_...json`

4. `saveUploadsIndex(string $uploadsDir, string $indexPath): bool`
   - Escaneia pasta `uploads/`
   - Cria índice por `id_trello`
   - Agrupa múltiplas versões do mesmo board

#### C. Handlers de POST (linhas 186-367)
1. **Upload de arquivo**
   - Valida extensão `.json`
   - Valida JSON válido
   - Renomeia com timestamp e sanitização
   - Handles duplicatas com suffix

2. **Refresh de Trello**
   - Valida shortUrl do Trello
   - Faz download via `file_get_contents()` com context stream
   - Suporta SSL com certificado auto-assinado

3. **Salvar configuração de listas**
   - Valida listas pending vs completed
   - Impede lista ser ao mesmo tempo pending e completed

#### D. Carregamento de Dados (linhas 369-500)
1. Carregar arquivos disponíveis
2. Criar índice em memória
3. Resolver arquivo selecionado (GET param ou config)
4. Validar existência do arquivo
5. Carregar e parsear JSON do board

#### E. Cálculo de Demandas (linhas 502-800)
1. **Mapear checklists**: `checkItemsByCard`, `checkItemsDataByCard`
2. **Ordenar actions por data**
3. **Rastrear eventos:**
   - `cardCreatedAt`: quando card foi criado
   - `cardFirstActionAt`: primeira ação no card
   - `cardDoneAt`: quando movido para lista de concluídos
   - `checkItemCompletedAt`: quando item de checklist foi concluído

4. **Contar demandas:**
   - Card sem checklist = 1 demanda
   - Card com checklist = quantidade de `checkItems`
   - Filtrar por listas configuradas (pending + completed)

5. **Construir séries temporais:**
   - Acumular escopo por data
   - Acumular conclusões por data
   - Gerar série de demandas restantes

#### F. Aplicar Filtro de Datas (linhas 802-840)
1. Validar formato `YYYY-MM-DD`
2. Validar data_inicio ≤ data_fim
3. Interpolar série para período selecionado
4. Recalcular contadores (período vs total)

#### G. Renderização HTML (linhas 842-1050)
1. **Seção de upload**
   - Formulário multipart
   - Mensagens de sucesso/erro

2. **Seção de seleção de arquivos**
   - Lista com botões "Usar"
   - Botão "Atualizar do Trello" (se disponível shortUrl)

3. **Seção de configuração de listas**
   - Checkboxes para listas pending
   - Checkboxes para listas completed
   - Validação no servidor

4. **Seção de filtro de datas**
   - Inputs de data (`type="date"`)
   - Botões "Filtrar" e "Limpar"

5. **Seção de metadados**
   - ID do board, URL, membros, cards
   - Contadores de demandas

6. **Gráficos**
   - Canvas para Burnup (escopo + concluído)
   - Canvas para Burndown (demandas restantes)

#### H. Script de Gráficos (linhas 1050-1100)
1. Função `compressSeries()`: reduz série para máximo 20 pontos
2. Inicializa dois gráficos Chart.js:
   - **Burnup:** tipo bar, datasets para escopo e concluído
   - **Burndown:** tipo bar, dataset para restantes
3. Configurações de responsividade e ticks

---

## 5. SCHEMA DE CONFIGURAÇÃO

### `app_config.json`
```json
{
  "latest_uploaded_file": "uploads/trello_2026-05-12_22-55-07_clohx19c.json",
  "updated_at": "2026-05-12T20:55:07+00:00",
  "board_lists_index": {
    "1": { "id": "...", "name": "Backlog" },
    "2": { "id": "...", "name": "A fazer" }
  },
  "demand_lists": {
    "pending_list_ids": ["69c14661996d5067c47188af"],
    "completed_list_ids": ["69b0aed7b15552ce641c8dc8"],
    "completed_list_id": "69b0aed7b15552ce641c8dc8"
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
  "atualizado_em": "2026-05-12 20:55:07",
  "indice": "id_trello",
  "arquivos": {
    "clohx19c": [
      {
        "id_trello": "clohx19c",
        "short_url": "https://trello.com/b/clohx19c/...",
        "nome_arquivo": "trello_2026-05-12_22-55-07_clohx19c.json",
        "data": "12/05/2026",
        "hora": "22:55:07",
        "data_atualizacao_json": "12/05/2026 20:55:00"
      }
    ]
  }
}
```

---

## 6. REGRAS DE NEGÓCIO

### Contagem de Demandas
- **Card sem checklist:** conta como 1 demanda
- **Card com checklist:** conta como N demandas (um por checkItem)
- **Filtro de listas:** apenas cards em listas configuradas (pending + completed)

### Status de Conclusão
- **Card sem checklist:** concluído se `card.idList` estiver em `completed_list_ids`
- **Card com checklist:** não importa posição, conta apenas os itens concluídos
- **CheckItem:** concluído quando `state === 'complete'`

### Datas Importantes
- **Criação:** `cardCreatedAt` (primeira ação de tipo `createCard`) ou `cardFirstActionAt`
- **Conclusão:** data da ação que moveu para lista de concluídos ou completou checkItem
- **Sincronização:** `dateLastActivity` do board/card é fallback quando ação não está disponível

### Filtro de Datas
- Formato: `YYYY-MM-DD`
- Inclusive em ambas as extremidades: `data_inicio ≤ data ≤ data_fim`
- Impacta apenas a exibição dos gráficos, não os contadores

### Listas "Quick Config"
- Campos `demand_fields.todo_list_id` e `demand_fields.done_list_id`
- Permitem focar em apenas 1 lista pending + 1 lista completed
- GET params `?todo_list_id=X&done_list_id=Y` configuram rapidamente

---

## 7. TRATAMENTO DE ERROS

### Validação no Upload
- ❌ Sem arquivo enviado
- ❌ Arquivo não é `.json`
- ❌ JSON inválido
- ❌ Não consegue criar pasta uploads
- ❌ Não consegue mover arquivo para uploads

### Validação na Atualização do Trello
- ❌ shortUrl inválida (não começa com `https://trello.com/b/`)
- ❌ Arquivo para atualizar não existe
- ❌ Não consegue fazer download do Trello (timeout 30s)
- ❌ Resposta do Trello não é JSON válido

### Validação de Configuração de Listas
- ❌ Lista pending igual a lista completed
- ❌ Lista não existe no board

### Validação de Filtro de Datas
- ❌ Data inicial inválida (não é `YYYY-MM-DD`)
- ❌ Data final inválida
- ❌ Data inicial > data final

---

## 8. VARIÁVEIS GLOBAIS IMPORTANTES

### Do GET/POST
```php
$filterStartInput         // GET 'start_date'
$filterEndInput           // GET 'end_date'
$sourceFileInput          // GET 'source_file'
$quickTodoListInput       // GET 'todo_list_id'
$quickDoneListInput       // GET 'done_list_id'
```

### De Estado
```php
$selectedFileRel          // Arquivo selecionado (relative path)
$config                   // Array da configuração carregada
$board                    // Array do JSON do Trello decodificado
```

### De Listagens
```php
$availableFiles           // Arquivos disponíveis (dados.json + uploads)
$availableListIds         // Listas do board atual (mapa)
$boardListsIndex          // Índice de listas por posição
```

### De Dados Processados
```php
$cards, $checklists, $actions, $lists   // Coleções do board
$cardCreatedAt, $cardDoneAt, $checkItemCompletedAt   // Timestamps
$scopeByDate, $doneByDate   // Séries temporais
$scopeSeries, $doneSeries, $remainingSeries   // Séries acumuladas
```

---

## 9. FLUXO DE REQUISIÇÕES

### GET (Exibir página)
1. ✅ Carregar configuração
2. ✅ Listar arquivos disponíveis
3. ✅ Resolver arquivo selecionado (param ou config)
4. ✅ Carregar JSON
5. ✅ Calcular demandas e gráficos
6. ✅ Renderizar HTML + gráficos JS

### POST - Upload
1. ✅ Validar arquivo
2. ✅ Mover para uploads com timestamp
3. ✅ Atualizar app_config.json
4. ✅ Atualizar uploads_index.json
5. ✅ Redirecionar para GET com `?upload=ok`

### POST - Refresh do Trello
1. ✅ Validar shortUrl
2. ✅ Download via `file_get_contents()`
3. ✅ Validar JSON
4. ✅ Salvar sobre arquivo existente
5. ✅ Atualizar app_config.json e uploads_index.json
6. ✅ Redirecionar com `?refreshed=ok`

### POST - Salvar Config de Listas
1. ✅ Validar listas (pending vs completed)
2. ✅ Salvar em app_config.json
3. ✅ Redirecionar com `?config_lists=ok`

---

## 10. PONTOS DE EXTENSÃO

### Fáceis (baixo risco)
- Adicionar novos tipos de gráficos (tipo: linha, pizza, etc.)
- Exportar dados em CSV/Excel
- Temas CSS customizáveis
- Idiomas adicionais
- Estatísticas mais detalhadas (velocidade, taxa de conclusão, etc.)

### Médios (risco moderado)
- Integração com API v3 do Trello (autenticação)
- Sincronização automática periódica
- Armazenamento em BD relacional
- Multi-board em uma página
- Usuários e permissões

### Complexos (alto risco)
- Machine Learning para previsões
- Integração com Jira, Azure DevOps, etc.
- Real-time updates via WebSocket
- Dashboard admin
- Auditoria de mudanças

---

## 11. PADRÕES DE CÓDIGO

### Declaração de Tipos
```php
declare(strict_types=1);
```

### Nomes de Variáveis
- Camel case para variáveis: `$filterStartInput`, `$boardName`
- Snake case para arrays: `$board_lists_index`, `$card_created_at`
- Prefixo de tipo para coleções: `$xxxIds`, `$xxxByXxx`

### Validação
```php
// Sempre verificar tipo com is_array(), is_string(), etc.
if (isset($array['key']) && is_string($array['key'])) {
    $value = trim((string)$array['key']);
}
```

### Encoding
```php
// Sempre usar htmlspecialchars para saída
echo htmlspecialchars($var, ENT_QUOTES, 'UTF-8');

// JSON sem escape de slashes/unicode
json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
```

### Datas e Timezones
```php
// Sempre usar GMT para arquivo config
gmdate('c')  // ISO-8601

// Usar strtotime para parsear ISO-8601
strtotime('2026-05-12T20:55:07+00:00')
```

---

## 12. COMO USAR ESTE DOCUMENTO

**Este arquivo DEVE ser consultado e referenciado em TODA e QUALQUER implementação, refatoração, bugfix ou adição de features.**

Ao receber um task:
1. Leia a solicitação
2. Consulte as seções relevantes deste documento
3. Valide regras de negócio (Seção 6)
4. Implemente respeitando padrões (Seção 11)
5. Valide tratamento de erros (Seção 7)
6. Atualize este documento se houver mudança de arquitetura

---

## 13. CHECKLIST DE IMPLEMENTAÇÃO

Para TODA mudança:
- [ ] Validação de entrada/output
- [ ] Tratamento de erros
- [ ] Tipo declarations corretos
- [ ] HTML encoding com `htmlspecialchars()`
- [ ] JSON encoding com flags corretas
- [ ] Paths com `DIRECTORY_SEPARATOR`
- [ ] Arquivo padrão como fallback
- [ ] Atualizar este documento se necessário

---

**Fim do contexto completo.**
