<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mundial 2026 - Gestión de Partidos</title>
    <link rel="stylesheet" href="styles.css?v=<?= filemtime('styles.css') ?>">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>

<div class="toast-container" id="toastContainer"></div>

<div class="container">
    <!-- HERO -->
    <header class="hero">
        <div class="hero-badge">FIFA World Cup 2026</div>
        <h1><span class="trophy-glow">🏆</span> Mundial 2026</h1>
        <p class="hero-subtitle">Gestión de Partidos y Estadísticas</p>
        <div class="hero-line"></div>
    </header>

    <!-- NAV -->
    <nav class="main-nav" id="mainNav">
        <a href="#grupos" class="active" data-target="grupos">Fase de Grupos</a>
        <a href="#eliminatorias" data-target="eliminatorias">Eliminatorias</a>
        <a href="#estadisticas" data-target="estadisticas">Estadísticas</a>
    </nav>

    <!-- LOADING -->
    <div class="loading" id="loadingScreen">
        <div class="loading-spinner"></div>
        <div class="loading-text">Cargando Mundial 2026...</div>
    </div>

    <!-- CONTENT -->
    <div id="appContent" style="display:none">

        <!-- GROUPS SECTION -->
        <section class="section" id="grupos">
            <div class="section-header">
                <span class="phase-badge groups" id="phaseBadge">Fase de Grupos</span>
                <div class="line"></div>
            </div>
            <div class="groups-grid" id="groupsContainer"></div>
        </section>

        <!-- BRACKET SECTION -->
        <section class="section" id="eliminatorias">
            <div class="section-header">
                <h2>🏅 Cuadro Eliminatorio</h2>
                <div class="line"></div>
            </div>
            <div class="bracket-section" id="bracketContainer">
                <div class="bracket-container" id="bracketInner"></div>
                <div class="bracket-third-place" id="bracketThirdPlace"></div>
            </div>
        </section>

        <!-- STATS SECTION -->
        <section class="section" id="estadisticas">
            <div class="section-header">
                <h2>📊 Estadísticas Generales</h2>
                <div class="line"></div>
            </div>
            <div class="stats-grid" id="statsContainer">
                <div class="stat-card">
                    <div class="stat-icon">⚽</div>
                    <span class="stat-value" id="statPartidos">0</span>
                    <span class="stat-label">Partidos Jugados</span>
                </div>
                <div class="stat-card">
                    <div class="stat-icon">🥅</div>
                    <span class="stat-value" id="statGoles">0</span>
                    <span class="stat-label">Goles Totales</span>
                </div>
                <div class="stat-card">
                    <div class="stat-icon">📈</div>
                    <span class="stat-value" id="statPromedio">0.00</span>
                    <span class="stat-label">Promedio por Partido</span>
                </div>
                <div class="stat-card">
                    <div class="stat-icon">🔥</div>
                    <span class="stat-value" id="statMaxPais">-</span>
                    <span class="stat-label">País con Más Goles</span>
                </div>
            </div>
            <div class="chart-container">
                <canvas id="goalsChart"></canvas>
            </div>
        </section>
    </div>

    <!-- FOOTER -->
    <footer class="footer">
        <p>Mundial 2026 &mdash; <span class="gold">Gestión de Partidos</span> &mdash; Desarrollado con PHP + MySQL</p>
    </footer>
</div>

<script src="script.js?v=<?= filemtime('script.js') ?>"></script>
</body>
</html>
