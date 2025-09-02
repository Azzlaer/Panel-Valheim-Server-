<?php

// ================== LISTAR ARCHIVOS CFG ==================
function listCFGFiles($dir) {
    $rii = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir));
    $files = [];
    foreach ($rii as $file) {
        if ($file->isDir()) continue;
        if (strtolower($file->getExtension()) === "cfg") {
            $files[] = $file->getPathname();
        }
    }
    return $files;
}

$cfgDir = "C:\\Servidores\\Steam\\steamapps\\common\\Valheim dedicated server\\BepInEx\\config";
$cfgFiles = listCFGFiles($cfgDir);

// ================== EDITAR CFG ==================
if (isset($_GET['action']) && $_GET['action'] === "editcfg" && isset($_GET['file'])) {
    $file = $_GET['file'];

    // Seguridad: solo archivos dentro de la carpeta config
    $realBase = realpath($cfgDir);
    $realFile = realpath($file);

    if ($realFile && strpos($realFile, $realBase) === 0 && file_exists($realFile)) {
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['content'])) {
            file_put_contents($realFile, $_POST['content']);
            echo "<div class='alert alert-success'>✅ Archivo guardado correctamente.</div>";
        }

        $content = htmlspecialchars(file_get_contents($realFile));
        echo "<h3>📝 Editando: " . basename($realFile) . "</h3>";
        echo "<form method='POST'>";
        echo "<textarea name='content' style='width:100%; height:500px;'>$content</textarea>";
        echo "<br><button type='submit' class='btn btn-success mt-3'>💾 Guardar</button>";
        echo "</form>";
        exit;
    } else {
        die("⚠️ Archivo no válido.");
    }
}


// ================== LISTAR ARCHIVOS DB ==================
function listDBFiles($dir) {
    $files = glob($dir . DIRECTORY_SEPARATOR . "*.dll");
    return $files ? $files : [];
}

$dbDir = "C:\\Servidores\\Steam\\steamapps\\common\\Valheim dedicated server\\BepInEx\\plugins";
$dbFiles = listDBFiles($dbDir);




// ================== VISOR DE LOGS ==================
if (isset($_GET['action']) && $_GET['action'] === "viewlog" && isset($_GET['file'])) {
    $file = $_GET['file'];

    // Seguridad: solo permitimos archivos predefinidos
    $allowed = [
        "server" => "C:\\Servidores\\Steam\\steamapps\\common\\Valheim dedicated server\\server1.txt",
        "steamcmd" => "C:\\Servidores\\Steam\\steamapps\\common\\Valheim dedicated server\\steamcmd_log.txt"
    ];

    if (isset($allowed[$file]) && file_exists($allowed[$file])) {
        header("Content-Type: text/plain");
        readfile($allowed[$file]);
    }
    exit;
}


// ================== CONFIG ==================
$serverDir = "C:\\Servidores\\Steam\\steamapps\\common\\Valheim dedicated server\\server01\\"; 
$servers = json_decode(file_get_contents("servers.json"), true); 
$lists = [
    "adminlist.txt"     => "👑 Administradores",
    "bannedlist.txt"    => "🚫 Baneados",
    "permittedlist.txt" => "✅ Permitidos"
];

// ================== FUNCIONES ==================
function isRunning($processName) {
    $tasklist = shell_exec("tasklist /FI \"IMAGENAME eq $processName\"");
    return strpos($tasklist, $processName) !== false;
}

function readList($file) {
    if (!file_exists($file)) return [];
    $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    return $lines ?: [];
}

function saveList($file, $lines) {
    file_put_contents($file, implode(PHP_EOL, $lines) . PHP_EOL);
}

// ================== ACCIONES SERVIDORES ==================
if (isset($_GET['action']) && isset($_GET['id'])) {
    $id = intval($_GET['id']);
    foreach ($servers as $server) {
        if ($server['id'] == $id) {
            if ($_GET['action'] == "start") {
                pclose(popen("start \"ValheimServer{$id}\" \"{$server['path']}\" {$server['params']}", "r"));
            } elseif ($_GET['action'] == "stop") {
                // ⚠️ Ideal: usar SendSignalCtrlC.exe para cierre seguro
                shell_exec("taskkill /F /IM valheim_server.exe");
            }
        }
    }
    header("Location: index.php");
    exit;
}

// ================== ACCIONES LISTAS ==================
if (isset($_POST['action']) && isset($_POST['file'])) {
    $file = $serverDir . $_POST['file'];
    $list = readList($file);

    if ($_POST['action'] === "add" && !empty($_POST['steamid'])) {
        $steamid = trim($_POST['steamid']);
        if (!in_array($steamid, $list)) {
            $list[] = $steamid;
            saveList($file, $list);
        }
    } elseif ($_POST['action'] === "delete" && isset($_POST['steamid'])) {
        $steamid = trim($_POST['steamid']);
        $list = array_diff($list, [$steamid]);
        saveList($file, $list);
    }

    header("Location: index.php#lists");
    exit;
}

// ================== ACCIONES UPDATE ==================
if (isset($_GET['action']) && $_GET['action'] === "update" && isset($_GET['type'])) {
    if (!isRunning("valheim_server.exe")) {
        $steamcmd = "C:\\Servidores\\Steam\\steamcmd.exe";

        if ($_GET['type'] === "prebeta") {
            $cmd = "\"$steamcmd\" +login anonymous +app_update 896660 -beta public-test -betapassword yesimadebackups validate +quit";
        } elseif ($_GET['type'] === "normal") {
            $cmd = "\"$steamcmd\" +login anonymous +app_update 896660 validate +quit";
        }

        // Ejecutar actualización sin bloquear
        pclose(popen("start \"ValheimUpdate\" cmd /c $cmd", "r"));
    }

    header("Location: index.php#update");
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>⚔️ Panel de Administración Valheim</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-dark text-light">

<div class="container py-4">
    <h1 class="text-center mb-5">⚔️ Panel de Administración de Valheim</h1>

    <!-- ================== SERVIDORES ================== -->
    <h2 class="mb-3">🖥️ Servidores</h2>
    <table class="table table-dark table-hover text-center align-middle">
        <thead>
            <tr>
                <th>Nombre</th>
                <th>Ejecutable</th>
                <th>Parámetros</th>
                <th>Estado</th>
                <th>Acciones</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($servers as $server): ?>
                <?php $running = isRunning("valheim_server.exe"); ?>
                <tr>
                    <td><strong><?= htmlspecialchars($server['name']) ?></strong></td>
                    <td><?= htmlspecialchars($server['path']) ?></td>
                    <td><small><?= htmlspecialchars($server['params']) ?></small></td>
                    <td>
                        <?php if ($running): ?>
                            <span class="badge bg-success">✅ En ejecución</span>
                        <?php else: ?>
                            <span class="badge bg-danger">🛑 Apagado</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($running): ?>
                            <a href="?action=stop&id=<?= $server['id'] ?>" class="btn btn-danger btn-sm">🛑 Detener</a>
                        <?php else: ?>
                            <a href="?action=start&id=<?= $server['id'] ?>" class="btn btn-success btn-sm">🚀 Iniciar</a>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
	<div class="container my-5">
    <h2 class="text-center mb-4">📊 Archivos DB en Plugins</h2>
    <p>Total encontrados: <b><?= count($dbFiles) ?></b></p>
    <table class="table table-bordered table-striped">
        <thead>
            <tr>
                <th>Archivo</th>
                <th>Ruta Completa</th>
                <th>Tamaño</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($dbFiles as $file): ?>
                <tr>
                    <td><?= basename($file) ?></td>
                    <td><?= $file ?></td>
                    <td><?= filesize($file) ?> bytes</td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<div class="container my-5">
    <h2 class="text-center mb-4">⚙️ Archivos CFG en Config</h2>
    <p>Total encontrados: <b><?= count($cfgFiles) ?></b></p>
    <table class="table table-bordered table-striped">
        <thead>
            <tr>
                <th>Archivo</th>
                <th>Ruta Completa</th>
                <th>Acciones</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($cfgFiles as $file): ?>
                <tr>
                    <td><?= basename($file) ?></td>
                    <td><?= $file ?></td>
                    <td>
                        <a href="index.php?action=editcfg&file=<?= urlencode($file) ?>" class="btn btn-primary btn-sm">📝 Editar</a>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>


    <!-- ================== LISTAS ================== -->
    <div class="container my-5" id="lists">
        <h2 class="text-center mb-4">📂 Gestión de Listas</h2>

        <!-- Tabs -->
        <ul class="nav nav-tabs" id="listTabs" role="tablist">
            <?php $first = true; foreach ($lists as $file => $title): ?>
                <li class="nav-item" role="presentation">
                    <button class="nav-link <?= $first ? 'active' : '' ?>" id="<?= $file ?>-tab"
                            data-bs-toggle="tab" data-bs-target="#<?= $file ?>" type="button" role="tab">
                        <?= $title ?>
                    </button>
                </li>
            <?php $first = false; endforeach; ?>
        </ul>

        <!-- Tab Content -->
        <div class="tab-content bg-dark text-light p-3 border border-secondary border-top-0 rounded-bottom">
            <?php $first = true; foreach ($lists as $file => $title): ?>
                <?php $items = readList($serverDir . $file); ?>
                <div class="tab-pane fade <?= $first ? 'show active' : '' ?>" id="<?= $file ?>" role="tabpanel">
                    <h5><?= $title ?></h5>

                    <!-- Tabla -->
                    <table class="table table-dark table-striped text-center">
                        <thead>
                            <tr>
                                <th>SteamID64</th>
                                <th>Acción</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($items)): ?>
                                <tr><td colspan="2">⚠️ Lista vacía</td></tr>
                            <?php else: ?>
                                <?php foreach ($items as $id): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($id) ?></td>
                                        <td>
                                            <form method="post" style="display:inline;">
                                                <input type="hidden" name="file" value="<?= $file ?>">
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="steamid" value="<?= htmlspecialchars($id) ?>">
                                                <button type="submit" class="btn btn-sm btn-danger">❌ Eliminar</button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>

                    <!-- Formulario agregar -->
                    <form method="post" class="d-flex mt-3">
                        <input type="hidden" name="file" value="<?= $file ?>">
                        <input type="hidden" name="action" value="add">
                        <input type="text" name="steamid" class="form-control me-2" placeholder="Ingresa SteamID64" required>
                        <button type="submit" class="btn btn-success">➕ Agregar</button>
                    </form>
                </div>
            <?php $first = false; endforeach; ?>
        </div>
    </div>
	
	<!-- ================== VISOR DE LOGS ================== -->
<div class="container my-5" id="logs">
    <h2 class="text-center mb-4">📜 Visor de Logs</h2>

    <div class="d-flex justify-content-center mb-3">
        <button class="btn btn-primary mx-2" onclick="loadLog('server')">📖 Ver Log del Servidor</button>
        <button class="btn btn-warning mx-2" onclick="loadLog('steamcmd')">⚙️ Ver Log de SteamCMD</button>
    </div>

    <pre id="logContent" style="background:#000; color:#0f0; padding:15px; height:400px; overflow-y:scroll; border-radius:10px;">
        Selecciona un log para visualizar...
    </pre>
</div>


    <!-- ================== ACTUALIZACIÓN ================== -->
    <div class="container my-5" id="update">
        <h2 class="text-center mb-4">🔄 Actualización del Servidor Valheim</h2>
        <p class="text-center">El servidor debe estar <strong>apagado</strong> para ejecutar la actualización.</p>

        <table class="table table-dark table-hover text-center align-middle">
            <thead>
                <tr>
                    <th>Configuración</th>
                    <th>Comando</th>
                    <th>Acción</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>🧪 Servidor Pre-Beta</td>
                    <td><small>steamcmd.exe +login anonymous +app_update 896660 -beta public-test -betapassword yesimadebackups validate +quit</small></td>
                    <td>
                        <?php if (!isRunning("valheim_server.exe")): ?>
                            <a href="?action=update&type=prebeta" class="btn btn-warning btn-sm">🔄 Actualizar Pre-Beta</a>
                        <?php else: ?>
                            <button class="btn btn-secondary btn-sm" disabled>⚠️ Servidor en ejecución</button>
                        <?php endif; ?>
                    </td>
                </tr>
                <tr>
                    <td>🎮 Servidor Normal</td>
                    <td><small>steamcmd.exe +login anonymous +app_update 896660 validate +quit</small></td>
                    <td>
                        <?php if (!isRunning("valheim_server.exe")): ?>
                            <a href="?action=update&type=normal" class="btn btn-success btn-sm">🔄 Actualizar Normal</a>
                        <?php else: ?>
                            <button class="btn btn-secondary btn-sm" disabled>⚠️ Servidor en ejecución</button>
                        <?php endif; ?>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</div>
<script>
let logInterval;

function loadLog(type) {
    clearInterval(logInterval);
    document.getElementById("logContent").innerText = "Cargando " + type + "...";

    function fetchLog() {
        fetch("index.php?action=viewlog&file=" + type)
            .then(response => response.text())
            .then(data => {
                const logBox = document.getElementById("logContent");
                logBox.innerText = data;

                // Autoscroll
                logBox.scrollTop = logBox.scrollHeight;
            })
            .catch(err => {
                document.getElementById("logContent").innerText = "⚠️ Error leyendo log.";
            });
    }

    // Primera carga
    fetchLog();
    // Actualizar cada 3 segundos
    logInterval = setInterval(fetchLog, 3000);
}
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
