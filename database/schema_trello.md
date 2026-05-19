# Estrutura de Banco de Dados — Trello Export

Fonte: `uploads/trello_2026-05-18_23-17-50_clohx19c_-_jumbsofware__3_.json`  
Banco: MySQL 5.7+ / MariaDB 10.3+  
Arquivo SQL: `database/schema_trello.sql`

---

## Convenção de campo `indice`

Cada tabela possui um campo `indice VARCHAR(64)` que armazena o **ID original gerado pelo Trello** (string hexadecimal, ex: `69b0aeceee51a805bcb699a5`). As chaves estrangeiras entre tabelas referenciam esse campo, e não o `id` auto-increment interno, para manter rastreabilidade com o JSON exportado.

```
boards.indice    → ID do board   (campo "id" no JSON raiz)
lists.indice     → ID da lista   (campo lists[].id)
cards.indice     → ID do card    (campo cards[].id)
members.indice   → ID do membro  (campo members[].id)
...
```

---

## Diagrama de Entidades

```
boards
  │
  ├── lists            (id_board → boards.indice)
  │     └── cards      (id_list  → lists.indice)
  │           ├── card_labels    (id_card  → cards.indice)
  │           ├── card_members   (id_card  → cards.indice)
  │           ├── attachments    (id_card  → cards.indice)
  │           └── checklists     (id_card  → cards.indice)
  │                 └── check_items  (id_checklist → checklists.indice)
  │
  ├── labels           (id_board → boards.indice)
  ├── members          (sem FK de board; membros são globais)
  ├── memberships      (id_board + id_member)
  └── actions          (id_board + id_card + id_checklist + id_check_item, todos opcionais)
```

---

## Tabelas

### `boards`
Armazena o quadro Trello. Um JSON exportado contém exatamente **1 board**.

| Campo | Tipo | Origem no JSON |
|---|---|---|
| `indice` | VARCHAR(64) | `id` |
| `node_id` | VARCHAR(255) | `nodeId` |
| `name` | VARCHAR(255) | `name` |
| `descricao` | TEXT | `desc` |
| `closed` | TINYINT(1) | `closed` |
| `date_closed` | DATETIME | `dateClosed` |
| `id_organization` | VARCHAR(64) | `idOrganization` |
| `id_enterprise` | VARCHAR(64) | `idEnterprise` |
| `id_member_creator` | VARCHAR(64) | `idMemberCreator` |
| `short_link` | VARCHAR(32) | `shortLink` |
| `short_url` | VARCHAR(255) | `shortUrl` |
| `url` | VARCHAR(500) | `url` |
| `date_last_activity` | DATETIME | `dateLastActivity` |
| `date_last_view` | DATETIME | `dateLastView` |
| `ix_update` | VARCHAR(32) | `ixUpdate` |
| `starred` | TINYINT(1) | `starred` |
| `pinned` | TINYINT(1) | `pinned` |
| `enterprise_owned` | TINYINT(1) | `enterpriseOwned` |
| `tipo` | VARCHAR(32) | `type` |

---

### `lists`
Colunas do quadro (ex: "PRIMEIRA VERSAO DO APP", "Backlog"). No arquivo havia **9 listas**.

| Campo | Tipo | Origem no JSON |
|---|---|---|
| `indice` | VARCHAR(64) | `lists[].id` |
| `node_id` | VARCHAR(255) | `lists[].nodeId` |
| `id_board` | VARCHAR(64) | `lists[].idBoard` |
| `name` | VARCHAR(255) | `lists[].name` |
| `closed` | TINYINT(1) | `lists[].closed` |
| `color` | VARCHAR(32) | `lists[].color` |
| `pos` | BIGINT | `lists[].pos` |
| `subscribed` | TINYINT(1) | `lists[].subscribed` |
| `tipo` | VARCHAR(32) | `lists[].type` |
| `creation_method` | VARCHAR(64) | `lists[].creationMethod` |
| `id_organization` | VARCHAR(64) | `lists[].idOrganization` |

---

### `labels`
Etiquetas coloridas do quadro. No arquivo havia **6 labels**.

| Campo | Tipo | Origem no JSON |
|---|---|---|
| `indice` | VARCHAR(64) | `labels[].id` |
| `node_id` | VARCHAR(255) | `labels[].nodeId` |
| `id_board` | VARCHAR(64) | `labels[].idBoard` |
| `name` | VARCHAR(255) | `labels[].name` |
| `color` | VARCHAR(32) | `labels[].color` (red, green, blue...) |
| `uses` | INT | `labels[].uses` |

---

### `members`
Usuários com acesso ao quadro. No arquivo havia **4 membros**.

| Campo | Tipo | Origem no JSON |
|---|---|---|
| `indice` | VARCHAR(64) | `members[].id` |
| `aa_id` | VARCHAR(128) | `members[].aaId` (Atlassian Account) |
| `activity_blocked` | TINYINT(1) | `members[].activityBlocked` |
| `avatar_hash` | VARCHAR(64) | `members[].avatarHash` |
| `avatar_url` | VARCHAR(500) | `members[].avatarUrl` |
| `bio` | TEXT | `members[].bio` |
| `confirmed` | TINYINT(1) | `members[].confirmed` |
| `full_name` | VARCHAR(255) | `members[].fullName` |
| `initials` | VARCHAR(8) | `members[].initials` |
| `member_type` | ENUM | `members[].memberType` |
| `username` | VARCHAR(100) | `members[].username` |
| `url` | VARCHAR(500) | `members[].url` |
| `date_last_impression` | DATETIME | `members[].dateLastImpression` |
| `status` | VARCHAR(32) | `members[].status` |

---

### `memberships`
Relaciona membros ao quadro com seu papel (admin/normal).

| Campo | Tipo | Origem no JSON |
|---|---|---|
| `indice` | VARCHAR(64) | `memberships[].id` |
| `id_board` | VARCHAR(64) | *(board atual)* |
| `id_member` | VARCHAR(64) | `memberships[].idMember` |
| `member_type` | ENUM | `memberships[].memberType` |
| `unconfirmed` | TINYINT(1) | `memberships[].unconfirmed` |
| `deactivated` | TINYINT(1) | `memberships[].deactivated` |

---

### `cards`
Cards do quadro. No arquivo havia **112 cards**.

| Campo | Tipo | Origem no JSON |
|---|---|---|
| `indice` | VARCHAR(64) | `cards[].id` |
| `node_id` | VARCHAR(255) | `cards[].nodeId` |
| `id_board` | VARCHAR(64) | `cards[].idBoard` |
| `id_list` | VARCHAR(64) | `cards[].idList` |
| `id_member_creator` | VARCHAR(64) | `cards[].idMemberCreator` |
| `id_organization` | VARCHAR(64) | `cards[].idOrganization` |
| `id_short` | INT | `cards[].idShort` (número sequencial) |
| `name` | TEXT | `cards[].name` |
| `descricao` | TEXT | `cards[].desc` |
| `closed` | TINYINT(1) | `cards[].closed` |
| `date_closed` | DATETIME | `cards[].dateClosed` |
| `date_last_activity` | DATETIME | `cards[].dateLastActivity` |
| `date_completed` | DATETIME | `cards[].dateCompleted` |
| `due` | DATETIME | `cards[].due` |
| `due_complete` | TINYINT(1) | `cards[].dueComplete` |
| `due_reminder` | INT | `cards[].dueReminder` (minutos) |
| `start` | DATETIME | `cards[].start` |
| `pos` | BIGINT | `cards[].pos` |
| `short_link` | VARCHAR(32) | `cards[].shortLink` |
| `short_url` | VARCHAR(255) | `cards[].shortUrl` |
| `url` | VARCHAR(1000) | `cards[].url` |
| `is_template` | TINYINT(1) | `cards[].isTemplate` |
| `pinned` | TINYINT(1) | `cards[].pinned` |
| `subscribed` | TINYINT(1) | `cards[].subscribed` |
| `card_role` | VARCHAR(32) | `cards[].cardRole` |
| `location_name` | VARCHAR(255) | `cards[].locationName` |
| `cover_color` | VARCHAR(32) | `cards[].cover.color` |
| `cover_size` | VARCHAR(32) | `cards[].cover.size` |
| `mirror_source_id` | VARCHAR(64) | `cards[].mirrorSourceId` |

---

### `card_members` *(junção N:N)*
Associa cards aos membros atribuídos.

| Campo | Tipo | Origem no JSON |
|---|---|---|
| `indice` | VARCHAR(128) | Gerado: `{idCard}_{idMember}` |
| `id_card` | VARCHAR(64) | `cards[].idMembers[]` |
| `id_member` | VARCHAR(64) | `cards[].idMembers[]` |

---

### `card_labels` *(junção N:N)*
Associa cards às etiquetas aplicadas.

| Campo | Tipo | Origem no JSON |
|---|---|---|
| `indice` | VARCHAR(128) | Gerado: `{idCard}_{idLabel}` |
| `id_card` | VARCHAR(64) | `cards[].idLabels[]` |
| `id_label` | VARCHAR(64) | `cards[].idLabels[]` |

---

### `checklists`
Listas de verificação vinculadas a cards. No arquivo havia **31 checklists**.

| Campo | Tipo | Origem no JSON |
|---|---|---|
| `indice` | VARCHAR(64) | `checklists[].id` |
| `id_board` | VARCHAR(64) | `checklists[].idBoard` |
| `id_card` | VARCHAR(64) | `checklists[].idCard` |
| `name` | VARCHAR(255) | `checklists[].name` |
| `pos` | BIGINT | `checklists[].pos` |
| `creation_method` | VARCHAR(64) | `checklists[].creationMethod` |

---

### `check_items`
Itens individuais de cada checklist.

| Campo | Tipo | Origem no JSON |
|---|---|---|
| `indice` | VARCHAR(64) | `checklists[].checkItems[].id` |
| `id_checklist` | VARCHAR(64) | `checklists[].checkItems[].idChecklist` |
| `id_member` | VARCHAR(64) | `checklists[].checkItems[].idMember` |
| `name` | TEXT | `checklists[].checkItems[].name` |
| `state` | ENUM | `checklists[].checkItems[].state` (`incomplete`/`complete`) |
| `pos` | BIGINT | `checklists[].checkItems[].pos` |
| `due` | DATETIME | `checklists[].checkItems[].due` |
| `due_reminder` | INT | `checklists[].checkItems[].dueReminder` |

---

### `attachments`
Arquivos anexados a cards.

| Campo | Tipo | Origem no JSON |
|---|---|---|
| `indice` | VARCHAR(64) | `cards[].attachments[].id` |
| `id_card` | VARCHAR(64) | *(card pai)* |
| `id_member` | VARCHAR(64) | `cards[].attachments[].idMember` |
| `name` | VARCHAR(255) | `cards[].attachments[].name` |
| `file_name` | VARCHAR(255) | `cards[].attachments[].fileName` |
| `url` | VARCHAR(1000) | `cards[].attachments[].url` |
| `mime_type` | VARCHAR(100) | `cards[].attachments[].mimeType` |
| `bytes` | BIGINT | `cards[].attachments[].bytes` |
| `date` | DATETIME | `cards[].attachments[].date` |
| `is_upload` | TINYINT(1) | `cards[].attachments[].isUpload` |
| `is_malicious` | TINYINT(1) | `cards[].attachments[].isMalicious` |
| `pos` | BIGINT | `cards[].attachments[].pos` |
| `edge_color` | VARCHAR(16) | `cards[].attachments[].edgeColor` |

---

### `actions`
Histórico de atividades do quadro. No arquivo havia **1.000 ações**.

| Campo | Tipo | Origem no JSON |
|---|---|---|
| `indice` | VARCHAR(64) | `actions[].id` |
| `id_member_creator` | VARCHAR(64) | `actions[].idMemberCreator` |
| `tipo` | VARCHAR(64) | `actions[].type` |
| `data` | DATETIME | `actions[].date` |
| `id_board` | VARCHAR(64) | `actions[].data.board.id` |
| `id_list` | VARCHAR(64) | `actions[].data.list.id` |
| `id_card` | VARCHAR(64) | `actions[].data.card.id` |
| `id_checklist` | VARCHAR(64) | `actions[].data.checklist.id` |
| `id_check_item` | VARCHAR(64) | `actions[].data.checkItem.id` |
| `texto` | TEXT | `actions[].data.text` (comentários) |
| `check_item_state` | ENUM | `actions[].data.checkItem.state` |
| `data_json` | JSON | `actions[].data` completo |

#### Tipos de ação presentes no arquivo

| Tipo | Descrição |
|---|---|
| `commentCard` | Comentário adicionado a um card |
| `createCard` | Card criado |
| `createInboxCard` | Card criado via inbox |
| `createList` | Lista criada |
| `updateCard` | Card atualizado (movido, editado, etc.) |
| `updateChecklist` | Checklist atualizado |
| `updateCheckItemStateOnCard` | Item de checklist marcado/desmarcado |
| `updateBoard` | Board atualizado |
| `updateList` | Lista atualizada |
| `addChecklistToCard` | Checklist adicionado ao card |
| `removeChecklistFromCard` | Checklist removido do card |
| `addMemberToCard` | Membro atribuído ao card |
| `removeMemberFromCard` | Membro removido do card |
| `addMemberToBoard` | Membro adicionado ao board |
| `addAttachmentToCard` | Anexo adicionado ao card |
| `deleteAttachmentFromCard` | Anexo removido do card |
| `deleteCard` | Card excluído |
| `moveInboxCardToBoard` | Card movido do inbox para o board |

---

## Views

### `vw_cards_resumo`
Visão consolidada de cada card com: nome, lista, board, contagem de labels, membros, checklists, check_items (total e concluídos) e attachments.

```sql
SELECT * FROM vw_cards_resumo WHERE closed = 0 ORDER BY due ASC;
```

### `vw_actions_historico`
Histórico de ações com nome do membro, número e nome do card, nome da lista. Ordenado por data decrescente.

```sql
SELECT * FROM vw_actions_historico WHERE tipo = 'commentCard' LIMIT 20;
```

---

## Ordem de importação

Para respeitar as chaves estrangeiras, inserir nesta sequência:

```
1. boards
2. lists
3. labels
4. members
5. memberships
6. cards
7. card_members
8. card_labels
9. checklists
10. check_items
11. attachments
12. actions
```

---

## Volumes do arquivo de origem

| Entidade | Quantidade |
|---|---|
| boards | 1 |
| lists | 9 |
| labels | 6 |
| members | 4 |
| memberships | 4 |
| cards | 112 |
| checklists | 31 |
| actions | 1.000 |
