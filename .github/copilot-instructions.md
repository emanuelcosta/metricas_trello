# Claude - Instruções Personalizadas para Métricas Trello

## Escopo
Estas instruções aplicam-se a TODA e QUALQUER ação de implementação, refatoração, debugging ou adição de features no projeto **Métricas Trello**.

## Referência Obrigatória
**SEMPRE consulte e respeite o arquivo `PROJECT_FULL_CONTEXT.md` antes de qualquer implementação.**

Este arquivo contém:
- Visão geral da arquitetura
- Estrutura completa do código
- Regras de negócio
- Padrões de código
- Tratamento de erros
- Checklist de validação

## Fluxo de Trabalho

### 1. Ao Receber uma Solicitação
- [ ] Leia a solicitação com atenção
- [ ] Identifique a seção relevante em `PROJECT_FULL_CONTEXT.md`
- [ ] Consulte a arquitetura (Seção 3-4)
- [ ] Valide regras de negócio (Seção 6)
- [ ] Consulte tratamento de erros (Seção 7)

### 2. Antes de Implementar
- [ ] Valide se é um ponto de extensão apropriado (Seção 10)
- [ ] Planeje as mudanças no contexto da arquitetura
- [ ] Identifique todos os arquivos que serão afetados
- [ ] Revise padrões de código (Seção 11)

### 3. Durante a Implementação
- [ ] Respeite tipo declarations: `declare(strict_types=1);`
- [ ] Use `htmlspecialchars()` para saída HTML
- [ ] Use flags corretas em `json_encode()`
- [ ] Use `DIRECTORY_SEPARATOR` para paths
- [ ] Sempre validar tipo com `is_array()`, `is_string()`, etc.
- [ ] Sempre trim() valores de entrada
- [ ] Sempre usar fallback para arquivo padrão

### 4. Após Implementar
- [ ] Valide contra checklist da Seção 13
- [ ] Teste com dados reais (arquivos em uploads/)
- [ ] Atualize `PROJECT_FULL_CONTEXT.md` se arquitetura mudar
- [ ] Atualize documentação relevante
- [ ] Confirme que não quebrou funcionalidades existentes

## Padrões Obrigatórios

### Estrutura de Funções
```php
/**
 * Descrição breve
 * 
 * @param string $param1 Descrição
 * @return bool|string Descrição
 */
function functionName(string $param1): bool|string
{
    if (!is_file($param1)) {
        return false;
    }
    // implementação...
}
```

### Validação de Entrada
```php
// ✅ Correto
$input = isset($_GET['key']) ? trim((string)$_GET['key']) : '';
if ($input !== '' && preg_match('/^pattern$/', $input)) {
    // usar $input
}

// ❌ Incorreto
$input = $_GET['key'];  // pode não existir
echo $input;  // sem escape
```

### Validação de Array
```php
// ✅ Correto
if (isset($array['key']) && is_string($array['key'])) {
    $value = trim($array['key']);
}

// ❌ Incorreto
if (!empty($array['key'])) {  // não valida tipo
    $value = $array['key'];
}
```

### JSON
```php
// ✅ Correto
json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
json_decode($raw, true);  // sempre true para array

// ❌ Incorreto
json_encode($data);  // sem flags
json_decode($raw);  // sem true (retorna object)
```

### Paths
```php
// ✅ Correto
$path = $baseDir . DIRECTORY_SEPARATOR . 'subdir' . DIRECTORY_SEPARATOR . 'file.json';
$relative = str_replace('\\', '/', $path);  // normalizar separador

// ❌ Incorreto
$path = $baseDir . '/' . $file;  // hardcoded /
```

### Datas
```php
// ✅ Correto
$now = gmdate('c');  // ISO-8601 com GMT
$timestamp = strtotime('2026-05-12T20:55:07+00:00');

// ❌ Incorreto
date('Y-m-d H:i:s');  // sem timezone
time();  // usa server timezone
```

### Saída HTML
```php
// ✅ Correto
echo htmlspecialchars($value, ENT_QUOTES, 'UTF-8');

// ❌ Incorreto
echo $value;  // XSS vulnerability
echo htmlspecialchars($value);  // ENT_COMPAT é insuficiente
```

## Regras de Negócio Críticas

### Contagem de Demandas
- Card **sem checklist** = 1 demanda
- Card **com checklist** = quantidade de checkItems (não cards)
- **Filtro de listas:** apenas cards em `pending_list_ids` + `completed_list_ids`
- Não contar cards em listas não configuradas

### Status de Conclusão
- Card sem checklist: concluído se `idList` em `completed_list_ids`
- CheckItem: concluído se `state === 'complete'`
- Datas: usar ação `updateCheckItemStateOnCard` ou `updateCard`

### Filtro de Datas
- Formato: `YYYY-MM-DD` (ISO-8601 sem hora)
- Inclusive ambos os lados
- Afeta apenas gráficos, não contadores globais
- Interpolar série existente (não recalcular)

## Tratamento de Erros Esperados

### Upload
- ❌ Sem arquivo / arquivo não .json
- ❌ JSON inválido
- ❌ Não consegue criar pasta uploads
- ❌ Não consegue mover arquivo

**Ação:** Mostrar mensagem de erro clara, não fazer redirect

### Refresh do Trello
- ❌ shortUrl inválida
- ❌ Timeout ao baixar (30s)
- ❌ Resposta não é JSON válido
- ❌ Arquivo para atualizar não existe

**Ação:** Mostrar erro, permitir retry

### Configuração de Listas
- ❌ Lista pending === lista completed
- ❌ Lista não existe no board
- ❌ Arquivo config não consegue salvar

**Ação:** Mostrar erro com mensagem clara

### Filtro de Datas
- ❌ Data inválida (não YYYY-MM-DD)
- ❌ Data inicial > data final

**Ação:** Mostrar erro, limpar filtro

## Logging e Debug

Para debug:
1. Use `var_export()` ou `json_encode()` antes de `exit;`
2. Gere arquivo `_debug_output.html` se necessário
3. Nunca deixe debug em produção
4. Sempre use `@` para suprimir warnings esperados

Exemplo:
```php
$fetched = @file_get_contents($url, false, $context);
if ($fetched === false) {
    // erro esperado
}
```

## Commits e Versionamento

Siga `commit_message_config.json`:
```
Tipos: feat, fix, refactor, docs, chore, test
Formato: {tipo}: {ação_no_futuro_do_preterito}
Exemplo: feat: adicionaria cache de configuração
```

## Quando Não Seguir Estas Instruções

❌ Nunca ignore validação de tipo  
❌ Nunca ouça hardcoding de paths  
❌ Nunca ignore timezone (use sempre GMT para config)  
❌ Nunca implemente features não previstas em Seção 10  
❌ Nunca mude padrões sem atualizar `PROJECT_FULL_CONTEXT.md`  

## Contato/Dúvidas

Se algo não estiver claro em `PROJECT_FULL_CONTEXT.md`:
1. Consulte seção "Como usar" (Seção 12)
2. Verifique exemplos em Seção 11
3. Se ainda houver dúvida, **parei e perguntou ao usuário** antes de implementar

---

**Última atualização:** 2026-05-12  
**Versão:** 1.0  
**Válido para:** Todos os colaboradores do projeto Métricas Trello
