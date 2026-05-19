-- Schema SQLite — metricas_trello
-- Arquivo de banco: database/metricas_trello.sqlite

PRAGMA foreign_keys = ON;
PRAGMA journal_mode = WAL;

-- ─── boards ──────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS boards (
    id                  TEXT    NOT NULL,
    name                TEXT    NOT NULL DEFAULT '',
    url                 TEXT    NOT NULL DEFAULT '',
    short_url           TEXT    NOT NULL DEFAULT '',
    date_last_activity  TEXT    NULL,
    members_count       INTEGER NOT NULL DEFAULT 0,
    imported_file       TEXT    NOT NULL DEFAULT '',
    imported_at         TEXT    NOT NULL DEFAULT (DATETIME('now')),
    PRIMARY KEY (id)
);

-- ─── lists ───────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS lists (
    id        TEXT    NOT NULL,
    board_id  TEXT    NOT NULL,
    name      TEXT    NOT NULL DEFAULT '',
    position  INTEGER NOT NULL DEFAULT 0,
    PRIMARY KEY (id),
    FOREIGN KEY (board_id) REFERENCES boards(id) ON DELETE CASCADE
);
CREATE INDEX IF NOT EXISTS idx_lists_board ON lists(board_id);

-- ─── cards ───────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS cards (
    id                  TEXT NOT NULL,
    board_id            TEXT NOT NULL,
    list_id             TEXT NOT NULL,
    name                TEXT NOT NULL DEFAULT '',
    short_link          TEXT NOT NULL DEFAULT '',
    date_last_activity  TEXT NULL,
    created_at          TEXT NULL,
    done_at             TEXT NULL,
    PRIMARY KEY (id),
    FOREIGN KEY (board_id) REFERENCES boards(id) ON DELETE CASCADE,
    FOREIGN KEY (list_id)  REFERENCES lists(id)  ON DELETE CASCADE
);
CREATE INDEX IF NOT EXISTS idx_cards_board ON cards(board_id);
CREATE INDEX IF NOT EXISTS idx_cards_list  ON cards(list_id);

-- ─── checklists ──────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS checklists (
    id       TEXT NOT NULL,
    card_id  TEXT NOT NULL,
    name     TEXT NOT NULL DEFAULT '',
    PRIMARY KEY (id),
    FOREIGN KEY (card_id) REFERENCES cards(id) ON DELETE CASCADE
);
CREATE INDEX IF NOT EXISTS idx_checklists_card ON checklists(card_id);

-- ─── check_items ─────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS check_items (
    id            TEXT NOT NULL,
    checklist_id  TEXT NOT NULL,
    card_id       TEXT NOT NULL,
    name          TEXT NOT NULL DEFAULT '',
    state         TEXT NOT NULL DEFAULT 'incomplete'
                       CHECK (state IN ('incomplete','complete')),
    completed_at  TEXT NULL,
    PRIMARY KEY (id),
    FOREIGN KEY (checklist_id) REFERENCES checklists(id) ON DELETE CASCADE,
    FOREIGN KEY (card_id)      REFERENCES cards(id)      ON DELETE CASCADE
);
CREATE INDEX IF NOT EXISTS idx_ci_checklist ON check_items(checklist_id);
CREATE INDEX IF NOT EXISTS idx_ci_card      ON check_items(card_id);

-- ─── actions ─────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS actions (
    id        TEXT NOT NULL,
    board_id  TEXT NOT NULL,
    card_id   TEXT NULL,
    type      TEXT NOT NULL DEFAULT '',
    date      TEXT NULL,
    data      TEXT NULL,
    PRIMARY KEY (id),
    FOREIGN KEY (board_id) REFERENCES boards(id) ON DELETE CASCADE
);
CREATE INDEX IF NOT EXISTS idx_actions_board ON actions(board_id);
CREATE INDEX IF NOT EXISTS idx_actions_card  ON actions(card_id);
CREATE INDEX IF NOT EXISTS idx_actions_type  ON actions(type);
CREATE INDEX IF NOT EXISTS idx_actions_date  ON actions(date);

-- ─── board_list_config ───────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS board_list_config (
    board_id              TEXT NOT NULL,
    pending_list_ids      TEXT NOT NULL DEFAULT '[]',
    completed_list_ids    TEXT NOT NULL DEFAULT '[]',
    in_progress_list_ids  TEXT NOT NULL DEFAULT '[]',
    updated_at            TEXT NOT NULL DEFAULT (DATETIME('now')),
    PRIMARY KEY (board_id),
    FOREIGN KEY (board_id) REFERENCES boards(id) ON DELETE CASCADE
);
