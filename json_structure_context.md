# Contexto da Estrutura do JSON (Trello)

## ⚠️ ANÁLISE DO ARQUIVO CARREGADO

**Arquivo:** `trello_2026-05-15_14-45-51_69f8dce4fed4ad11f670e5d2__1_.json`

**Tipo:** ❌ **CARD INDIVIDUAL** (não é um board completo)

### Conteúdo
- **ID do Card:** `69f8dce4fed4ad11f670e5d2`
- **Nome:** "Upload de arquivos digitais"
- **Board ID:** `65030943a399df3b54a82895`
- **List ID:** `69d4e79f5f2c78f10f89e18c`
- **Checklists:** 5 checklists
- **CheckItems Total:** 29 (11 completos + 18 incompletos)
- **Actions:** ~30+ ações de histórico

### Checklists Encontradas

| # | Nome | Items | Completos |
|---|------|-------|-----------|
| 1 | Checklist | 2 | 2 |
| 2 | Cadastro de servidor | 6 | 4 |
| 3 | ADMIN / GESTÃO DE CONFIGURAÇÕES DE UPLOADS | 9 | 1 |
| 4 | ADMIN/OPERADOR | 11 | 4 |
| 5 | Checklist | 1 | 0 |

---

## Arquivo de referência padrão
- `dados.json`

## Estrutura de topo (formato correto)
- **Tipo:** objeto
- **Chaves principais:**
  - `id`, `name`, `url`, `dateLastActivity`
  - `actions`, `cards`, `checklists`, `lists`, `members`
  - `labels`, `customFields`, `prefs`, `limits`

## Coleções principais (formato board completo)

### `lists`
- Tipo: array de objetos
- Campos principais: `id`, `name`, `closed`, `pos`

### `cards`
- Tipo: array de objetos
- Campos principais: `id`, `name`, `idList`, `idChecklists`, `dateLastActivity`, `closed`, `labels`

### `checklists`
- Tipo: array de objetos
- Campos principais: `id`, `name`, `idCard`, `idBoard`, `checkItems`
- `checkItems` (itens): `id`, `name`, `state`, `idChecklist`

### `actions`
- Tipo: array de objetos
- Campos principais: `id`, `type`, `date`, `data`, `memberCreator`
- Tipos mais usados nos gráficos:
  - `createCard`
  - `updateCard`
  - `updateCheckItemStateOnCard`

## Relacionamentos
- `cards[].idList -> lists[].id`
- `checklists[].idCard -> cards[].id`
- `checklists[].checkItems[].idChecklist -> checklists[].id`
- `actions[].data.card.id -> cards[].id`

## Regras usadas no projeto
- **Contagem de demanda**
  - Cartão sem checklist conta 1 demanda
  - Cartão com checklist conta a quantidade de `checkItems`
- **Conclusão**
  - Card sem checklist: concluído quando `card.idList` está em lista de concluídos
  - CheckItem: concluído quando `state = complete`

---

## 🔧 Tratamento de Arquivo Individual

O código PHP detecta quando é um card individual e:
1. Cria uma estrutura artificial de board
2. Gera uma lista padrão com ID do `idList` do card
3. Conta todos os 29 checkItems como demandas
4. Marca 11 como concluídas (state = complete)
5. Marca 18 como pendentes (state = incomplete)

**Contagem Esperada:**
- Total de demandas: **29**
- Demandas completas: **11** (38%)
- Demandas pendentes: **18** (62%)
