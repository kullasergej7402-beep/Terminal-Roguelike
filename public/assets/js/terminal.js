(function () {
    'use strict';

    const API = {
        start(name) {
            return fetch('/api/run/start', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ name }),
            }).then((r) => r.json());
        },
        state(token) {
            return fetch(`/api/run/${token}`).then((r) => r.json());
        },
        async action(token, command) {
            const r = await fetch(`/api/run/${token}/action`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ command }),
            });
            const data = await r.json();
            if (!r.ok) {
                throw new Error(data.error || 'Неизвестная ошибка сервера.');
            }
            return data;
        },
        leaderboard() {
            return fetch('/api/leaderboard').then((r) => r.json());
        },
    };

    const startScreen = document.getElementById('start-screen');
    const gameScreen = document.getElementById('game-screen');
    const logEl = document.getElementById('log');
    const optionsEl = document.getElementById('options');
    const statbarEl = document.getElementById('statbar');
    const errorLine = document.getElementById('error-line');
    const commandInput = document.getElementById('command-input');
    const crt = document.getElementById('crt');
    const restartButton = document.getElementById('restart-button');

    let token = localStorage.getItem('tr_token');
    let lastHp = null;
    let knownLogLength = 0;
    let busy = false;

    function typeLine(text, className) {
        return new Promise((resolve) => {
            const line = document.createElement('div');
            line.className = 'line' + (className ? ' ' + className : '');
            logEl.appendChild(line);

            let i = 0;
            const speed = 12;

            (function step() {
                line.textContent = text.slice(0, i);
                i += 1;
                logEl.scrollTop = logEl.scrollHeight;

                if (i <= text.length) {
                    setTimeout(step, speed);
                } else {
                    resolve();
                }
            }());
        });
    }

    async function renderLogAnimated(fullLog) {
        const newLines = fullLog.slice(knownLogLength);
        knownLogLength = fullLog.length;

        for (const line of newLines) {
            await typeLine(line);
        }
    }

    function renderLogInstant(fullLog) {
        knownLogLength = fullLog.length;
        logEl.innerHTML = '';
        fullLog.forEach((line) => {
            const p = document.createElement('div');
            p.className = 'line';
            p.textContent = line;
            logEl.appendChild(p);
        });
        logEl.scrollTop = logEl.scrollHeight;
    }

    function renderStatbar(state) {
        const p = state.player;
        const r = state.run;
        statbarEl.innerHTML = `
            <span>Игрок: <b>${escapeHtml(r.playerName)}</b></span>
            <span>Этаж: <b>${r.floor}</b></span>
            <span>Уровень: <b>${p.level}</b></span>
            <span>HP: <b>${p.hp}/${p.maxHp}</b></span>
            <span>MP: <b>${p.mp}/${p.maxMp}</b></span>
            <span>EXP: <b>${p.exp}/${p.expToNext}</b></span>
            <span>Атака: <b>${p.attack}</b></span>
            <span>Защита: <b>${p.defense}</b></span>
        `;
    }

    function renderOptions(state) {
        optionsEl.innerHTML = '';
        if (!state.options || state.options.length === 0) {
            return;
        }

        state.options.forEach((opt, idx) => {
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.textContent = `${idx + 1}. ${opt.label}`;
            btn.addEventListener('click', () => sendCommand(String(idx + 1)));
            optionsEl.appendChild(btn);
        });
    }

    function maybeFlicker(state) {
        const hp = state.player.hp;
        if (lastHp !== null && hp < lastHp) {
            crt.classList.remove('flicker');
            void crt.offsetWidth;
            crt.classList.add('flicker');
        }
        lastHp = hp;
    }

    function showGameOver(state) {
        commandInput.disabled = true;
        optionsEl.innerHTML = '';
        restartButton.classList.remove('hidden');

        const banner = document.createElement('div');
        banner.className = 'game-over-banner glow';
        banner.textContent = state.run.status === 'won' ? 'ПОБЕДА' : 'ЗАБЕГ ОКОНЧЕН';
        logEl.appendChild(banner);
        logEl.scrollTop = logEl.scrollHeight;
    }

    async function refreshFromState(state, options) {
        const animate = !options || options.animate !== false;

        renderStatbar(state);

        if (animate) {
            await renderLogAnimated(state.log);
        } else {
            renderLogInstant(state.log);
        }

        maybeFlicker(state);

        if (state.run.status !== 'active') {
            showGameOver(state);
            return;
        }

        commandInput.disabled = false;
        renderOptions(state);
    }

    async function sendCommand(command) {
        if (!command || !token || busy) {
            return;
        }

        busy = true;
        errorLine.textContent = '';
        commandInput.disabled = true;

        try {
            const state = await API.action(token, command);
            await refreshFromState(state);
        } catch (e) {
            errorLine.textContent = e.message;
            commandInput.disabled = false;
        } finally {
            busy = false;
            commandInput.focus();
        }
    }

    commandInput.addEventListener('keydown', (e) => {
        if (e.key === 'Enter') {
            const value = commandInput.value.trim();
            if (!value) {
                return;
            }
            commandInput.value = '';
            sendCommand(value);
        }
    });

    document.getElementById('start-button').addEventListener('click', async () => {
        const nameInput = document.getElementById('player-name');
        const name = nameInput.value.trim() || 'anon';
        const errBox = document.getElementById('start-error');
        errBox.classList.add('hidden');

        try {
            const state = await API.start(name);
            token = state.run.token;
            localStorage.setItem('tr_token', token);
            enterGameScreen(state);
        } catch (e) {
            errBox.textContent = 'Не удалось начать забег. Проверь соединение с сервером.';
            errBox.classList.remove('hidden');
        }
    });

    restartButton.addEventListener('click', () => {
        localStorage.removeItem('tr_token');
        location.reload();
    });

    function enterGameScreen(state) {
        startScreen.classList.add('hidden');
        gameScreen.classList.remove('hidden');
        knownLogLength = 0;
        lastHp = state.player.hp;
        refreshFromState(state, { animate: false });
        commandInput.focus();
    }

    async function loadLeaderboard() {
        try {
            const data = await API.leaderboard();
            const tbody = document.querySelector('#leaderboard-table tbody');
            tbody.innerHTML = '';
            (data.leaderboard || []).forEach((run, idx) => {
                const tr = document.createElement('tr');
                const statusLabel = run.status === 'won' ? 'победа' : 'гибель';
                tr.innerHTML =
                    `<td>${idx + 1}</td><td>${escapeHtml(run.playerName)}</td>` +
                    `<td>${run.floor}</td><td>${run.score}</td><td>${statusLabel}</td>`;
                tbody.appendChild(tr);
            });
        } catch (e) {
            // Leaderboard is non-critical for the game itself; fail silently.
        }
    }

    function escapeHtml(str) {
        const div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }

    (async function init() {
        if (token) {
            try {
                const state = await API.state(token);
                if (state && state.run) {
                    enterGameScreen(state);
                    return;
                }
            } catch (e) {
                // fall through to start screen
            }
            localStorage.removeItem('tr_token');
            token = null;
        }

        loadLeaderboard();
    }());
}());
