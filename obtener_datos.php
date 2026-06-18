<?php
require_once __DIR__ . '/conexion.php';

$action = $_GET['action'] ?? '';

switch ($action) {
    case 'todo':
        obtenerTodo($pdo);
        break;
    case 'grupos':
        obtenerGrupos($pdo);
        break;
    case 'partidos':
        obtenerPartidos($pdo);
        break;
    case 'stats':
        obtenerStats($pdo);
        break;
    case 'llaves':
        obtenerLlaves($pdo);
        break;
    default:
        obtenerTodo($pdo);
}

function obtenerTodo($pdo) {
    $grupos = obtenerGruposData($pdo);
    $partidos = $pdo->query('SELECT p.*, l.nombre AS local_nombre, v.nombre AS visitante_nombre FROM partidos p LEFT JOIN equipos l ON p.equipo_local_id = l.id LEFT JOIN equipos v ON p.equipo_visitante_id = v.id ORDER BY p.id')->fetchAll();
    $stats = calcularStats($pdo, $partidos);
    $llaves = obtenerLlavesData($pdo);
    $gruposCompletos = [];
    $faseActual = 'grupos';

    foreach ($grupos as $g => $data) {
        $pendientes = 0;
        foreach ($data['partidos'] as $p) {
            if (!$p['jugado']) $pendientes++;
        }
        if ($pendientes === 0) {
            $gruposCompletos[] = $g;
        }
    }

    if (count($gruposCompletos) === 4) {
        $stmt = $pdo->query("
            SELECT fase, COUNT(*) as total, SUM(CASE WHEN jugado=1 THEN 1 ELSE 0 END) as jugados
            FROM partidos WHERE fase != 'grupos'
            GROUP BY fase
            ORDER BY
                CASE fase
                    WHEN 'cuartos' THEN 1
                    WHEN 'semis' THEN 2
                    WHEN 'tercero' THEN 3
                    WHEN 'final' THEN 4
                    ELSE 5
                END
        ");
        $fasesData = $stmt->fetchAll();

        if (empty($fasesData)) {
            $faseActual = 'cuartos';
        } else {
            $todasJugadas = true;
            foreach ($fasesData as $fd) {
                if (intval($fd['jugados']) < intval($fd['total'])) {
                    $faseActual = $fd['fase'];
                    $todasJugadas = false;
                    break;
                }
            }
            if ($todasJugadas) {
                $expectedOrder = ['cuartos', 'semis', 'final'];
                $existing = array_column($fasesData, 'fase');
                $missingPhase = null;
                foreach ($expectedOrder as $ep) {
                    if (!in_array($ep, $existing)) {
                        $missingPhase = $ep;
                        break;
                    }
                }
                $faseActual = $missingPhase ?: 'finalizado';
            }
        }
    }

    responder([
        'grupos' => $grupos,
        'partidos' => $partidos,
        'stats' => $stats,
        'llaves' => $llaves,
        'fase_actual' => $faseActual,
        'grupos_completos' => $gruposCompletos
    ]);
}

function obtenerGruposData($pdo) {
    $equipos = $pdo->query("SELECT * FROM equipos WHERE eliminado = 0 ORDER BY grupo, puntos DESC, dg DESC, gf DESC")->fetchAll();
    $partidos = $pdo->query("SELECT p.*, l.nombre AS local_nombre, v.nombre AS visitante_nombre FROM partidos p LEFT JOIN equipos l ON p.equipo_local_id = l.id LEFT JOIN equipos v ON p.equipo_visitante_id = v.id WHERE p.fase = 'grupos' ORDER BY p.id")->fetchAll();

    $colores = ['A' => '#E53935', 'B' => '#1E88E5', 'C' => '#43A047', 'D' => '#FB8C00'];
    $grupos = [];

    foreach ($equipos as $eq) {
        $g = $eq['grupo'];
        if (!isset($grupos[$g])) {
            $grupos[$g] = ['color' => $colores[$g] ?? '#666', 'equipos' => [], 'partidos' => []];
        }
        $grupos[$g]['equipos'][] = $eq;
    }

    foreach ($partidos as $p) {
        $g = $p['grupo'];
        if (isset($grupos[$g])) {
            $grupos[$g]['partidos'][] = $p;
        }
    }

    return $grupos;
}

function obtenerPartidos($pdo) {
    $partidos = $pdo->query("SELECT p.*, l.nombre AS local_nombre, v.nombre AS visitante_nombre FROM partidos p LEFT JOIN equipos l ON p.equipo_local_id = l.id LEFT JOIN equipos v ON p.equipo_visitante_id = v.id ORDER BY p.id")->fetchAll();
    responder(['partidos' => $partidos]);
}

function obtenerLlavesData($pdo) {
    $partidos = $pdo->query("SELECT p.*, l.nombre AS local_nombre, v.nombre AS visitante_nombre FROM partidos p LEFT JOIN equipos l ON p.equipo_local_id = l.id LEFT JOIN equipos v ON p.equipo_visitante_id = v.id WHERE p.fase != 'grupos' ORDER BY FIELD(p.fase,'cuartos','semis','tercero','final'), p.id")->fetchAll();
    $llaves = ['cuartos' => [], 'semis' => [], 'tercero' => null, 'final' => null];

    foreach ($partidos as $p) {
        switch ($p['fase']) {
            case 'cuartos': $llaves['cuartos'][] = $p; break;
            case 'semis': $llaves['semis'][] = $p; break;
            case 'tercero': $llaves['tercero'] = $p; break;
            case 'final': $llaves['final'] = $p; break;
        }
    }

    return $llaves;
}

function obtenerLlaves($pdo) {
    responder(['llaves' => obtenerLlavesData($pdo)]);
}

function obtenerStats($pdo) {
    $partidos = $pdo->query("SELECT p.*, l.nombre AS local_nombre, v.nombre AS visitante_nombre FROM partidos p LEFT JOIN equipos l ON p.equipo_local_id = l.id LEFT JOIN equipos v ON p.equipo_visitante_id = v.id")->fetchAll();
    responder(['stats' => calcularStats($pdo, $partidos)]);
}

function calcularStats($pdo, $partidos) {
    $jugados = 0;
    $totalGoles = 0;

    foreach ($partidos as $p) {
        if ($p['jugado']) {
            $jugados++;
            $totalGoles += intval($p['goles_local']) + intval($p['goles_visitante']);
        }
    }

    $promedio = $jugados > 0 ? round($totalGoles / $jugados, 2) : 0;

    $golesPorPais = $pdo->query("
        SELECT e.nombre, SUM(goles) as total FROM (
            SELECT equipo_local_id AS eid, goles_local AS goles FROM partidos WHERE jugado = 1 AND goles_local IS NOT NULL
            UNION ALL
            SELECT equipo_visitante_id AS eid, goles_visitante AS goles FROM partidos WHERE jugado = 1 AND goles_visitante IS NOT NULL
        ) t JOIN equipos e ON t.eid = e.id GROUP BY e.nombre ORDER BY total DESC
    ")->fetchAll();

    $maxGoles = 0;
    $maxPaises = [];
    foreach ($golesPorPais as $gp) {
        if (intval($gp['total']) > $maxGoles) {
            $maxGoles = intval($gp['total']);
            $maxPaises = [$gp['nombre']];
        } elseif (intval($gp['total']) === $maxGoles && $maxGoles > 0) {
            $maxPaises[] = $gp['nombre'];
        }
    }

    return [
        'total_partidos' => $jugados,
        'total_goles' => $totalGoles,
        'promedio' => $promedio,
        'max_goles_pais' => implode(', ', $maxPaises),
        'goles_por_pais' => $golesPorPais
    ];
}

function obtenerGrupos($pdo) {
    responder(['grupos' => obtenerGruposData($pdo)]);
}
