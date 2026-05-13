# collapse-fix Bugfix Design

## Overview

O Bootstrap JS não está incluído no projeto. Apenas o CSS (`assets/bootstrap/bootstrap.min.css`) está presente. O componente collapse usa `data-bs-toggle="collapse"` e `data-bs-target`, que dependem do Bootstrap JS para funcionar. Sem o JS, clicar nas linhas expansíveis da tabela de cards em andamento não produz nenhum efeito.

A correção consiste em baixar `bootstrap.bundle.min.js` localmente para `assets/bootstrap/` e incluir a tag `<script>` correspondente no `index.php`, logo antes do `<script src="assets/chartjs/chart.umd.min.js">`.

## Glossary

- **Bug_Condition (C)**: A condição que dispara o bug — a página é carregada sem o Bootstrap JS, tornando `data-bs-toggle="collapse"` inoperante
- **Property (P)**: O comportamento desejado — clicar em uma linha expansível deve expandir/colapsar o painel de checklists via Bootstrap collapse
- **Preservation**: O Bootstrap CSS já carregado e o Chart.js devem continuar funcionando normalmente após a correção
- **bootstrap.bundle.min.js**: Arquivo JS do Bootstrap (inclui Popper.js) necessário para os componentes interativos como collapse, modal e dropdown
- **data-bs-toggle="collapse"**: Atributo HTML que instrui o Bootstrap JS a tratar o elemento como gatilho de collapse

## Bug Details

### Fault Condition

O bug se manifesta quando a página é carregada sem o arquivo `bootstrap.bundle.min.js` referenciado. O `index.php` inclui apenas o CSS do Bootstrap, deixando todos os componentes JS (collapse, modal, dropdown) sem inicialização.

**Formal Specification:**
```
FUNCTION isBugCondition(pageState)
  INPUT: pageState representando o estado de carregamento da página
  OUTPUT: boolean

  RETURN NOT bootstrapJsLoaded(pageState)
         AND pageContainsElement('[data-bs-toggle="collapse"]', pageState)
END FUNCTION
```

### Examples

- Usuário clica em uma linha da tabela com checklists → nada acontece (esperado: painel expande)
- Usuário clica novamente na mesma linha → nada acontece (esperado: painel colapsa)
- Página carregada → DevTools mostra ausência de `bootstrap.bundle.min.js` nos recursos carregados
- Linha sem checklists → não possui botão de expansão, comportamento inalterado

## Expected Behavior

### Preservation Requirements

**Unchanged Behaviors:**
- O Bootstrap CSS (`assets/bootstrap/bootstrap.min.css`) deve continuar aplicando todos os estilos visuais normalmente
- Linhas da tabela sem checklists devem continuar sendo renderizadas sem botão de expansão e sem comportamento de collapse
- O Chart.js (`assets/chartjs/chart.umd.min.js`) deve continuar renderizando os gráficos de burnup e burndown corretamente

**Scope:**
Todos os comportamentos que não dependem do Bootstrap JS devem ser completamente inalterados pela correção. Isso inclui:
- Estilos visuais do Bootstrap CSS
- Renderização dos gráficos Chart.js
- Lógica PHP de processamento de dados
- Upload e seleção de arquivos JSON

## Hypothesized Root Cause

Com base na análise do bug, a causa é direta e única:

1. **Bootstrap JS ausente**: O `index.php` inclui apenas `<link href="assets/bootstrap/bootstrap.min.css">` mas não possui nenhuma tag `<script>` para o Bootstrap JS. Sem o JS, os atributos `data-bs-toggle` e `data-bs-target` são ignorados pelo navegador.

2. **Arquivo local inexistente**: O arquivo `assets/bootstrap/bootstrap.bundle.min.js` não existe no projeto, portanto mesmo que a tag `<script>` fosse adicionada, o arquivo precisaria ser baixado primeiro.

## Correctness Properties

Property 1: Fault Condition - Bootstrap JS Habilita Collapse

_For any_ estado de página onde `isBugCondition` retorna verdadeiro (Bootstrap JS não carregado e elementos `data-bs-toggle="collapse"` presentes), após a correção a página SHALL carregar `assets/bootstrap/bootstrap.bundle.min.js` e os elementos collapse SHALL responder a cliques expandindo e colapsando o conteúdo correspondente.

**Validates: Requirements 2.1, 2.2**

Property 2: Preservation - Comportamentos Existentes Inalterados

_For any_ comportamento que não depende do Bootstrap JS (estilos CSS, gráficos Chart.js, renderização de linhas sem checklists), a correção SHALL produzir exatamente o mesmo resultado que o código original, preservando toda a funcionalidade existente.

**Validates: Requirements 3.1, 3.2, 3.3**

## Fix Implementation

### Changes Required

**Passo 1 — Baixar o arquivo JS:**

Baixar `bootstrap.bundle.min.js` (versão compatível com o CSS já presente) para `assets/bootstrap/bootstrap.bundle.min.js`.

**File**: `assets/bootstrap/bootstrap.bundle.min.js` (novo arquivo)

---

**Passo 2 — Incluir o script no HTML:**

**File**: `index.php`

**Localização**: Linha ~1342, antes de `<script src="assets/chartjs/chart.umd.min.js"></script>`

**Specific Changes**:

1. **Adicionar tag script do Bootstrap JS**: Inserir `<script src="assets/bootstrap/bootstrap.bundle.min.js"></script>` antes do script do Chart.js

   Antes:
   ```html
   <script src="assets/chartjs/chart.umd.min.js"></script>
   ```

   Depois:
   ```html
   <script src="assets/bootstrap/bootstrap.bundle.min.js"></script>
   <script src="assets/chartjs/chart.umd.min.js"></script>
   ```

## Testing Strategy

### Validation Approach

A estratégia segue duas fases: primeiro verificar o comportamento com o código não corrigido (confirmar o bug), depois verificar que a correção funciona e que os comportamentos existentes foram preservados.

### Exploratory Fault Condition Checking

**Goal**: Confirmar que o collapse não funciona ANTES da correção e entender a causa raiz.

**Test Plan**: Abrir a página no navegador sem o Bootstrap JS e tentar clicar nas linhas expansíveis. Verificar no DevTools (aba Network) que `bootstrap.bundle.min.js` não é carregado.

**Test Cases**:
1. **Collapse não funciona**: Clicar em linha com checklists → painel não expande (falha esperada no código não corrigido)
2. **JS ausente no Network**: Verificar no DevTools que nenhum arquivo Bootstrap JS é carregado
3. **Sem erros de JS**: Verificar que não há erros de console relacionados ao Bootstrap (o atributo é simplesmente ignorado)

**Expected Counterexamples**:
- Clicar na linha não produz nenhuma mudança visual no DOM
- Nenhuma requisição para `bootstrap.bundle.min.js` aparece no Network

### Fix Checking

**Goal**: Verificar que após a correção, o collapse funciona para todos os elementos com `data-bs-toggle="collapse"`.

**Pseudocode:**
```
FOR ALL element WHERE isBugCondition(pageState) DO
  result := clickCollapseToggle(element)
  ASSERT collapseContentIsVisible(result) OR collapseContentIsHidden(result)
  ASSERT bootstrapJsLoaded(pageState)
END FOR
```

### Preservation Checking

**Goal**: Verificar que para todos os comportamentos que não dependem do Bootstrap JS, o resultado após a correção é idêntico ao original.

**Pseudocode:**
```
FOR ALL behavior WHERE NOT isBugCondition(pageState) DO
  ASSERT originalBehavior(behavior) = fixedBehavior(behavior)
END FOR
```

**Testing Approach**: Testes manuais e visuais são suficientes aqui dado o escopo pequeno da correção. A mudança é aditiva (apenas adiciona um arquivo e uma tag script), sem remover ou alterar código existente.

**Test Cases**:
1. **CSS Preservation**: Verificar que os estilos Bootstrap continuam aplicados após adicionar o JS
2. **Chart.js Preservation**: Verificar que os gráficos de burnup e burndown continuam renderizando corretamente
3. **Linhas sem checklists**: Verificar que linhas sem checklists continuam sem botão de expansão

### Unit Tests

- Verificar que `assets/bootstrap/bootstrap.bundle.min.js` existe no sistema de arquivos
- Verificar que `index.php` contém a tag `<script src="assets/bootstrap/bootstrap.bundle.min.js">` antes do Chart.js
- Verificar que o Bootstrap CSS ainda está referenciado no `<head>`

### Property-Based Tests

- Para qualquer linha da tabela com `data-bs-toggle="collapse"`, clicar deve alternar a visibilidade do painel alvo
- Para qualquer linha sem checklists, a ausência do botão de expansão deve ser preservada
- O carregamento do Bootstrap JS não deve interferir com a inicialização do Chart.js

### Integration Tests

- Carregar a página completa e verificar que o Bootstrap JS é carregado (Network tab)
- Clicar em uma linha com checklists e verificar que o painel expande
- Clicar novamente e verificar que o painel colapsa
- Verificar que os gráficos continuam funcionando após a adição do Bootstrap JS
