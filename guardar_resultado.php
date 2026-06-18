<?php
require_once __DIR__ . '/conexion.php';

$action = $_GET['action'] ?? '';

if ($action === 'resultado') {
    $data = jsonInput();
    guardarResultado($pdo, $data);
} elseif ($action === 'eliminar') {
    $data = jsonInput();
    eliminarResultado($pdo, $data);
} elseif ($action === 'inicializar') {
    inicializar($pdo);
} elseif ($action === 'reiniciar') {
    reiniciar($pdo);
} else {
    error('Acción no válida');
}

function guardarResultado($pdo, $data) {
    $partidoId = intval($data['partido_id'] ?? 0);
    $golesLocal = intval($data['goles_local'] ?? -1);
    $golesVisitante = intval($data['goles_visitante'] ?? -1);

    if ($partidoId <= 0 || $golesLocal < 0 || $golesVisitante < 0) {
        error('Datos de resultado inválidos');
    }

    $stmt = $pdo->prepare("SELECT p.*, l.nombre AS ln, v.nombre AS vn, l.id AS lid, v.id AS vid FROM partidos p JOIN equipos l ON p.equipo_local_id = l.id JOIN equipos v ON p.equipo_visitante_id = v.id WHERE p.id = ?");
    $stmt->execute([$partidoId]);
    $partido = $stmt->fetch();

    if (!$partido) {
        error('Partido no encontrado', 404);
    }

    if ($partido['jugado']) {
        error('Este partido ya tiene un resultado cargado');
    }

    $ganadorId = null;
    $perdedorId = null;
    if ($golesLocal > $golesVisitante) {
        $ganadorId = $partido['lid'];
        $perdedorId = $partido['vid'];
    } elseif ($golesVisitante > $golesLocal) {
        $ganadorId = $partido['vid'];
        $perdedorId = $partido['lid'];
    }

    $stmt = $pdo->prepare("UPDATE partidos SET goles_local = ?, goles_visitante = ?, jugado = 1, ganador_id = ?, perdedor_id = ? WHERE id = ?");
    $stmt->execute([$golesLocal, $golesVisitante, $ganadorId, $perdedorId, $partidoId]);

    if ($partido['fase'] === 'grupos') {
        recalcularGrupo($pdo, $partido['grupo']);
        verificarAvanceEliminatorias($pdo);
    } else {
        avanzarEnLlave($pdo, $partido, $ganadorId, $perdedorId);
    }

    responder(['success' => true, 'message' => 'Resultado guardado correctamente']);
}

function recalcularGrupo($pdo, $grupo) {
    $equipos = $pdo->prepare("SELECT * FROM equipos WHERE grupo = ?");
    $equipos->execute([$grupo]);
    $equipos = $equipos->fetchAll();

    $stmt = $pdo->prepare("SELECT * FROM partidos WHERE grupo = ? AND fase = 'grupos' AND jugado = 1");
    $stmt->execute([$grupo]);
    $partidos = $stmt->fetchAll();

    foreach ($equipos as $eq) {
        $eid = $eq['id'];
        $pj = 0; $pg = 0; $pe = 0; $pp = 0; $gf = 0; $gc = 0;

        foreach ($partidos as $p) {
            if ($p['equipo_local_id'] == $eid) {
                $pj++;
                $gf += intval($p['goles_local']);
                $gc += intval($p['goles_visitante']);
                if ($p['ganador_id'] == $eid) $pg++;
                elseif ($p['perdedor_id'] == $eid) $pp++;
                else $pe++;
            } elseif ($p['equipo_visitante_id'] == $eid) {
                $pj++;
                $gf += intval($p['goles_visitante']);
                $gc += intval($p['goles_local']);
                if ($p['ganador_id'] == $eid) $pg++;
                elseif ($p['perdedor_id'] == $eid) $pp++;
                else $pe++;
            }
        }

        $pts = ($pg * 3) + $pe;
        $dg = $gf - $gc;

        $upd = $pdo->prepare("UPDATE equipos SET puntos = ?, pj = ?, pg = ?, pe = ?, pp = ?, gf = ?, gc = ?, dg = ? WHERE id = ?");
        $upd->execute([$pts, $pj, $pg, $pe, $pp, $gf, $gc, $dg, $eid]);
    }
}

function verificarAvanceEliminatorias($pdo) {
    $grupos = $pdo->query("SELECT DISTINCT grupo FROM equipos")->fetchAll(PDO::FETCH_COLUMN);
    $todosCompletos = true;

    foreach ($grupos as $g) {
        $pendientes = $pdo->prepare("SELECT COUNT(*) as c FROM partidos WHERE grupo = ? AND fase = 'grupos' AND jugado = 0");
        $pendientes->execute([$g]);
        if ($pendientes->fetch()['c'] > 0) {
            $todosCompletos = false;
            break;
        }
    }

    if (!$todosCompletos) return;

    $existeElim = $pdo->query("SELECT COUNT(*) as c FROM partidos WHERE fase != 'grupos'")->fetch()['c'];
    if ($existeElim > 0) return;

    $primeros = [];
    $segundos = [];

    foreach ($grupos as $g) {
        $stmt = $pdo->prepare("SELECT * FROM equipos WHERE grupo = ? ORDER BY puntos DESC, dg DESC, gf DESC LIMIT 2");
        $stmt->execute([$g]);
        $top = $stmt->fetchAll();
        if (count($top) >= 2) {
            $primeros[$g] = $top[0];
            $segundos[$g] = $top[1];
        } else {
            return;
        }
    }

    $llaves = [
        ['local' => $primeros['A']['id'], 'visitante' => $segundos['B']['id']],
        ['local' => $primeros['B']['id'], 'visitante' => $segundos['A']['id']],
        ['local' => $primeros['C']['id'], 'visitante' => $segundos['D']['id']],
        ['local' => $primeros['D']['id'], 'visitante' => $segundos['C']['id']],
    ];

    $insertLlave = $pdo->prepare("INSERT INTO partidos (grupo, equipo_local_id, equipo_visitante_id, fase, ronda) VALUES (NULL, ?, ?, 'cuartos', ?)");
    foreach ($llaves as $i => $ll) {
        $insertLlave->execute([$ll['local'], $ll['visitante'], 'cuartos_' . ($i + 1)]);
        error_log("[Mundial2026] Cuarto de final " . ($i+1) . " creado: local_id={$ll['local']} visitante_id={$ll['visitante']}");
    }
}

function avanzarEnLlave($pdo, $partido, $ganador, $perdedor) {
    $ganador = intval($ganador);
    $perdedor = intval($perdedor);
    error_log("[Mundial2026] avanzarEnLlave: partido_id={$partido['id']} fase={$partido['fase']} ronda='{$partido['ronda']}' ganador=$ganador perdedor=$perdedor");
    if (!$ganador) {
        error_log("[Mundial2026] avanzarEnLlave: SIN GANADOR, se cancela avance");
        return;
    }

    $fase = $partido['fase'];
    $ronda = $partido['ronda'] ?? '';

    switch ($fase) {
        case 'cuartos':
            $num = 0;
            if (preg_match('/cuartos_(\d+)/', $ronda, $m)) {
                $num = intval($m[1]);
            } else {
                error_log("[Mundial2026] ERROR: No se pudo extraer número de ronda '{$ronda}' en cuartos");
                return;
            }

            $semisMap = [1 => 'semis_1', 2 => 'semis_1', 3 => 'semis_2', 4 => 'semis_2'];
            $colMap = [1 => 'equipo_local_id', 2 => 'equipo_visitante_id', 3 => 'equipo_local_id', 4 => 'equipo_visitante_id'];

            if (!isset($semisMap[$num]) || !isset($colMap[$num])) {
                error_log("[Mundial2026] ERROR: Número de cuarto inválido: $num");
                return;
            }

            $rondaSemis = $semisMap[$num];
            $columna = $colMap[$num];

            error_log("[Mundial2026] Procesando cuarto $num → $rondaSemis, columna=$columna");

            $existe = $pdo->prepare("SELECT id, equipo_local_id, equipo_visitante_id FROM partidos WHERE fase='semis' AND ronda=?");
            $existe->execute([$rondaSemis]);
            $semis = $existe->fetch();

            if ($semis) {
                $stmtUpd = $pdo->prepare("UPDATE partidos SET $columna = ? WHERE id = ?");
                $stmtUpd->execute([$ganador, $semis['id']]);
                error_log("[Mundial2026] Semifinal {$rondaSemis} (id={$semis['id']}) actualizada: {$columna}=$ganador");
            } else {
                $local = ($columna === 'equipo_local_id') ? $ganador : null;
                $visit = ($columna === 'equipo_visitante_id') ? $ganador : null;
                $stmtIns = $pdo->prepare("INSERT INTO partidos (equipo_local_id, equipo_visitante_id, fase, ronda) VALUES (?, ?, 'semis', ?)");
                $stmtIns->execute([$local, $visit, $rondaSemis]);
                error_log("[Mundial2026] Semifinal {$rondaSemis} CREADA: id={$pdo->lastInsertId()} local=$local visit=$visit");
            }
            break;

        case 'semis':
            $existeFinal = $pdo->query("SELECT id, equipo_local_id, equipo_visitante_id FROM partidos WHERE fase='final'")->fetch();
            $existeTercero = $pdo->query("SELECT id, equipo_local_id, equipo_visitante_id FROM partidos WHERE fase='tercero'")->fetch();

            error_log("[Mundial2026] Procesando semifinal: ronda=$ronda, final_exists=" . ($existeFinal ? 'si' : 'no') . ", tercero_exists=" . ($existeTercero ? 'si' : 'no'));

            $esSemis1 = ($ronda === 'semis_1');
            $colFinal = $esSemis1 ? 'equipo_local_id' : 'equipo_visitante_id';
            $colTercero = $esSemis1 ? 'equipo_local_id' : 'equipo_visitante_id';

            if ($existeFinal) {
                $stmtFinal = $pdo->prepare("UPDATE partidos SET $colFinal = ? WHERE fase = 'final'");
                $stmtFinal->execute([$ganador]);
                error_log("[Mundial2026] Final actualizada: $colFinal = $ganador (id={$existeFinal['id']})");
            } else {
                $localFinal = $esSemis1 ? $ganador : null;
                $visitFinal = $esSemis1 ? null : $ganador;
                $stmtIns = $pdo->prepare("INSERT INTO partidos (equipo_local_id, equipo_visitante_id, fase, ronda) VALUES (?, ?, 'final', 'final')");
                $stmtIns->execute([$localFinal, $visitFinal]);
                error_log("[Mundial2026] Final CREADA: id={$pdo->lastInsertId()} local=$localFinal visit=$visitFinal");
            }

            if ($perdedor) {
                if ($existeTercero) {
                    $stmtTerc = $pdo->prepare("UPDATE partidos SET $colTercero = ? WHERE fase = 'tercero'");
                    $stmtTerc->execute([$perdedor]);
                    error_log("[Mundial2026] Tercer puesto actualizado: $colTercero = $perdedor (id={$existeTercero['id']})");
                } else {
                    $localTerc = $esSemis1 ? $perdedor : null;
                    $visitTerc = $esSemis1 ? null : $perdedor;
                    $stmtIns = $pdo->prepare("INSERT INTO partidos (equipo_local_id, equipo_visitante_id, fase, ronda) VALUES (?, ?, 'tercero', 'tercero')");
                    $stmtIns->execute([$localTerc, $visitTerc]);
                    error_log("[Mundial2026] Tercer puesto CREADO: id={$pdo->lastInsertId()} local=$localTerc visit=$visitTerc");
                }
            }
            break;
    }
}

function eliminarResultado($pdo, $data) {
    $partidoId = intval($data['partido_id'] ?? 0);
    if ($partidoId <= 0) error('ID de partido inválido');

    $stmt = $pdo->prepare("SELECT * FROM partidos WHERE id = ?");
    $stmt->execute([$partidoId]);
    $partido = $stmt->fetch();
    if (!$partido) error('Partido no encontrado', 404);
    if (!$partido['jugado']) error('El partido no tiene resultado para eliminar');

    $fase = $partido['fase'];
    $grupo = $partido['grupo'];
    error_log("[Mundial2026] eliminarResultado: partido_id=$partidoId fase=$fase ganador={$partido['ganador_id']} perdedor={$partido['perdedor_id']}");

    $pdo->prepare("UPDATE partidos SET goles_local = NULL, goles_visitante = NULL, jugado = 0, ganador_id = NULL, perdedor_id = NULL WHERE id = ?")->execute([$partidoId]);

    if ($fase === 'grupos') {
        recalcularGrupo($pdo, $grupo);
        $pdo->exec("DELETE FROM partidos WHERE fase != 'grupos'");
        error_log("[Mundial2026] eliminarResultado grupo: todas las eliminatorias eliminadas");
    } else {
        $ganadorId = intval($partido['ganador_id']);
        $perdedorId = intval($partido['perdedor_id']);

        if ($fase === 'cuartos') {
            $ronda = $partido['ronda'] ?? '';
            $num = 0;
            if (preg_match('/cuartos_(\d+)/', $ronda, $m)) $num = intval($m[1]);
            $semisMap = [1 => 'semis_1', 2 => 'semis_1', 3 => 'semis_2', 4 => 'semis_2'];
            $colMap = [1 => 'equipo_local_id', 2 => 'equipo_visitante_id', 3 => 'equipo_local_id', 4 => 'equipo_visitante_id'];

            if (isset($semisMap[$num], $colMap[$num])) {
                $rondaSemis = $semisMap[$num];
                $columna = $colMap[$num];
                $pdo->prepare("UPDATE partidos SET $columna = NULL, goles_local = NULL, goles_visitante = NULL, jugado = 0, ganador_id = NULL, perdedor_id = NULL WHERE fase='semis' AND ronda=?")->execute([$rondaSemis]);
                error_log("[Mundial2026] eliminarResultado cuartos: semifinal {$rondaSemis} columna {$columna} limpiada");
            }
        } elseif ($fase === 'semis') {
            $ronda = $partido['ronda'] ?? '';
            $esSemis1 = ($ronda === 'semis_1');
            $colFinal = $esSemis1 ? 'equipo_local_id' : 'equipo_visitante_id';
            $colTercero = $esSemis1 ? 'equipo_local_id' : 'equipo_visitante_id';
            $pdo->prepare("UPDATE partidos SET $colFinal = NULL, goles_local = NULL, goles_visitante = NULL, jugado = 0, ganador_id = NULL, perdedor_id = NULL WHERE fase='final'")->execute();
            $pdo->prepare("UPDATE partidos SET $colTercero = NULL, goles_local = NULL, goles_visitante = NULL, jugado = 0, ganador_id = NULL, perdedor_id = NULL WHERE fase='tercero'")->execute();
            error_log("[Mundial2026] eliminarResultado semis: final y tercer puesto limpiados");
        } elseif ($fase === 'final' || $fase === 'tercero') {
            error_log("[Mundial2026] eliminarResultado {$fase}: no hay fases posteriores que limpiar");
        }
    }

    responder(['success' => true, 'message' => 'Resultado eliminado correctamente']);
}

function inicializar($pdo) {
    $pdo->exec("TRUNCATE TABLE partidos");
    $pdo->exec("TRUNCATE TABLE equipos");

    $equipos = [
        ['Argentina', 'A'], ['Chile', 'A'], ['México', 'A'], ['Nueva Zelanda', 'A'],
        ['Brasil', 'B'], ['España', 'B'], ['Japón', 'B'], ['Ghana', 'B'],
        ['Francia', 'C'], ['Inglaterra', 'C'], ['Estados Unidos', 'C'], ['Australia', 'C'],
        ['Alemania', 'D'], ['Países Bajos', 'D'], ['Senegal', 'D'], ['Corea del Sur', 'D'],
    ];

    $ins = $pdo->prepare("INSERT INTO equipos (nombre, grupo) VALUES (?, ?)");
    $idMap = [];
    foreach ($equipos as $i => $eq) {
        $ins->execute([$eq[0], $eq[1]]);
        $idMap[] = $pdo->lastInsertId();
    }

    $partidosGrupo = [
        [1,2],[3,4],[1,3],[2,4],[1,4],[2,3]
    ];

    $grupos = ['A','B','C','D'];
    $idx = 0;
    $insP = $pdo->prepare("INSERT INTO partidos (grupo, equipo_local_id, equipo_visitante_id, fase) VALUES (?, ?, ?, 'grupos')");

    foreach ($grupos as $g) {
        foreach ($partidosGrupo as $pg) {
            $lid = $idMap[$idx + $pg[0] - 1];
            $vid = $idMap[$idx + $pg[1] - 1];
            $insP->execute([$g, $lid, $vid]);
        }
        $idx += 4;
    }

    responder(['success' => true, 'message' => 'Datos inicializados correctamente']);
}

function reiniciar($pdo) {
    $pdo->exec("UPDATE partidos SET goles_local = NULL, goles_visitante = NULL, jugado = 0, ganador_id = NULL, perdedor_id = NULL WHERE fase = 'grupos'");
    $pdo->exec("DELETE FROM partidos WHERE fase != 'grupos'");
    $pdo->exec("UPDATE equipos SET puntos = 0, pj = 0, pg = 0, pe = 0, pp = 0, gf = 0, gc = 0, dg = 0, eliminado = 0");
    responder(['success' => true, 'message' => 'Torneo reiniciado. Los grupos están limpios.']);
}
