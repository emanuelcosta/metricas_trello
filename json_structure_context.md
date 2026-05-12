# Contexto da Estrutura do JSON (Trello)

## Arquivo de referência
- `dados.json`

## Estrutura de topo
- **Tipo:** objeto
- **Chaves principais:**
  - `id`, `name`, `url`, `dateLastActivity`
  - `actions`, `cards`, `checklists`, `lists`, `members`
  - `labels`, `customFields`, `prefs`, `limits`

## Coleções principais

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
