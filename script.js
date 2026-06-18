const API_BASE = 'obtener_datos.php';
const API_SAVE = 'guardar_resultado.php';
let goalsChart = null;
let allData = null;

const FLAGS = {
    'Argentina': '🇦🇷', 'Chile': '🇨🇱', 'Mexico': '🇲🇽', 'Nueva Zelanda': '🇳🇿',
    'Brasil': '🇧🇷', 'Espana': '🇪🇸', 'Japon': '🇯🇵', 'Ghana': '🇬🇭',
    'Francia': '🇫🇷', 'Inglaterra': '🏴󠁧󠁢󠁥󠁮󠁧󠁿', 'Estados Unidos': '🇺🇸', 'Australia': '🇦🇺',
    'Alemania': '🇩🇪', 'Paises Bajos': '🇳🇱', 'Senegal': '🇸🇳', 'Corea del Sur': '🇰🇷'
};

const ABBREVIATIONS = {
    'Argentina': 'ARG', 'Chile': 'CHI', 'Mexico': 'MEX', 'Nueva Zelanda': 'NZ',
    'Brasil': 'BRA', 'Espana': 'ESP', 'Japon': 'JPN', 'Ghana': 'GHA',
    'Francia': 'FRA', 'Inglaterra': 'ENG', 'Estados Unidos': 'USA', 'Australia': 'AUS',
    'Alemania': 'GER', 'Paises Bajos': 'NED', 'Senegal': 'SEN', 'Corea del Sur': 'KOR'
};

function shortName(nombre) {
    return ABBREVIATIONS[nombre] || nombre;
}

function flag(nombre) {
    return FLAGS[nombre] || '🏳️';
}

function toast(msg, type = 'success') {
    const container = document.getElementById('toastContainer');
    const t = document.createElement('div');
    t.className = `toast ${type}`;
    t.textContent = msg;
    container.appendChild(t);
    setTimeout(() => {
        t.classList.add('fade-out');
        setTimeout(() => t.remove(), 300);
    }, 3000);
}

async function apiGet(params) {
    const url = API_BASE + '?' + new URLSearchParams(params);
    const res = await fetch(url);
    if (!res.ok) {
        const err = await res.json().catch(() => ({}));
        throw new Error(err.error || `HTTP ${res.status}`);
    }
    return res.json();
}

async function apiPost(url, data) {
    const res = await fetch(url, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(data)
    });
    if (!res.ok) {
        const err = await res.json().catch(() => ({}));
        throw new Error(err.error || `HTTP ${res.status}`);
    }
    return res.json();
}

async function deleteResult(partidoId) {
    if (!confirm('¿Eliminar este resultado? Se borrarán los partidos posteriores.')) return;
    try {
        await apiPost(API_SAVE + '?action=eliminar', { partido_id: partidoId });
        toast('Resultado eliminado', 'info');
        await loadAll();
    } catch (e) {
        toast(e.message || 'Error al eliminar', 'error');
    }
}

async function loadAll() {
    try {
        allData = await apiGet({ action: 'todo' });
        console.log('[Mundial2026] loadAll data:', JSON.parse(JSON.stringify(allData)));
        renderGroups(allData.grupos);
        renderStats(allData.stats);
        renderBracket(allData.llaves, allData.fase_actual);
        updatePhaseBadge(allData.fase_actual);
        document.getElementById('loadingScreen').style.display = 'none';
        document.getElementById('appContent').style.display = 'block';
    } catch (e) {
        document.getElementById('loadingScreen').innerHTML = `
            <div style="text-align:center;color:#e53935;padding:40px;">
                <div style="font-size:3rem;margin-bottom:15px;">⚠️</div>
                <h3>Error al cargar datos</h3>
                <p style="color:#999;margin-top:8px;">${e.message}</p>
                <button class="btn-primary" style="margin-top:20px;" onclick="location.reload()">Reintentar</button>
            </div>
        `;
    }
}

function renderGroups(grupos) {
    const container = document.getElementById('groupsContainer');
    const letters = Object.keys(grupos).sort();

    container.innerHTML = letters.map(letra => {
        const g = grupos[letra];
        const color = g.color;
        const equipos = g.equipos;
        const partidos = g.partidos;

        let standingsHtml = '';
        if (equipos.length > 0) {
            const sorted = [...equipos].sort((a, b) => {
                if (parseInt(b.puntos) !== parseInt(a.puntos)) return parseInt(b.puntos) - parseInt(a.puntos);
                if (parseInt(b.dg) !== parseInt(a.dg)) return parseInt(b.dg) - parseInt(a.dg);
                return parseInt(b.gf) - parseInt(a.gf);
            });
            standingsHtml = `
            <table class="standings-table">
                <thead><tr>
                    <th>Equipo</th><th>PJ</th><th>PG</th><th>PE</th><th>PP</th>
                    <th>GF</th><th>GC</th><th>DG</th><th>PTS</th>
                </tr></thead>
                <tbody>
                ${sorted.map((eq, i) => `
                    <tr>
                        <td><div class="team-name"><span class="team-flag">${flag(eq.nombre)}</span> ${shortName(eq.nombre)}</div></td>
                        <td>${eq.pj}</td>
                        <td>${eq.pg}</td>
                        <td>${eq.pe}</td>
                        <td>${eq.pp}</td>
                        <td>${eq.gf}</td>
                        <td>${eq.gc}</td>
                        <td>${parseInt(eq.dg) > 0 ? '+' : ''}${eq.dg}</td>
                        <td style="font-weight:800;color:var(--gold);">${eq.puntos}</td>
                    </tr>
                `).join('')}
                </tbody>
            </table>`;
        }

        let matchesHtml = '';
        if (partidos.length > 0) {
            matchesHtml = `<div class="group-matches"><h4>📅 Partidos del Grupo</h4>`;
            partidos.forEach(p => {
                const played = p.jugado == 1;
                const localName = p.local_nombre;
                const visitName = p.visitante_nombre;

                if (played) {
                    const localWin = p.ganador_id == p.equipo_local_id;
                    const visitWin = p.ganador_id == p.equipo_visitante_id;
                    matchesHtml += `
                    <div class="match-row played">
                        <div class="match-result">
                            <span class="team ${localWin ? 'winner' : ''}">${flag(localName)} ${shortName(localName)}</span>
                            <span class="score"><strong>${p.goles_local}</strong> - <strong>${p.goles_visitante}</strong></span>
                            <span class="team ${visitWin ? 'winner' : ''}">${flag(visitName)} ${shortName(visitName)}</span>
                        </div>
                        <span style="font-size:0.65rem;color:var(--success);">✅</span>
                        <button class="match-del-btn" onclick="deleteResult(${p.id})" title="Eliminar resultado">🗑️</button>
                    </div>`;
                } else {
                    matchesHtml += `
                    <div class="match-row" id="match-${p.id}">
                        <span class="team-name" style="flex:0 0 auto;">${flag(localName)} ${shortName(localName)}</span>
                        <div class="score">
                            <input type="number" class="score-input" id="gl-${p.id}" min="0" value="" placeholder="0">
                            <span class="score-sep">-</span>
                            <input type="number" class="score-input" id="gv-${p.id}" min="0" value="" placeholder="0">
                        </div>
                        <span class="team-name" style="flex:0 0 auto;">${flag(visitName)} ${shortName(visitName)}</span>
                        <button class="match-save-btn" onclick="saveResult(${p.id})">Guardar</button>
                    </div>`;
                }
            });
            matchesHtml += `</div>`;
        }

        return `
        <div class="group-card animate-in">
            <div class="group-header" style="background:${color}22;border-bottom:2px solid ${color};">
                <h3>Grupo ${letra}</h3>
                <div class="group-badge" style="background:${color};">${letra}</div>
            </div>
            <div class="group-body">
                ${standingsHtml}
                ${matchesHtml}
            </div>
        </div>`;
    }).join('');
}

async function saveResult(partidoId) {
    const gl = document.getElementById(`gl-${partidoId}`);
    const gv = document.getElementById(`gv-${partidoId}`);
    if (!gl || !gv) return;

    const golesLocal = parseInt(gl.value);
    const golesVisitante = parseInt(gv.value);

    if (isNaN(golesLocal) || isNaN(golesVisitante) || golesLocal < 0 || golesVisitante < 0) {
        toast('Ingresá los goles de ambos equipos', 'error');
        return;
    }

    const btn = document.querySelector(`#match-${partidoId} .match-save-btn`);
    if (btn) btn.disabled = true;

    try {
        await apiPost(API_SAVE + '?action=resultado', {
            partido_id: partidoId,
            goles_local: golesLocal,
            goles_visitante: golesVisitante
        });
        toast('Resultado guardado correctamente!', 'success');
        await loadAll();
    } catch (e) {
        toast(e.message || 'Error al guardar el resultado', 'error');
        if (btn) btn.disabled = false;
    }
}

function renderStats(stats) {
    document.getElementById('statPartidos').textContent = stats.total_partidos;
    document.getElementById('statGoles').textContent = stats.total_goles;
    document.getElementById('statPromedio').textContent = stats.promedio.toFixed(2);
    document.getElementById('statMaxPais').textContent = stats.max_goles_pais ? stats.max_goles_pais.split(', ').map(shortName).join(', ') : '-';

    const ctx = document.getElementById('goalsChart').getContext('2d');
    if (goalsChart) goalsChart.destroy();

    const paises = stats.goles_por_pais || [];
    const labels = paises.map(p => shortName(p.nombre));
    const data = paises.map(p => parseInt(p.total));

    const FULL_NAME = Object.fromEntries(Object.entries(ABBREVIATIONS).map(([k, v]) => [v, k]));
    const groupColorsFull = {
        'Argentina':'#E53935','Chile':'#E53935','Mexico':'#E53935','Nueva Zelanda':'#E53935',
        'Brasil':'#1E88E5','Espana':'#1E88E5','Japon':'#1E88E5','Ghana':'#1E88E5',
        'Francia':'#43A047','Inglaterra':'#43A047','Estados Unidos':'#43A047','Australia':'#43A047',
        'Alemania':'#FB8C00','Paises Bajos':'#FB8C00','Senegal':'#FB8C00','Corea del Sur':'#FB8C00'
    };

    const bgColors = labels.map(l => {
        const full = FULL_NAME[l] || l;
        const c = groupColorsFull[full] || '#ffd700';
        return c + '99';
    });
    const borderColors = labels.map(l => {
        const full = FULL_NAME[l] || l;
        return groupColorsFull[full] || '#ffd700';
    });

    goalsChart = new Chart(ctx, {
        type: 'bar',
        data: {
            labels,
            datasets: [{
                label: 'Goles',
                data,
                backgroundColor: bgColors,
                borderColor: borderColors,
                borderWidth: 2,
                borderRadius: 6
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: { display: false },
                tooltip: {
                    callbacks: {
                        label: ctx => `${ctx.raw} goles`
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: { color: '#6a6a8a', stepSize: 1, font: { size: 11 } },
                    grid: { color: 'rgba(255,255,255,0.05)' }
                },
                x: {
                    ticks: { color: '#b0b0d0', font: { size: 10 } },
                    grid: { display: false }
                }
            }
        }
    });
}

function renderBracket(llaves, faseActual) {
    const inner = document.getElementById('bracketInner');
    const thirdPlaceDiv = document.getElementById('bracketThirdPlace');

    const cuartos = llaves.cuartos || [];
    const semis = llaves.semis || [];
    const finalMatch = llaves.final;
    const tercero = llaves.tercero;

    console.log('[Mundial2026] renderBracket llaves:', JSON.parse(JSON.stringify(llaves)));

    const qfLabels = ['1°A vs 2°B', '1°B vs 2°A', '1°C vs 2°D', '1°D vs 2°C'];
    const sfLabels = ['Semifinal 1', 'Semifinal 2'];

    let cuartosHtml = '';
    if (cuartos.length === 0) {
        cuartosHtml = `<div class="bracket-match"><div class="bracket-team tbd" style="justify-content:center;">Esperando resultados de fase de grupos...</div></div>`;
    } else {
        cuartos.forEach((m, i) => {
            cuartosHtml += renderBracketMatch(m, qfLabels[i] || `CF ${i+1}`, i < 2 ? 'sf1' : 'sf2');
        });
    }

    let semisHtml = '';
    if (semis.length === 0) {
        semisHtml = `<div class="bracket-match"><div class="bracket-team tbd" style="justify-content:center;">Esperando ganadores de cuartos...</div></div>`;
    } else {
        semis.forEach((m, i) => {
            semisHtml += renderBracketMatch(m, sfLabels[i] || `SF ${i+1}`, 'fin');
        });
    }

    let finalHtml = '';
    if (finalMatch && (finalMatch.equipo_local_id || finalMatch.equipo_visitante_id)) {
        finalHtml = renderBracketMatch(finalMatch, 'Gran Final', null);
    } else {
        const finalLabel = (semis.length >= 2 && semis.every(s => s.jugado == 1))
            ? '<span style="color:#4caf50;">🏆 Gran Final — Lista para jugar</span>'
            : '🏆 Gran Final';
        finalHtml = `
        <div class="bracket-match">
            <div class="bracket-round-label" style="color:#ffd700;font-size:0.8rem;">${finalLabel}</div>
            <div class="match-teams">
                <div class="bracket-team tbd">Por definirse</div>
                <div class="match-vs">VS</div>
                <div class="bracket-team tbd">Por definirse</div>
            </div>
        </div>`;
    }

    inner.innerHTML = `
    <div class="bracket-round" id="bracketRoundCuartos">
        <div class="bracket-round-label">Cuartos de Final</div>
        ${cuartosHtml}
    </div>
    <div class="bracket-round" id="bracketRoundSemis">
        <div class="bracket-round-label">Semifinales</div>
        ${semisHtml}
    </div>
    <div class="bracket-round bracket-final-round" id="bracketRoundFinal">
        ${finalHtml}
    </div>`;

    if (tercero && (tercero.equipo_local_id || tercero.equipo_visitante_id)) {
        thirdPlaceDiv.innerHTML = `
        <div class="bracket-round-label">🥉 Tercer Puesto</div>
        ${renderBracketMatch(tercero, 'Tercer Puesto', null)}`;
        thirdPlaceDiv.style.display = 'block';
    } else {
        thirdPlaceDiv.style.display = 'none';
    }
}

function renderBracketMatch(match, label, connector) {
    const infoA = match.local_nombre ? getGroupInfoForTeam(match.local_nombre) : null;
    const infoB = match.visitante_nombre ? getGroupInfoForTeam(match.visitante_nombre) : null;
    const colorA = infoA ? infoA.color : null;
    const colorB = infoB ? infoB.color : null;
    const grupoA = infoA ? infoA.letra : '';
    const grupoB = infoB ? infoB.letra : '';
    const played = match.jugado == 1;

    const localTeam = match.equipo_local_id && match.local_nombre ? `
        <div class="bracket-team ${match.ganador_id == match.equipo_local_id ? 'winner' : (played && match.ganador_id ? 'loser' : '')}">
            <div class="info">
                <span class="flag">${flag(match.local_nombre)}</span>
                <span class="name">${shortName(match.local_nombre)}</span>
                ${colorA ? `<span class="match-group-badge" style="background:${colorA}">${grupoA}</span>` : ''}
            </div>
            <span class="goals">${match.goles_local !== null && match.goles_local !== undefined ? match.goles_local : ''}</span>
        </div>` :
        `<div class="bracket-team tbd">Por definir</div>`;

    const visitTeam = match.equipo_visitante_id && match.visitante_nombre ? `
        <div class="bracket-team ${match.ganador_id == match.equipo_visitante_id ? 'winner' : (played && match.ganador_id ? 'loser' : '')}">
            <div class="info">
                <span class="flag">${flag(match.visitante_nombre)}</span>
                <span class="name">${shortName(match.visitante_nombre)}</span>
                ${colorB ? `<span class="match-group-badge" style="background:${colorB}">${grupoB}</span>` : ''}
            </div>
            <span class="goals">${match.goles_visitante !== null && match.goles_visitante !== undefined ? match.goles_visitante : ''}</span>
        </div>` :
        `<div class="bracket-team tbd">Por definir</div>`;

    const showScoreInputs = !played && match.equipo_local_id && match.equipo_visitante_id && match.fase && match.fase !== 'grupos';

    return `
    <div class="bracket-match ${match.equipo_local_id || match.equipo_visitante_id ? 'has-teams' : ''}">
        <div class="bracket-round-label">${label}</div>
        <div class="match-teams">
            ${localTeam}
            <div class="match-vs">${played ? '' : 'VS'}</div>
            ${visitTeam}
        </div>
        ${showScoreInputs ? `
        <div style="margin-top:6px;display:flex;gap:4px;justify-content:center;">
            <input type="number" class="score-input" id="bgl-${match.id}" min="0" placeholder="0" style="width:36px;font-size:0.75rem;">
            <span style="color:var(--text-muted);font-size:0.7rem;">-</span>
            <input type="number" class="score-input" id="bgv-${match.id}" min="0" placeholder="0" style="width:36px;font-size:0.75rem;">
            <button class="match-save-btn" onclick="saveKnockoutResult(${match.id})" style="font-size:0.6rem;padding:3px 8px;">OK</button>
        </div>` : ''}
        ${played ? `
        <div style="margin-top:4px;display:flex;align-items:center;justify-content:center;gap:8px;">
            <span style="font-size:0.6rem;color:var(--success);">${match.ganador_id ? '✅ Definido' : '🤝 Empate'}</span>
            <button class="match-del-btn" onclick="deleteResult(${match.id})" title="Eliminar resultado" style="font-size:0.65rem;">🗑️</button>
        </div>` : ''}
    </div>`;
}

async function saveKnockoutResult(partidoId) {
    const gl = document.getElementById(`bgl-${partidoId}`);
    const gv = document.getElementById(`bgv-${partidoId}`);
    if (!gl || !gv) return;

    const golesLocal = parseInt(gl.value);
    const golesVisitante = parseInt(gv.value);

    if (isNaN(golesLocal) || isNaN(golesVisitante) || golesLocal < 0 || golesVisitante < 0) {
        toast('Ingresá los goles de ambos equipos', 'error');
        return;
    }

    if (golesLocal === golesVisitante) {
        toast('En eliminatorias no puede haber empate. Definí un ganador.', 'error');
        return;
    }

    try {
        await apiPost(API_SAVE + '?action=resultado', {
            partido_id: partidoId,
            goles_local: golesLocal,
            goles_visitante: golesVisitante
        });
        toast('Resultado guardado! El ganador avanza.', 'success');
        await loadAll();
    } catch (e) {
        toast(e.message || 'Error al guardar', 'error');
    }
}

function getGroupInfoForTeam(teamName) {
    if (!allData || !allData.grupos) return null;
    for (const [letra, g] of Object.entries(allData.grupos)) {
        if (g.equipos.some(e => e.nombre === teamName)) {
            return { color: g.color, letra };
        }
    }
    return null;
}

function getGroupColorForTeam(teamName) {
    const info = getGroupInfoForTeam(teamName);
    return info ? info.color : null;
}

function getGroupLetterForTeam(teamName) {
    const info = getGroupInfoForTeam(teamName);
    return info ? info.letra : '';
}

function updatePhaseBadge(faseActual) {
    const badge = document.getElementById('phaseBadge');
    if (!badge) return;

    const labels = {
        'grupos': 'Fase de Grupos',
        'cuartos': 'Cuartos de Final',
        'semis': 'Semifinales',
        'tercero': 'Tercer Puesto',
        'final': 'Gran Final',
        'finalizado': '🏆 Torneo Finalizado'
    };
    const classes = {
        'grupos': 'groups',
        'cuartos': 'knockout',
        'semis': 'knockout',
        'tercero': 'knockout',
        'final': 'knockout',
        'finalizado': 'finished'
    };

    badge.textContent = labels[faseActual] || faseActual;
    badge.className = `phase-badge ${classes[faseActual] || 'groups'}`;
}

// Navigation
document.addEventListener('DOMContentLoaded', () => {
    const navLinks = document.querySelectorAll('#mainNav a');
    navLinks.forEach(link => {
        link.addEventListener('click', (e) => {
            e.preventDefault();
            navLinks.forEach(l => l.classList.remove('active'));
            link.classList.add('active');

            const target = link.dataset.target;
            document.querySelectorAll('section.section').forEach(s => s.style.display = 'none');

            if (target === 'grupos') document.getElementById('grupos').style.display = 'block';
            else if (target === 'eliminatorias') document.getElementById('eliminatorias').style.display = 'block';
            else if (target === 'estadisticas') document.getElementById('estadisticas').style.display = 'block';

            document.getElementById(target).scrollIntoView({ behavior: 'smooth', block: 'start' });
        });
    });

    document.querySelectorAll('section.section').forEach((s, i) => {
        s.style.display = i === 0 ? 'block' : 'none';
    });
});

loadAll();
