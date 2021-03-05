-- Terminal Roguelike SQLite schema

CREATE TABLE IF NOT EXISTS runs (
    id              INTEGER PRIMARY KEY AUTOINCREMENT,
    token           TEXT NOT NULL UNIQUE,
    player_name     TEXT NOT NULL,
    status          TEXT NOT NULL DEFAULT 'active', -- active | dead | won
    floor           INTEGER NOT NULL DEFAULT 1,
    score           INTEGER NOT NULL DEFAULT 0,
    enemies_killed  INTEGER NOT NULL DEFAULT 0,
    items_found     INTEGER NOT NULL DEFAULT 0,
    started_at      TEXT NOT NULL,
    ended_at        TEXT
);

CREATE TABLE IF NOT EXISTS players (
    id              INTEGER PRIMARY KEY AUTOINCREMENT,
    run_id          INTEGER NOT NULL UNIQUE REFERENCES runs(id) ON DELETE CASCADE,
    level           INTEGER NOT NULL DEFAULT 1,
    hp              INTEGER NOT NULL DEFAULT 30,
    max_hp          INTEGER NOT NULL DEFAULT 30,
    mp              INTEGER NOT NULL DEFAULT 10,
    max_mp          INTEGER NOT NULL DEFAULT 10,
    attack          INTEGER NOT NULL DEFAULT 6,
    defense         INTEGER NOT NULL DEFAULT 2,
    exp             INTEGER NOT NULL DEFAULT 0,
    exp_to_next     INTEGER NOT NULL DEFAULT 20,
    gold            INTEGER NOT NULL DEFAULT 0,
    current_room_id INTEGER
);

CREATE TABLE IF NOT EXISTS rooms (
    id          INTEGER PRIMARY KEY AUTOINCREMENT,
    run_id      INTEGER NOT NULL REFERENCES runs(id) ON DELETE CASCADE,
    floor       INTEGER NOT NULL,
    room_index  INTEGER NOT NULL,
    type        TEXT NOT NULL, -- entrance | combat | treasure | trap | rest | stairs | empty
    x           INTEGER NOT NULL,
    y           INTEGER NOT NULL,
    connections TEXT NOT NULL DEFAULT '{}', -- JSON: {"north": room_id, "south": null, ...}
    visited     INTEGER NOT NULL DEFAULT 0,
    cleared     INTEGER NOT NULL DEFAULT 0
);

CREATE TABLE IF NOT EXISTS enemies (
    id          INTEGER PRIMARY KEY AUTOINCREMENT,
    room_id     INTEGER NOT NULL REFERENCES rooms(id) ON DELETE CASCADE,
    name        TEXT NOT NULL,
    hp          INTEGER NOT NULL,
    max_hp      INTEGER NOT NULL,
    attack      INTEGER NOT NULL,
    defense     INTEGER NOT NULL,
    exp_reward  INTEGER NOT NULL,
    alive       INTEGER NOT NULL DEFAULT 1
);

CREATE TABLE IF NOT EXISTS items (
    id            INTEGER PRIMARY KEY AUTOINCREMENT,
    room_id       INTEGER REFERENCES rooms(id) ON DELETE CASCADE,
    player_id     INTEGER REFERENCES players(id) ON DELETE CASCADE,
    name          TEXT NOT NULL,
    type          TEXT NOT NULL, -- weapon | armor | potion
    effect_value  INTEGER NOT NULL,
    equipped      INTEGER NOT NULL DEFAULT 0,
    taken         INTEGER NOT NULL DEFAULT 0
);

CREATE TABLE IF NOT EXISTS game_log (
    id          INTEGER PRIMARY KEY AUTOINCREMENT,
    run_id      INTEGER NOT NULL REFERENCES runs(id) ON DELETE CASCADE,
    message     TEXT NOT NULL,
    created_at  TEXT NOT NULL
);

CREATE INDEX IF NOT EXISTS idx_rooms_run_id ON rooms(run_id);
CREATE INDEX IF NOT EXISTS idx_enemies_room_id ON enemies(room_id);
CREATE INDEX IF NOT EXISTS idx_items_room_id ON items(room_id);
CREATE INDEX IF NOT EXISTS idx_items_player_id ON items(player_id);
CREATE INDEX IF NOT EXISTS idx_game_log_run_id ON game_log(run_id);
