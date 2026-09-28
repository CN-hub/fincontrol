<?php
session_start();

// ==========================================
// CONFIGURAÇÃO DO SISTEMA
// ==========================================
define('SISTEMA_VERSAO', '1.2');
define('SISTEMA_NOME', 'FinControl');

// ==========================================
// CONEXÃO COM O BANCO
// ==========================================
$host = 'localhost'; $db = 'fincontrol'; $user = 'root'; $pass = '';
try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Erro de conexão com o banco. Verifique o PHPMyAdmin. Detalhes: " . $e->getMessage());
}

// Bootstrap: Cria usuário Dev se tabela vazia
try {
    $total = $pdo->query("SELECT COUNT(*) FROM usuarios")->fetchColumn();
    if ($total == 0) {
        $hash = password_hash('123456', PASSWORD_DEFAULT);
        $pdo->prepare("INSERT INTO usuarios (username, password, nome_completo, email) VALUES (?, ?, ?, ?)")
            ->execute(['Dev', $hash, 'Desenvolvedor', 'dev@fincontrol.com']);
    }
} catch (Exception $e) { }

// ==========================================
// FERIADOS BRASILEIROS
// ==========================================
function calcularPascoa($ano) {
    $a = $ano % 19; $b = floor($ano / 100); $c = $ano % 100;
    $d = floor($b / 4); $e = $b % 4; $f = floor(($b + 8) / 25);
    $g = floor(($b - $f + 1) / 3); $h = (19 * $a + $b - $d - $g + 15) % 30;
    $i = floor($c / 4); $k = $c % 4;
    $l = (32 + 2 * $e + 2 * $i - $h - $k) % 7;
    $m = floor(($a + 11 * $h + 22 * $l) / 451);
    $mes = floor(($h + $l - 7 * $m + 114) / 31);
    $dia = (($h + $l - 7 * $m + 114) % 31) + 1;
    return sprintf('%04d-%02d-%02d', $ano, $mes, $dia);
}
function getFeriados($ano) {
    $tsPascoa = strtotime(calcularPascoa($ano));
    $fixos = [
        "$ano-01-01" => "Confraternização Universal", "$ano-04-21" => "Tiradentes",
        "$ano-05-01" => "Dia do Trabalho", "$ano-09-07" => "Independência do Brasil",
        "$ano-10-12" => "Nossa Senhora Aparecida", "$ano-11-02" => "Finados",
        "$ano-11-15" => "Proclamação da República", "$ano-11-20" => "Consciência Negra",
        "$ano-12-25" => "Natal",
    ];
    $moveis = [
        date('Y-m-d', strtotime('-48 days', $tsPascoa)) => "Carnaval (Segunda)",
        date('Y-m-d', strtotime('-47 days', $tsPascoa)) => "Carnaval (Terça)",
        date('Y-m-d', strtotime('-46 days', $tsPascoa)) => "Quarta-feira de Cinzas",
        date('Y-m-d', strtotime('-2 days', $tsPascoa))  => "Sexta-feira Santa",
        date('Y-m-d', strtotime('+60 days', $tsPascoa)) => "Corpus Christi",
    ];
    return array_merge($fixos, $moveis);
}
function getDatasComemorativas($ano) {
    return [
        "$ano-03-08" => "Dia Internacional da Mulher", "$ano-03-15" => "Dia do Consumidor",
        "$ano-04-01" => "Dia da Mentira", "$ano-04-22" => "Descobrimento do Brasil",
        "$ano-05-11" => "Dia das Mães", "$ano-06-12" => "Dia dos Namorados",
        "$ano-07-20" => "Dia do Amigo", "$ano-08-11" => "Dia do Estudante",
        "$ano-08-22" => "Dia do Folclore", "$ano-09-21" => "Dia da Árvore",
        "$ano-10-15" => "Dia do Professor", "$ano-10-31" => "Halloween",
        "$ano-11-19" => "Dia da Bandeira", "$ano-12-24" => "Véspera de Natal",
        "$ano-12-31" => "Véspera de Ano Novo",
    ];
}

// ==========================================
// API
// ==========================================
if (isset($_GET['action'])) {
    $action = $_GET['action'];
    $method = $_SERVER['REQUEST_METHOD'];

    if ($action === 'version') {
        header("Content-Type: application/json");
        echo json_encode(["versao" => SISTEMA_VERSAO, "nome" => SISTEMA_NOME]);
        exit;
    }

    if ($action === 'login' && $method === 'POST') {
        header("Content-Type: application/json");
        $data = json_decode(file_get_contents("php://input"), true);
        $username = trim($data['username'] ?? '');
        $password = $data['password'] ?? '';
        $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE LOWER(username) = LOWER(?) LIMIT 1");
        $stmt->execute([$username]);
        $u = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($u && password_verify($password, $u['password'])) {
            $_SESSION['user'] = $u['username'];
            $_SESSION['user_id'] = $u['id'];
            echo json_encode(["success" => true, "username" => $u['username']]);
        } else {
            echo json_encode(["error" => "Usuário ou senha incorretos."]);
        }
        exit;
    }

    if ($action === 'register' && $method === 'POST') {
        header("Content-Type: application/json");
        $data = json_decode(file_get_contents("php://input"), true);
        $username = trim($data['username'] ?? '');
        $password = $data['password'] ?? '';
        $nome = trim($data['nome'] ?? '');
        $email = trim($data['email'] ?? '');
        if (strlen($username) < 3) { echo json_encode(["error" => "Usuário deve ter 3+ caracteres."]); exit; }
        if (strlen($password) < 6) { echo json_encode(["error" => "Senha deve ter 6+ caracteres."]); exit; }
        $stmt = $pdo->prepare("SELECT id FROM usuarios WHERE LOWER(username) = LOWER(?)");
        $stmt->execute([$username]);
        if ($stmt->fetch()) { echo json_encode(["error" => "Usuário já cadastrado."]); exit; }
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("INSERT INTO usuarios (username, password, nome_completo, email) VALUES (?, ?, ?, ?)");
        echo json_encode($stmt->execute([$username, $hash, $nome, $email]) ? ["success" => true] : ["error" => "Erro ao cadastrar."]);
        exit;
    }

    if ($action === 'logout') { session_destroy(); header("Location: index.php"); exit; }

    if (!isset($_SESSION['user'])) {
        header("Content-Type: application/json");
        echo json_encode(["error" => "Não autenticado"]);
        exit;
    }

    if ($method === 'GET' && $action === 'export_current') {
        $bkp = [];
        foreach (['contas_parceladas','usuarios'] as $t) { $bkp[$t] = $pdo->query("SELECT * FROM $t")->fetchAll(PDO::FETCH_ASSOC); }
        $bkp['_meta'] = ['gerado_em' => date('Y-m-d H:i:s'), 'versao' => SISTEMA_VERSAO, 'sistema' => SISTEMA_NOME];
        header('Content-Type: application/json; charset=utf-8');
        header('Content-Disposition: attachment; filename="fincontrol_export_' . date('Y-m-d_H-i-s') . '.json"');
        echo json_encode($bkp, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($method === 'POST' && $action === 'import_backup') {
        header("Content-Type: application/json; charset=UTF-8");
        if (!isset($_FILES['arquivo']) || $_FILES['arquivo']['error'] !== UPLOAD_ERR_OK) {
            echo json_encode(["error" => "Nenhum arquivo enviado."]);
            exit;
        }
        $dados = json_decode(file_get_contents($_FILES['arquivo']['tmp_name']), true);
        if (!$dados || !is_array($dados)) { echo json_encode(["error" => "Arquivo JSON inválido."]); exit; }
        $modo = $_POST['modo'] ?? 'merge';
        if (!isset($dados['contas_parceladas']) && !isset($dados['usuarios'])) {
            echo json_encode(["error" => "Arquivo não contém dados válidos."]); exit;
        }
        try {
            $pdo->beginTransaction();
            $totalImportado = 0;
            if (isset($dados['contas_parceladas']) && is_array($dados['contas_parceladas'])) {
                if ($modo === 'replace') { $pdo->exec("DELETE FROM contas_parceladas"); }
                foreach ($dados['contas_parceladas'] as $item) {
                    $check = $pdo->prepare("SELECT id FROM contas_parceladas WHERE descricao = ? AND valor_total = ? LIMIT 1");
                    $check->execute([$item['descricao'] ?? '', $item['valor_total'] ?? 0]);
                    if ($check->fetch()) continue;
                    $stmt = $pdo->prepare("INSERT INTO contas_parceladas (descricao, valor_total, num_parcelas, parcelas_pagas, valor_parcela, proximo_vencimento, status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                    $stmt->execute([
                        $item['descricao'] ?? 'Sem descrição', $item['valor_total'] ?? 0,
                        $item['num_parcelas'] ?? 1, $item['parcelas_pagas'] ?? 0,
                        $item['valor_parcela'] ?? 0, $item['proximo_vencimento'] ?? null,
                        $item['status'] ?? 'Ativo', $item['created_at'] ?? date('Y-m-d H:i:s')
                    ]);
                    $totalImportado++;
                }
            }
            if (isset($dados['usuarios']) && is_array($dados['usuarios'])) {
                foreach ($dados['usuarios'] as $item) {
                    $username = $item['username'] ?? '';
                    if (empty($username)) continue;
                    $check = $pdo->prepare("SELECT id FROM usuarios WHERE LOWER(username) = LOWER(?) LIMIT 1");
                    $check->execute([$username]);
                    if ($check->fetch()) continue;
                    $stmt = $pdo->prepare("INSERT INTO usuarios (username, password, nome_completo, email, created_at) VALUES (?, ?, ?, ?, ?)");
                    $stmt->execute([
                        $username, $item['password'] ?? password_hash('123456', PASSWORD_DEFAULT),
                        $item['nome_completo'] ?? null, $item['email'] ?? null,
                        $item['created_at'] ?? date('Y-m-d H:i:s')
                    ]);
                }
            }
            $pdo->commit();
            echo json_encode(["success" => true, "importados" => $totalImportado, "modo" => $modo]);
        } catch (Exception $e) {
            $pdo->rollBack();
            echo json_encode(["error" => "Erro ao importar: " . $e->getMessage()]);
        }
        exit;
    }

    header("Content-Type: application/json; charset=UTF-8");

    if ($method === 'GET' && $action === 'get_installments') {
        echo json_encode($pdo->query("SELECT * FROM contas_parceladas ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC)); exit;
    }
    if ($method === 'POST' && $action === 'add_installment') {
        $d = json_decode(file_get_contents("php://input"), true);
        $vt = floatval($d['valorTotal']); $np = intval($d['numParcelas']);
        if ($np <= 0) { echo json_encode(["error" => "Nº de parcelas inválido."]); exit; }
        $vp = $vt / $np; $pv = date('Y-m-d', strtotime('+1 month'));
        $stmt = $pdo->prepare("INSERT INTO contas_parceladas (descricao, valor_total, num_parcelas, parcelas_pagas, valor_parcela, proximo_vencimento, status) VALUES (?, ?, ?, 0, ?, ?, 'Ativo')");
        $stmt->execute([$d['descricao'], $vt, $np, $vp, $pv]);
        echo json_encode(["success" => true]); exit;
    }
    if ($method === 'POST' && $action === 'baixa') {
        $d = json_decode(file_get_contents("php://input"), true);
        $id = intval($d['id']);
        $stmt = $pdo->prepare("SELECT * FROM contas_parceladas WHERE id = ?"); $stmt->execute([$id]);
        $i = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($i) {
            $np = $i['parcelas_pagas'] + 1;
            $ns = ($np >= $i['num_parcelas']) ? 'Concluído' : 'Ativo';
            $pv = ($ns === 'Concluído') ? null : date('Y-m-d', strtotime($i['proximo_vencimento'] . ' +1 month'));
            $pdo->prepare("UPDATE contas_parceladas SET parcelas_pagas = ?, status = ?, proximo_vencimento = ? WHERE id = ?")->execute([$np, $ns, $pv, $id]);
            echo json_encode(["success" => true]);
        } else { echo json_encode(["error" => "Item não encontrado"]); }
        exit;
    }
    if ($method === 'GET' && $action === 'get_calendar_events') {
        $ano = intval($_GET['ano'] ?? date('Y')); $mes = intval($_GET['mes'] ?? date('n'));
        $prefixo = sprintf('%04d-%02d-', $ano, $mes);
        $f = array_filter(getFeriados($ano), function($k) use ($prefixo) { return strpos($k, $prefixo) === 0; }, ARRAY_FILTER_USE_KEY);
        $c = array_filter(getDatasComemorativas($ano), function($k) use ($prefixo) { return strpos($k, $prefixo) === 0; }, ARRAY_FILTER_USE_KEY);
        $stmt = $pdo->prepare("SELECT id, descricao, valor_parcela, proximo_vencimento, status FROM contas_parceladas WHERE proximo_vencimento LIKE ? AND status != 'Concluído'");
        $stmt->execute([$prefixo . '%']);
        echo json_encode(["feriados" => $f, "comemorativas" => $c, "parcelas" => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
        exit;
    }
    if ($method === 'GET' && $action === 'export') {
        $formato = $_GET['formato'] ?? 'csv';
        $dados = $pdo->query("SELECT id, descricao, valor_total, num_parcelas, parcelas_pagas, valor_parcela, proximo_vencimento, status, created_at FROM contas_parceladas ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);
        if ($formato === 'json') {
            header("Content-Type: application/json"); header("Content-Disposition: attachment; filename=relatorio_financeiro.json");
            echo json_encode($dados, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE); exit;
        }
        if ($formato === 'csv') {
            header("Content-Type: text/csv; charset=utf-8"); header("Content-Disposition: attachment; filename=relatorio_financeiro.csv");
            $o = fopen("php://output", "w"); fputs($o, "\xEF\xBB\xBF");
            fputcsv($o, ['ID','Descrição','Valor Total','Nº Parcelas','Pagas','Valor Parcela','Vencimento','Status','Criado em'], ';');
            foreach ($dados as $r) { fputcsv($o, $r, ';'); } fclose($o); exit;
        }
        if ($formato === 'excel') {
            header("Content-Type: application/vnd.ms-excel; charset=utf-8"); header("Content-Disposition: attachment; filename=relatorio_financeiro.xls");
            echo "\xEF\xBB\xBF<table border='1'><tr style='background:#7ECFC5;color:#fff;'><th>ID</th><th>Descrição</th><th>Valor Total</th><th>Parcelas</th><th>Pagas</th><th>Valor Parcela</th><th>Vencimento</th><th>Status</th></tr>";
            foreach ($dados as $r) { echo "<tr>"; foreach ($r as $c) { echo "<td>" . htmlspecialchars($c ?? '') . "</td>"; } echo "</tr>"; }
            echo "</table>"; exit;
        }
        if ($formato === 'pdf') {
            header("Content-Type: text/html; charset=utf-8");
            echo "<html><head><title>Relatório</title><style>body{font-family:Arial;padding:20px}h1{color:#7ECFC5}table{width:100%;border-collapse:collapse}th{background:#7ECFC5;color:#fff;padding:10px;text-align:left}td{padding:8px;border-bottom:1px solid #ddd}tr:nth-child(even){background:#F5F6FA}@media print{button{display:none}}</style></head><body><button onclick='window.print()' style='padding:10px 20px;background:#7ECFC5;color:#fff;border:none;border-radius:8px;cursor:pointer;margin-bottom:20px'>🖨️ Imprimir/Salvar PDF</button><h1>Relatório Financeiro - FinControl v" . SISTEMA_VERSAO . "</h1><p>Gerado em: " . date('d/m/Y H:i') . "</p><table><tr><th>ID</th><th>Descrição</th><th>Valor Total</th><th>Parcelas</th><th>Pagas</th><th>Valor Parcela</th><th>Vencimento</th><th>Status</th></tr>";
            foreach ($dados as $r) { echo "<tr><td>{$r['id']}</td><td>" . htmlspecialchars($r['descricao']) . "</td><td>R$ " . number_format($r['valor_total'], 2, ',', '.') . "</td><td>{$r['num_parcelas']}</td><td>{$r['parcelas_pagas']}</td><td>R$ " . number_format($r['valor_parcela'], 2, ',', '.') . "</td><td>" . ($r['proximo_vencimento'] ?? '-') . "</td><td>{$r['status']}</td></tr>"; }
            echo "</table></body></html>"; exit;
        }
    }
    if ($method === 'GET' && $action === 'list_backups') {
        echo json_encode($pdo->query("SELECT * FROM backups ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC)); exit;
    }
    if ($method === 'POST' && $action === 'create_backup') {
        $d = json_decode(file_get_contents("php://input"), true);
        $tipo = $d['tipo'] ?? 'Manual';
        $dir = __DIR__ . '/backups/'; if (!is_dir($dir)) { mkdir($dir, 0777, true); }
        $bkp = []; foreach (['contas_parceladas','usuarios'] as $t) { $bkp[$t] = $pdo->query("SELECT * FROM $t")->fetchAll(PDO::FETCH_ASSOC); }
        $nome = 'backup_' . date('Y-m-d_H-i-s') . '_' . strtolower($tipo) . '.json';
        file_put_contents($dir . $nome, json_encode($bkp, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        $tam = round(filesize($dir . $nome) / 1024, 2) . ' KB';
        $pdo->prepare("INSERT INTO backups (nome_arquivo, tamanho, tipo) VALUES (?, ?, ?)")->execute([$nome, $tam, $tipo]);
        echo json_encode(["success" => true, "nome" => $nome, "tamanho" => $tam]); exit;
    }
    if ($method === 'POST' && $action === 'delete_backup') {
        $d = json_decode(file_get_contents("php://input"), true); $id = intval($d['id']);
        $stmt = $pdo->prepare("SELECT nome_arquivo FROM backups WHERE id = ?"); $stmt->execute([$id]);
        $b = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($b) { $f = __DIR__ . '/backups/' . $b['nome_arquivo']; if (file_exists($f)) { unlink($f); } $pdo->prepare("DELETE FROM backups WHERE id = ?")->execute([$id]); echo json_encode(["success" => true]); }
        exit;
    }
    if ($method === 'GET' && $action === 'download_backup') {
        $id = intval($_GET['id']);
        $stmt = $pdo->prepare("SELECT nome_arquivo FROM backups WHERE id = ?"); $stmt->execute([$id]);
        $b = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($b) { $f = __DIR__ . '/backups/' . $b['nome_arquivo']; if (file_exists($f)) { header('Content-Type: application/octet-stream'); header('Content-Disposition: attachment; filename="' . basename($f) . '"'); header('Content-Length: ' . filesize($f)); readfile($f); exit; } }
        exit;
    }
    if ($method === 'GET' && $action === 'check_auto_backup') {
        $ultimo = $pdo->query("SELECT created_at FROM backups WHERE tipo = 'Automático' ORDER BY id DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
        $precisa = true;
        if ($ultimo && (time() - strtotime($ultimo['created_at'])) < 3600) { $precisa = false; }
        if ($precisa) {
            $dir = __DIR__ . '/backups/'; if (!is_dir($dir)) { mkdir($dir, 0777, true); }
            $bkp = []; foreach (['contas_parceladas','usuarios'] as $t) { $bkp[$t] = $pdo->query("SELECT * FROM $t")->fetchAll(PDO::FETCH_ASSOC); }
            $nome = 'backup_' . date('Y-m-d_H-i-s') . '_automatico.json';
            file_put_contents($dir . $nome, json_encode($bkp, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            $tam = round(filesize($dir . $nome) / 1024, 2) . ' KB';
            $pdo->prepare("INSERT INTO backups (nome_arquivo, tamanho, tipo) VALUES (?, ?, 'Automático')")->execute([$nome, $tam]);
            echo json_encode(["success" => true, "message" => "Backup automático criado."]);
        } else { echo json_encode(["success" => true, "message" => "Backup recente."]); }
        exit;
    }
    echo json_encode(["error" => "Ação não encontrada."]); exit;
}
?>
<!DOCTYPE html>
<html lang="pt-BR" class="dark">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>FinControl v<?= SISTEMA_VERSAO ?> - Sistema Financeiro</title>
<script src="https://cdn.tailwindcss.com"></script>
<script>
tailwind.config = {
    darkMode: 'class',
    theme: {
        extend: {
            colors: {
                // Paleta Suave - Tema Escuro
                softdark: {
                    bg: '#1C1F2E',
                    card: '#252A3D',
                    border: '#353B54',
                    hover: '#2E3450',
                    text: '#E4E6EF',
                    muted: '#9BA3B8'
                },
                // Paleta Suave - Tema Claro
                softlight: {
                    bg: '#F5F6FA',
                    card: '#FFFFFF',
                    border: '#E5E8F0',
                    hover: '#F0F2F8',
                    text: '#2E3440',
                    muted: '#7A8095'
                },
                // Cores de Destaque Suaves
                accent: {
                    teal: '#7ECFC5',
                    lavender: '#B9A8E8',
                    sage: '#A8D5BA',
                    peach: '#F4B7A8',
                    amber: '#F5CF8B',
                    rose: '#F4A8A8',
                    sky: '#A8C8E8'
                }
            }
        }
    }
}
</script>
<script crossorigin src="https://unpkg.com/react@18/umd/react.production.min.js"></script>
<script crossorigin src="https://unpkg.com/react-dom@18/umd/react-dom.production.min.js"></script>
<script src="https://unpkg.com/@babel/standalone/babel.min.js"></script>
<style>
/* Botão 3D com cores suaves */
.toggle-3d-dark {
    background: linear-gradient(135deg, #353B54, #1C1F2E);
    box-shadow: inset -4px -4px 8px rgba(255,255,255,0.05), inset 4px 4px 8px rgba(0,0,0,0.5), 0 8px 16px rgba(0,0,0,0.3);
}
.toggle-3d-light {
    background: linear-gradient(135deg, #F5F6FA, #E5E8F0);
    box-shadow: inset -4px -4px 8px rgba(255,255,255,0.9), inset 4px 4px 8px rgba(0,0,0,0.08), 0 8px 16px rgba(0,0,0,0.08);
}
.toggle-inner-dark {
    background: linear-gradient(135deg, #B9A8E8, #7ECFC5);
    box-shadow: 0 0 12px rgba(185,168,232,0.5);
}
.toggle-inner-light {
    background: linear-gradient(135deg, #F5CF8B, #F4B7A8);
    box-shadow: 0 0 12px rgba(245,207,139,0.5);
}
/* Scrollbar suave */
::-webkit-scrollbar { width: 8px; }
::-webkit-scrollbar-track { background: transparent; }
::-webkit-scrollbar-thumb { background: #353B54; border-radius: 4px; }
::-webkit-scrollbar-thumb:hover { background: #4A5170; }
/* Animações */
.animate-in { animation: fadeIn .3s ease-in-out; }
@keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
.drop-zone { border: 2px dashed #4A5170; transition: all .3s; }
.drop-zone.active { border-color: #7ECFC5; background: rgba(126,207,197,.08); }
/* Body base */
body { background-color: #F5F6FA; color: #2E3440; transition: background-color .3s, color .3s; }
html.dark body { background-color: #1C1F2E; color: #E4E6EF; }
</style>
</head>
<body>
<div id="root"></div>
<script type="text/babel">
const { useState, useEffect, useRef } = React;
const API = 'index.php';
const VERSAO = '<?= SISTEMA_VERSAO ?>';

// ============================================================
// ÍCONES
// ============================================================
const Icon = ({ name, className }) => {
    const I = {
        dashboard: <path d="M3 3h7v9H3zm0 11h7v7H3zm11-11h7v5h-7zm0 7h7v9h-7z"/>,
        calendar: <path d="M8 2v4M16 2v4M3 10h18M5 4h14a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2z"/>,
        wallet: <path d="M21 12V7H5a2 2 0 0 1 0-4h14v4M3 5v14a2 2 0 0 0 2 2h16v-5M18 12a2 2 0 0 0 0 4h4v-4Z"/>,
        settings: <><path d="M12.22 2h-.44a2 2 0 0 0-2 2v.18a2 2 0 0 1-1 1.73l-.43.25a2 2 0 0 1-2 0l-.15-.08a2 2 0 0 0-2.73.73l-.22.38a2 2 0 0 0 .73 2.73l.15.1a2 2 0 0 1 1 1.72v.51a2 2 0 0 1-1 1.74l-.15.09a2 2 0 0 0-.73 2.73l.22.38a2 2 0 0 0 2.73.73l.15-.08a2 2 0 0 1 2 0l.43.25a2 2 0 0 1 1 1.73V20a2 2 0 0 0 2 2h.44a2 2 0 0 0 2-2v-.18a2 2 0 0 1 1-1.73l.43-.25a2 2 0 0 1 2 0l.15.08a2 2 0 0 0 2.73-.73l.22-.39a2 2 0 0 0-.73-2.73l-.15-.08a2 2 0 0 1-1-1.74v-.5a2 2 0 0 1 1-1.74l.15-.09a2 2 0 0 0 .73-2.73l-.22-.38a2 2 0 0 0-2.73-.73l-.15.08a2 2 0 0 1-2 0l-.43-.25a2 2 0 0 1-1-1.73V4a2 2 0 0 0-2-2z"/><circle cx="12" cy="12" r="3"/></>,
        plus: <path d="M5 12h14M12 5v14"/>,
        x: <path d="M18 6 6 18M6 6l12 12"/>,
        trendingUp: <path d="M22 7 13.5 15.5 8.5 10.5 2 17"/>,
        dollar: <><line x1="12" y1="2" x2="12" y2="22"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></>,
        fileText: <><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></>,
        database: <><ellipse cx="12" cy="5" rx="9" ry="3"/><path d="M21 12c0 1.66-4 3-9 3s-9-1.34-9-3"/><path d="M3 5v14c0 1.66 4 3 9 3s9-1.34 9-3V5"/></>,
        menu: <><line x1="4" x2="20" y1="12" y2="12"/><line x1="4" x2="20" y1="6" y2="6"/><line x1="4" x2="20" y1="18" y2="18"/></>,
        download: <><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" x2="12" y1="15" y2="3"/></>,
        upload: <><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" x2="12" y1="3" y2="15"/></>,
        trash: <><path d="M3 6h18"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6"/><path d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></>,
        logout: <><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" x2="9" y1="12" y2="12"/></>,
        lock: <><rect width="18" height="11" x="3" y="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></>,
        user: <><path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></>,
        file: <><path d="M14.5 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7.5L14.5 2z"/><polyline points="14 2 14 8 20 8"/></>,
        refresh: <><path d="M3 12a9 9 0 0 1 9-9 9.75 9.75 0 0 1 6.74 2.74L21 8"/><path d="M21 3v5h-5"/><path d="M21 12a9 9 0 0 1-9 9 9.75 9.75 0 0 1-6.74-2.74L3 16"/><path d="M8 16H3v5"/></>,
        chevronLeft: <polyline points="15 18 9 12 15 6"/>,
        chevronRight: <polyline points="9 18 15 12 9 6"/>,
        gift: <><rect x="3" y="8" width="18" height="4" rx="1"/><path d="M12 8v13"/><path d="M19 12v7a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2v-7"/><path d="M7.5 8a2.5 2.5 0 0 1 0-5A4.8 8 0 0 1 12 8a4.8 8 0 0 1 4.5-5 2.5 2.5 0 0 1 0 5"/></>,
        info: <><circle cx="12" cy="12" r="10"/><path d="M12 16v-4"/><path d="M12 8h.01"/></>,
        mail: <><rect width="20" height="16" x="2" y="4" rx="2"/><path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"/></>,
        check: <polyline points="20 6 9 17 4 12"/>,
        tag: <><path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"/><line x1="7" y1="7" x2="7.01" y2="7"/></>
    };
    return <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" className={className}>{I[name]}</svg>;
};

// ============================================================
// TOGGLE DE TEMA 3D
// ============================================================
const Theme3D = ({ isDark, toggle }) => (
    <button onClick={toggle} aria-label={isDark ? "Tema claro" : "Tema escuro"}
        className={`relative w-14 h-14 rounded-full flex items-center justify-center transition-all duration-500 focus:outline-none focus:ring-2 focus:ring-accent-teal ${isDark ? 'toggle-3d-dark' : 'toggle-3d-light'}`}>
        <div className={`w-6 h-6 rounded-full transition-all duration-500 ${isDark ? 'toggle-inner-dark' : 'toggle-inner-light'}`} />
    </button>
);

// ============================================================
// MODAL
// ============================================================
const Modal = ({ isOpen, onClose, title, children, isDark }) => {
    if (!isOpen) return null;
    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm p-4" role="dialog" aria-modal="true">
            <div className={`${isDark ? 'bg-softdark-card border-softdark-border' : 'bg-softlight-card border-softlight-border'} border rounded-2xl w-full max-w-lg p-6 shadow-2xl animate-in`}>
                <div className={`flex justify-between items-center mb-6 border-b pb-4 ${isDark ? 'border-softdark-border' : 'border-softlight-border'}`}>
                    <h2 className="text-xl font-bold">{title}</h2>
                    <button onClick={onClose} aria-label="Fechar" className={`${isDark ? 'text-softdark-muted' : 'text-softlight-muted'} hover:opacity-70 transition-opacity`}><Icon name="x" className="w-6 h-6"/></button>
                </div>
                {children}
            </div>
        </div>
    );
};

// ============================================================
// TELA DE LOGIN
// ============================================================
function LoginScreen({ onLogin, isDark, toggleTheme }) {
    const [modo, setModo] = useState('login');
    const [f, setF] = useState({ username:'', password:'', confirmPassword:'', nome:'', email:'' });
    const [error, setError] = useState('');
    const [success, setSuccess] = useState('');
    const [loading, setLoading] = useState(false);

    const reset = () => { setF({ username:'', password:'', confirmPassword:'', nome:'', email:'' }); setError(''); setSuccess(''); };
    const upd = (k) => (e) => setF({ ...f, [k]: e.target.value });

    const handleLogin = async (e) => {
        e.preventDefault(); setLoading(true); setError(''); setSuccess('');
        try {
            const r = await fetch(`${API}?action=login`, { method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify({ username: f.username, password: f.password }) });
            const d = await r.json();
            if (d.success) onLogin(); else setError(d.error || 'Falha.');
        } catch { setError('Erro de conexão.'); }
        setLoading(false);
    };

    const handleRegister = async (e) => {
        e.preventDefault(); setError(''); setSuccess('');
        if (f.password !== f.confirmPassword) { setError('As senhas não coincidem.'); return; }
        if (f.password.length < 6) { setError('Senha deve ter 6+ caracteres.'); return; }
        if (f.username.length < 3) { setError('Usuário deve ter 3+ caracteres.'); return; }
        setLoading(true);
        try {
            const r = await fetch(`${API}?action=register`, { method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify({ username: f.username, password: f.password, nome: f.nome, email: f.email }) });
            const d = await r.json();
            if (d.success) { setSuccess('Conta criada! Faça login.'); setTimeout(() => { setModo('login'); reset(); }, 2000); }
            else setError(d.error || 'Erro ao cadastrar.');
        } catch { setError('Erro de conexão.'); }
        setLoading(false);
    };

    const bgClass = isDark ? 'bg-gradient-to-br from-[#1C1F2E] via-[#252A3D] to-[#1C1F2E]' : 'bg-gradient-to-br from-[#F5F6FA] via-white to-[#E5E8F0]';
    const cardBg = isDark ? 'bg-[#252A3D]/90 border-[#353B54]' : 'bg-white/90 border-[#E5E8F0]';
    const inputBg = isDark ? 'bg-[#1C1F2E] border-[#353B54] text-[#E4E6EF]' : 'bg-[#F5F6FA] border-[#E5E8F0] text-[#2E3440]';
    const txtMuted = isDark ? 'text-[#9BA3B8]' : 'text-[#7A8095]';
    const tabBg = isDark ? 'bg-[#1C1F2E]/60' : 'bg-[#F0F2F8]';

    return (
        <div className={`min-h-screen flex items-center justify-center p-4 ${bgClass} transition-colors duration-300`}>
            {/* Toggle tema no canto superior direito */}
            <div className="fixed top-4 right-4 z-10">
                <Theme3D isDark={isDark} toggle={toggleTheme}/>
            </div>

            <div className={`w-full max-w-md p-8 rounded-3xl backdrop-blur-xl border shadow-2xl animate-in ${cardBg}`}>
                <div className="flex justify-center mb-6">
                    <div className="p-4 bg-gradient-to-br from-accent-teal to-accent-lavender rounded-2xl shadow-lg shadow-accent-teal/20">
                        <Icon name="wallet" className="w-10 h-10 text-white"/>
                    </div>
                </div>
                <h1 className={`text-2xl font-bold text-center mb-1 ${isDark ? 'text-[#E4E6EF]' : 'text-[#2E3440]'}`}>FinControl</h1>
                <div className="flex items-center justify-center gap-2 mb-6">
                    <span className={`text-xs px-2 py-0.5 rounded-full border ${isDark ? 'border-[#353B54] text-[#9BA3B8] bg-[#1C1F2E]/60' : 'border-[#E5E8F0] text-[#7A8095] bg-[#F5F6FA]'}`}>
                        <Icon name="tag" className="w-3 h-3 inline mr-1"/>v{VERSAO}
                    </span>
                </div>
                <p className={`text-center text-sm mb-6 ${txtMuted}`}>{modo === 'login' ? 'Acesse sua conta' : 'Crie sua conta'}</p>

                <div className={`flex rounded-xl p-1 mb-6 ${tabBg}`}>
                    <button type="button" onClick={() => { setModo('login'); reset(); }}
                        className={`flex-1 py-2 rounded-lg text-sm font-bold transition-all ${modo === 'login' ? 'bg-gradient-to-r from-accent-teal to-accent-lavender text-white shadow-md' : txtMuted}`}>Entrar</button>
                    <button type="button" onClick={() => { setModo('register'); reset(); }}
                        className={`flex-1 py-2 rounded-lg text-sm font-bold transition-all ${modo === 'register' ? 'bg-gradient-to-r from-accent-teal to-accent-lavender text-white shadow-md' : txtMuted}`}>Cadastrar</button>
                </div>

                {error && <div className="mb-4 p-3 bg-accent-rose/15 border border-accent-rose/30 rounded-lg text-accent-rose text-sm text-center">{error}</div>}
                {success && <div className="mb-4 p-3 bg-accent-sage/15 border border-accent-sage/30 rounded-lg text-accent-sage text-sm text-center">{success}</div>}

                <form onSubmit={modo === 'login' ? handleLogin : handleRegister} className="space-y-4">
                    {modo === 'register' && (
                        <div>
                            <label className={`block text-sm font-medium mb-2 ${isDark ? 'text-[#E4E6EF]' : 'text-[#2E3440]'}`}>Nome Completo</label>
                            <div className="relative">
                                <Icon name="user" className={`w-5 h-5 absolute left-3 top-1/2 -translate-y-1/2 ${txtMuted}`}/>
                                <input type="text" value={f.nome} onChange={upd('nome')} placeholder="Seu nome" required
                                    className={`w-full pl-11 pr-4 py-3 border rounded-xl outline-none focus:border-accent-teal transition-colors ${inputBg}`}/>
                            </div>
                        </div>
                    )}
                    <div>
                        <label className={`block text-sm font-medium mb-2 ${isDark ? 'text-[#E4E6EF]' : 'text-[#2E3440]'}`}>Usuário</label>
                        <div className="relative">
                            <Icon name="user" className={`w-5 h-5 absolute left-3 top-1/2 -translate-y-1/2 ${txtMuted}`}/>
                            <input type="text" value={f.username} onChange={upd('username')} placeholder="Escolha um usuário" required
                                className={`w-full pl-11 pr-4 py-3 border rounded-xl outline-none focus:border-accent-teal transition-colors ${inputBg}`}/>
                        </div>
                    </div>
                    {modo === 'register' && (
                        <div>
                            <label className={`block text-sm font-medium mb-2 ${isDark ? 'text-[#E4E6EF]' : 'text-[#2E3440]'}`}>E-mail</label>
                            <div className="relative">
                                <Icon name="mail" className={`w-5 h-5 absolute left-3 top-1/2 -translate-y-1/2 ${txtMuted}`}/>
                                <input type="email" value={f.email} onChange={upd('email')} placeholder="seu@email.com"
                                    className={`w-full pl-11 pr-4 py-3 border rounded-xl outline-none focus:border-accent-teal transition-colors ${inputBg}`}/>
                            </div>
                        </div>
                    )}
                    <div>
                        <label className={`block text-sm font-medium mb-2 ${isDark ? 'text-[#E4E6EF]' : 'text-[#2E3440]'}`}>Senha</label>
                        <div className="relative">
                            <Icon name="lock" className={`w-5 h-5 absolute left-3 top-1/2 -translate-y-1/2 ${txtMuted}`}/>
                            <input type="password" value={f.password} onChange={upd('password')} placeholder="Mínimo 6 caracteres" required
                                className={`w-full pl-11 pr-4 py-3 border rounded-xl outline-none focus:border-accent-teal transition-colors ${inputBg}`}/>
                        </div>
                    </div>
                    {modo === 'register' && (
                        <div>
                            <label className={`block text-sm font-medium mb-2 ${isDark ? 'text-[#E4E6EF]' : 'text-[#2E3440]'}`}>Confirmar Senha</label>
                            <div className="relative">
                                <Icon name="lock" className={`w-5 h-5 absolute left-3 top-1/2 -translate-y-1/2 ${txtMuted}`}/>
                                <input type="password" value={f.confirmPassword} onChange={upd('confirmPassword')} placeholder="Repita a senha" required
                                    className={`w-full pl-11 pr-4 py-3 border rounded-xl outline-none focus:border-accent-teal transition-colors ${inputBg}`}/>
                            </div>
                        </div>
                    )}
                    <button type="submit" disabled={loading}
                        className="w-full py-3 bg-gradient-to-r from-accent-teal to-accent-lavender text-white font-bold rounded-xl hover:opacity-90 shadow-lg shadow-accent-teal/20 disabled:opacity-50 transition-all">
                        {loading ? 'Aguarde...' : (modo === 'login' ? 'Entrar' : 'Criar Conta')}
                    </button>
                </form>
            </div>
        </div>
    );
}

// ============================================================
// CALENDÁRIO
// ============================================================
function CalendarView({ isDark }) {
    const [dt, setDt] = useState(new Date());
    const [ev, setEv] = useState({ feriados:{}, comemorativas:{}, parcelas:[] });
    const [loading, setLoading] = useState(false);
    const [sel, setSel] = useState(null);
    const ano = dt.getFullYear(); const mes = dt.getMonth() + 1;
    const meses = ['Janeiro','Fevereiro','Março','Abril','Maio','Junho','Julho','Agosto','Setembro','Outubro','Novembro','Dezembro'];
    const sem = ['Dom','Seg','Ter','Qua','Qui','Sex','Sáb'];

    useEffect(() => {
        setLoading(true);
        fetch(`${API}?action=get_calendar_events&ano=${ano}&mes=${mes}`)
            .then(r => r.json()).then(d => { setEv(d); setLoading(false); }).catch(() => setLoading(false));
    }, [ano, mes]);

    const mudar = (d) => setDt(new Date(ano, mes - 1 + d, 1));
    const hoje = () => setDt(new Date());
    const primeiro = new Date(ano, mes - 1, 1).getDay();
    const total = new Date(ano, mes, 0).getDate();
    const dias = [];
    for (let i = 0; i < primeiro; i++) dias.push(null);
    for (let i = 1; i <= total; i++) dias.push(i);
    const hj = new Date();
    const isHoje = (d) => d === hj.getDate() && mes === (hj.getMonth() + 1) && ano === hj.getFullYear();
    const getEv = (dia) => {
        if (!dia) return { feriado:null, comemorativa:null, parcelas:[] };
        const k = `${ano}-${String(mes).padStart(2,'0')}-${String(dia).padStart(2,'0')}`;
        return { feriado: ev.feriados?.[k] || null, comemorativa: ev.comemorativas?.[k] || null, parcelas: (ev.parcelas || []).filter(p => p.proximo_vencimento === k) };
    };
    const evSel = sel ? getEv(sel) : null;

    const cardBg = isDark ? 'bg-softdark-card border-softdark-border' : 'bg-softlight-card border-softlight-border shadow-sm';
    const txtMut = isDark ? 'text-softdark-muted' : 'text-softlight-muted';
    const btnBg = isDark ? 'bg-softdark-hover text-accent-teal' : 'bg-softlight-hover text-accent-teal';

    return (
        <div className="space-y-6 animate-in">
            <div className={`p-6 rounded-2xl border ${cardBg}`}>
                <div className="flex flex-col md:flex-row md:justify-between md:items-center gap-4 mb-6">
                    <div className="flex items-center gap-3">
                        <button onClick={() => mudar(-1)} className={`p-2 rounded-lg ${btnBg} hover:opacity-80`} aria-label="Anterior"><Icon name="chevronLeft" className="w-5 h-5"/></button>
                        <h2 className="text-lg md:text-xl font-bold min-w-[180px] text-center">{meses[mes - 1]} {ano}</h2>
                        <button onClick={() => mudar(1)} className={`p-2 rounded-lg ${btnBg} hover:opacity-80`} aria-label="Próximo"><Icon name="chevronRight" className="w-5 h-5"/></button>
                        <button onClick={hoje} className="px-3 py-2 text-sm bg-accent-teal/15 text-accent-teal rounded-lg hover:bg-accent-teal/25 transition-colors">Hoje</button>
                    </div>
                    <div className="flex flex-wrap gap-3 text-xs">
                        <span className="flex items-center gap-1"><div className="w-3 h-3 rounded-full bg-accent-rose"></div> Feriado</span>
                        <span className="flex items-center gap-1"><div className="w-3 h-3 rounded-full bg-accent-lavender"></div> Comemorativa</span>
                        <span className="flex items-center gap-1"><div className="w-3 h-3 rounded-full bg-accent-amber"></div> Vencimento</span>
                    </div>
                </div>
                <div className="grid grid-cols-7 gap-1 md:gap-2 mb-2">
                    {sem.map(d => <div key={d} className={`text-center text-xs font-bold py-2 ${txtMut}`}>{d}</div>)}
                </div>
                <div className="grid grid-cols-7 gap-1 md:gap-2">
                    {dias.map((dia, i) => {
                        const e = dia ? getEv(dia) : { feriado:null, comemorativa:null, parcelas:[] };
                        const tem = e.feriado || e.comemorativa || e.parcelas.length > 0;
                        const fim = [0, 6].includes(i % 7);
                        return (
                            <button key={i} onClick={() => dia && setSel(dia)} disabled={!dia}
                                className={`min-h-[70px] md:min-h-[90px] p-1 md:p-2 rounded-xl border text-left transition-all hover:scale-[1.02] ${
                                    !dia ? 'opacity-0 cursor-default' :
                                    isHoje(dia) ? 'border-accent-teal bg-accent-teal/15 ring-2 ring-accent-teal' :
                                    e.feriado ? 'border-accent-rose/50 bg-accent-rose/10' :
                                    tem ? isDark ? 'border-softdark-border bg-softdark-hover/50' : 'border-softlight-border bg-softlight-hover' :
                                    isDark ? `border-softdark-border ${fim ? 'bg-softdark-hover/30' : 'bg-softdark-hover/10'}` : `border-softlight-border ${fim ? 'bg-softlight-hover' : 'bg-softlight-bg'}`
                                }`}>
                                <div className={`text-xs md:text-sm font-bold mb-1 ${e.feriado ? 'text-accent-rose' : isHoje(dia) ? 'text-accent-teal' : ''}`}>{dia}</div>
                                <div className="space-y-0.5 hidden md:block">
                                    {e.feriado && <div className="text-[9px] bg-accent-rose/20 text-accent-rose px-1 rounded truncate">🔴 {e.feriado.substring(0,14)}</div>}
                                    {e.comemorativa && <div className="text-[9px] bg-accent-lavender/20 text-accent-lavender px-1 rounded truncate">💜 {e.comemorativa.substring(0,14)}</div>}
                                    {e.parcelas.map((p, idx) => (
                                        <div key={idx} className="text-[9px] bg-accent-amber/20 text-accent-amber px-1 rounded truncate">💰 {p.descricao.substring(0,12)}</div>
                                    ))}
                                </div>
                                {tem && <div className="md:hidden flex gap-0.5 mt-1">
                                    {e.feriado && <div className="w-1.5 h-1.5 rounded-full bg-accent-rose"/>}
                                    {e.comemorativa && <div className="w-1.5 h-1.5 rounded-full bg-accent-lavender"/>}
                                    {e.parcelas.length > 0 && <div className="w-1.5 h-1.5 rounded-full bg-accent-amber"/>}
                                </div>}
                            </button>
                        );
                    })}
                </div>
                {loading && <p className="text-center text-accent-teal text-sm mt-4">Carregando eventos...</p>}
            </div>

            {sel && evSel && (
                <div className={`p-6 rounded-2xl border ${cardBg} animate-in`}>
                    <div className="flex justify-between items-center mb-4">
                        <h3 className="text-lg font-bold">Eventos de {String(sel).padStart(2,'0')}/{String(mes).padStart(2,'0')}/{ano}</h3>
                        <button onClick={() => setSel(null)} className={txtMut}><Icon name="x" className="w-5 h-5"/></button>
                    </div>
                    <div className="space-y-3">
                        {evSel.feriado && (
                            <div className="flex items-center gap-3 p-3 bg-accent-rose/10 border border-accent-rose/30 rounded-xl">
                                <div className="p-2 bg-accent-rose/20 text-accent-rose rounded-lg"><Icon name="info" className="w-5 h-5"/></div>
                                <div><p className="font-bold text-accent-rose">Feriado Nacional</p><p className="text-sm">{evSel.feriado}</p></div>
                            </div>
                        )}
                        {evSel.comemorativa && (
                            <div className="flex items-center gap-3 p-3 bg-accent-lavender/10 border border-accent-lavender/30 rounded-xl">
                                <div className="p-2 bg-accent-lavender/20 text-accent-lavender rounded-lg"><Icon name="gift" className="w-5 h-5"/></div>
                                <div><p className="font-bold text-accent-lavender">Data Comemorativa</p><p className="text-sm">{evSel.comemorativa}</p></div>
                            </div>
                        )}
                        {evSel.parcelas.map((p, i) => (
                            <div key={i} className="flex items-center gap-3 p-3 bg-accent-amber/10 border border-accent-amber/30 rounded-xl">
                                <div className="p-2 bg-accent-amber/20 text-accent-amber rounded-lg"><Icon name="dollar" className="w-5 h-5"/></div>
                                <div><p className="font-bold text-accent-amber">Vencimento</p><p className="text-sm">{p.descricao} - R$ {parseFloat(p.valor_parcela).toFixed(2)}</p></div>
                            </div>
                        ))}
                        {!evSel.feriado && !evSel.comemorativa && evSel.parcelas.length === 0 && (
                            <p className={`${txtMut} text-sm text-center py-4`}>Nenhum evento neste dia.</p>
                        )}
                    </div>
                </div>
            )}
        </div>
    );
}

// ============================================================
// APP PRINCIPAL
// ============================================================
function App() {
    const [auth, setAuth] = useState(false);
    const [checking, setChecking] = useState(true);
    const [isDark, setIsDark] = useState(true);
    const [tab, setTab] = useState('dashboard');
    const [sideOpen, setSideOpen] = useState(false);
    const [inst, setInst] = useState([]);
    const [bkp, setBkp] = useState([]);
    const [modal, setModal] = useState(false);
    const [loading, setLoading] = useState(true);
    const [err, setErr] = useState('');
    const [form, setForm] = useState({ descricao:'', total:'', valorTotal:'' });
    const [lastAuto, setLastAuto] = useState(null);
    const [importModal, setImportModal] = useState(false);
    const [importFile, setImportFile] = useState(null);
    const [importModo, setImportModo] = useState('merge');
    const [importLoading, setImportLoading] = useState(false);
    const [importMsg, setImportMsg] = useState(null);
    const [dragActive, setDragActive] = useState(false);
    const fileInputRef = useRef(null);

    useEffect(() => {
        fetch(`${API}?action=get_installments`).then(r => r.json())
            .then(d => setAuth(!(d.error === 'Não autenticado')))
            .catch(() => setAuth(false))
            .finally(() => setChecking(false));
    }, []);

    const loadInst = async () => {
        try {
            setLoading(true);
            const r = await fetch(`${API}?action=get_installments`);
            const d = await r.json();
            if (d.error === 'Não autenticado') { setAuth(false); return; }
            if (d.error) setErr(d.error); else { setInst(d); setErr(''); }
        } catch { setErr('Erro de conexão.'); }
        finally { setLoading(false); }
    };
    const loadBkp = async () => {
        try { const r = await fetch(`${API}?action=list_backups`); const d = await r.json(); if (!d.error) setBkp(d); } catch {}
    };

    useEffect(() => { if (auth) { loadInst(); loadBkp(); } }, [auth]);
    useEffect(() => { document.documentElement.classList.toggle('dark', isDark); }, [isDark]);

    useEffect(() => {
        if (!auth) return;
        const check = async () => {
            try {
                const r = await fetch(`${API}?action=check_auto_backup`);
                const d = await r.json();
                if (d.success && d.message.includes('criado')) { setLastAuto(new Date().toLocaleTimeString()); loadBkp(); }
            } catch {}
        };
        check();
        const i = setInterval(check, 3600000);
        return () => clearInterval(i);
    }, [auth]);

    const toggle = () => setIsDark(!isDark);
    const logout = async () => { await fetch(`${API}?action=logout`); setAuth(false); };
    const baixa = async (id) => {
        const r = await fetch(`${API}?action=baixa`, { method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify({ id }) });
        if ((await r.json()).success) loadInst();
    };
    const addInst = async (e) => {
        e.preventDefault();
        const r = await fetch(`${API}?action=add_installment`, { method:'POST', headers:{'Content-Type':'application/json'},
            body: JSON.stringify({ descricao: form.descricao, numParcelas: parseInt(form.total), valorTotal: parseFloat(form.valorTotal) }) });
        if ((await r.json()).success) { setModal(false); setForm({ descricao:'', total:'', valorTotal:'' }); loadInst(); }
    };
    const exportar = (f) => window.open(`${API}?action=export&formato=${f}`, '_blank');
    const criarBkp = async (tipo = 'Manual') => {
        const r = await fetch(`${API}?action=create_backup`, { method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify({ tipo }) });
        const d = await r.json();
        if (d.success) { loadBkp(); alert(`Backup criado: ${d.nome}`); }
    };
    const delBkp = async (id) => {
        if (!confirm('Excluir este backup?')) return;
        await fetch(`${API}?action=delete_backup`, { method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify({ id }) });
        loadBkp();
    };
    const exportarAtual = () => window.open(`${API}?action=export_current`, '_blank');

    const handleImportSubmit = async () => {
        if (!importFile) { setImportMsg({ tipo:'error', texto:'Selecione um arquivo JSON primeiro.' }); return; }
        if (!importFile.name.toLowerCase().endsWith('.json')) { setImportMsg({ tipo:'error', texto:'O arquivo deve ser .json' }); return; }
        setImportLoading(true); setImportMsg(null);
        try {
            const fd = new FormData(); fd.append('arquivo', importFile); fd.append('modo', importModo);
            const r = await fetch(`${API}?action=import_backup`, { method:'POST', body: fd });
            const d = await r.json();
            if (d.success) {
                setImportMsg({ tipo:'success', texto:`Importação concluída! ${d.importados} registro(s). Modo: ${d.modo === 'replace' ? 'Substituir' : 'Mesclar'}.` });
                loadInst(); loadBkp();
                setTimeout(() => { setImportModal(false); setImportFile(null); setImportMsg(null); }, 2500);
            } else { setImportMsg({ tipo:'error', texto: d.error || 'Erro.' }); }
        } catch { setImportMsg({ tipo:'error', texto:'Erro de conexão.' }); }
        setImportLoading(false);
    };

    const handleFileDrop = (e) => {
        e.preventDefault(); setDragActive(false);
        if (e.dataTransfer.files && e.dataTransfer.files[0]) { setImportFile(e.dataTransfer.files[0]); setImportMsg(null); }
    };

    // Classes dinâmicas - Paleta Suave
    const bgCard = isDark ? 'bg-softdark-card border-softdark-border' : 'bg-softlight-card border-softlight-border shadow-sm';
    const txtMut = isDark ? 'text-softdark-muted' : 'text-softlight-muted';
    const sideBg = isDark ? 'bg-[#222738] border-softdark-border' : 'bg-softlight-card border-softlight-border';
    const inputBg = isDark ? 'bg-softdark-bg border-softdark-border text-softdark-text' : 'bg-softlight-bg border-softlight-border text-softlight-text';

    const totAtivas = inst.filter(i => i.status === 'Ativo').length;
    const totPago = inst.reduce((a, i) => a + (i.parcelas_pagas * parseFloat(i.valor_parcela)), 0);
    const saldoDev = inst.reduce((a, i) => a + ((i.num_parcelas - i.parcelas_pagas) * parseFloat(i.valor_parcela)), 0);

    // ------------ TELAS ------------
    const Dashboard = () => (
        <div className="space-y-6 animate-in">
            <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 md:gap-6">
                {[
                    { t:'Saldo Devedor', v:`R$ ${saldoDev.toFixed(2)}`, i:'dollar', c:'text-accent-rose' },
                    { t:'Total Pago', v:`R$ ${totPago.toFixed(2)}`, i:'trendingUp', c:'text-accent-sage' },
                    { t:'Parcelas Ativas', v: totAtivas, i:'calendar', c:'text-accent-teal' },
                    { t:'Últ. Backup Auto', v: lastAuto || 'N/A', i:'database', c:'text-accent-lavender' },
                ].map((c, i) => (
                    <div key={i} className={`p-5 md:p-6 rounded-2xl border ${bgCard} flex flex-col gap-3 hover:-translate-y-1 transition-transform`}>
                        <div className="flex justify-between items-start">
                            <span className={`text-sm font-medium ${txtMut}`}>{c.t}</span>
                            <Icon name={c.i} className={`w-5 h-5 ${c.c}`}/>
                        </div>
                        <h3 className="text-xl md:text-2xl font-bold break-words">{c.v}</h3>
                    </div>
                ))}
            </div>
            <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <div className={`lg:col-span-2 p-6 rounded-2xl border ${bgCard}`}>
                    <h3 className="text-lg font-bold mb-4">Resumo das Parcelas</h3>
                    {inst.length === 0 ? <p className={txtMut}>Nenhuma parcela cadastrada.</p> : inst.map(item => (
                        <div key={item.id} className={`flex justify-between items-center border-b pb-2 mb-2 ${isDark ? 'border-softdark-border' : 'border-softlight-border'}`}>
                            <div>
                                <p className="font-medium text-sm">{item.descricao}</p>
                                <p className={`text-xs ${txtMut}`}>Parcela {item.parcelas_pagas}/{item.num_parcelas}</p>
                            </div>
                            <div className="text-right">
                                <p className="font-bold text-sm">R$ {parseFloat(item.valor_parcela).toFixed(2)}</p>
                                <p className="text-xs text-accent-amber">{item.proximo_vencimento || 'Concluído'}</p>
                            </div>
                        </div>
                    ))}
                </div>
                <div className={`p-6 rounded-2xl border ${bgCard}`}>
                    <h3 className="text-lg font-bold mb-4">Ações Rápidas</h3>
                    <button onClick={() => setModal(true)} className="w-full flex items-center justify-between p-4 bg-accent-teal/15 text-accent-teal rounded-xl hover:bg-accent-teal/25 mb-3 transition-colors">
                        <span className="font-medium">Nova Conta</span><Icon name="plus" className="w-5 h-5"/>
                    </button>
                    <button onClick={() => criarBkp('Manual')} className="w-full flex items-center justify-between p-4 bg-accent-lavender/15 text-accent-lavender rounded-xl hover:bg-accent-lavender/25 transition-colors">
                        <span className="font-medium">Backup Agora</span><Icon name="database" className="w-5 h-5"/>
                    </button>
                </div>
            </div>
        </div>
    );

    const Installments = () => (
        <div className="space-y-6 animate-in">
            <div className="flex justify-between items-center">
                <h2 className="text-xl font-bold">Contas Parceladas</h2>
                <button onClick={() => setModal(true)} className="flex items-center gap-2 bg-gradient-to-r from-accent-teal to-accent-lavender text-white px-4 py-2 rounded-xl font-bold shadow-lg shadow-accent-teal/20">
                    <Icon name="plus" className="w-4 h-4"/> Nova
                </button>
            </div>
            <div className={`p-4 md:p-6 rounded-2xl border ${bgCard} overflow-x-auto`}>
                <table className="w-full text-left border-collapse min-w-[600px]">
                    <thead>
                        <tr className={`text-sm ${txtMut} border-b ${isDark ? 'border-softdark-border' : 'border-softlight-border'}`}>
                            <th className="pb-3 font-medium">Descrição</th>
                            <th className="pb-3 font-medium">Progresso</th>
                            <th className="pb-3 font-medium">Valor</th>
                            <th className="pb-3 font-medium">Vencimento</th>
                            <th className="pb-3 font-medium">Status</th>
                            <th className="pb-3 font-medium text-right">Ação</th>
                        </tr>
                    </thead>
                    <tbody className="text-sm">
                        {inst.map(item => (
                            <tr key={item.id} className={`border-b ${isDark ? 'border-softdark-border/50 hover:bg-softdark-hover/40' : 'border-softlight-border hover:bg-softlight-hover'} transition-colors`}>
                                <td className="py-4 font-medium">{item.descricao}</td>
                                <td className="py-4">
                                    <span className={txtMut}>{item.parcelas_pagas}/{item.num_parcelas}</span>
                                    <div className={`w-24 h-2 rounded-full overflow-hidden mt-1 ${isDark ? 'bg-softdark-border' : 'bg-softlight-border'}`}>
                                        <div className="h-full bg-gradient-to-r from-accent-teal to-accent-lavender" style={{ width: `${(item.parcelas_pagas / item.num_parcelas) * 100}%` }}/>
                                    </div>
                                </td>
                                <td className="py-4">R$ {parseFloat(item.valor_parcela).toFixed(2)}</td>
                                <td className={`py-4 ${item.status === 'Concluído' ? 'text-accent-sage' : 'text-accent-amber'}`}>{item.proximo_vencimento || 'Concluído'}</td>
                                <td className="py-4">
                                    <span className={`px-2 py-1 rounded-full text-xs ${item.status === 'Concluído' ? 'bg-accent-sage/15 text-accent-sage' : 'bg-accent-teal/15 text-accent-teal'}`}>{item.status}</span>
                                </td>
                                <td className="py-4 text-right">
                                    {item.status !== 'Concluído' && (
                                        <button onClick={() => baixa(item.id)} className="px-4 py-1.5 bg-gradient-to-r from-accent-teal to-accent-lavender text-white rounded-lg text-xs font-bold hover:opacity-90 transition-opacity">Dar Baixa</button>
                                    )}
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </div>
    );

    const Reports = () => (
        <div className="space-y-6 animate-in">
            <h2 className="text-xl font-bold">Relatórios e Exportação</h2>
            <div className={`p-6 rounded-2xl border ${bgCard}`}>
                <h3 className="text-lg font-bold mb-4">Exportar Relatório</h3>
                <p className={`text-sm ${txtMut} mb-6`}>Escolha o formato para baixar os dados.</p>
                <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                    {[
                        { f:'pdf', t:'PDF', d:'Documento para impressão', c:'from-accent-peach to-accent-rose', i:'fileText' },
                        { f:'excel', t:'Excel', d:'Planilha eletrônica', c:'from-accent-sage to-accent-teal', i:'file' },
                        { f:'csv', t:'CSV', d:'Separado por vírgula', c:'from-accent-teal to-accent-sky', i:'fileText' },
                        { f:'json', t:'JSON', d:'Formato estruturado', c:'from-accent-lavender to-accent-sky', i:'file' },
                    ].map(o => (
                        <button key={o.f} onClick={() => exportar(o.f)} className={`p-6 rounded-2xl bg-gradient-to-br ${o.c} text-white text-left hover:scale-105 transition-transform shadow-lg`}>
                            <Icon name={o.i} className="w-8 h-8 mb-4"/>
                            <h4 className="font-bold text-lg mb-1">{o.t}</h4>
                            <p className="text-xs opacity-90">{o.d}</p>
                            <div className="flex items-center gap-2 mt-4 text-sm font-medium"><Icon name="download" className="w-4 h-4"/> Baixar</div>
                        </button>
                    ))}
                </div>
            </div>
        </div>
    );

    const Backups = () => (
        <div className="space-y-6 animate-in">
            <div className="flex flex-col md:flex-row md:justify-between md:items-center gap-4">
                <div>
                    <h2 className="text-xl font-bold">Gerenciamento de Backups</h2>
                    <p className={`text-sm ${txtMut}`}>Backup automático a cada 1 hora</p>
                </div>
                <button onClick={() => criarBkp('Manual')} className="flex items-center justify-center gap-2 bg-gradient-to-r from-accent-teal to-accent-lavender text-white px-4 py-2 rounded-xl font-bold shadow-lg shadow-accent-teal/20">
                    <Icon name="plus" className="w-4 h-4"/> Novo Backup
                </button>
            </div>

            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div className={`p-6 rounded-2xl border ${bgCard}`}>
                    <div className="flex items-center gap-3 mb-4">
                        <div className="p-3 bg-accent-teal/15 text-accent-teal rounded-xl"><Icon name="download" className="w-6 h-6"/></div>
                        <div>
                            <h3 className="font-bold text-lg">Exportar Backup</h3>
                            <p className={`text-xs ${txtMut}`}>Baixa um arquivo JSON com todos os dados atuais</p>
                        </div>
                    </div>
                    <button onClick={exportarAtual}
                        className="w-full flex items-center justify-center gap-2 py-3 bg-gradient-to-r from-accent-teal to-accent-sky text-white rounded-xl font-bold hover:opacity-90 shadow-lg shadow-accent-teal/20 transition-all">
                        <Icon name="download" className="w-5 h-5"/> Baixar Backup Atual (.json)
                    </button>
                </div>
                <div className={`p-6 rounded-2xl border ${bgCard}`}>
                    <div className="flex items-center gap-3 mb-4">
                        <div className="p-3 bg-accent-lavender/15 text-accent-lavender rounded-xl"><Icon name="upload" className="w-6 h-6"/></div>
                        <div>
                            <h3 className="font-bold text-lg">Importar Backup</h3>
                            <p className={`text-xs ${txtMut}`}>Restaura dados a partir de um arquivo JSON</p>
                        </div>
                    </div>
                    <button onClick={() => { setImportModal(true); setImportMsg(null); setImportFile(null); }}
                        className="w-full flex items-center justify-center gap-2 py-3 bg-gradient-to-r from-accent-lavender to-accent-peach text-white rounded-xl font-bold hover:opacity-90 shadow-lg shadow-accent-lavender/20 transition-all">
                        <Icon name="upload" className="w-5 h-5"/> Importar Arquivo (.json)
                    </button>
                </div>
            </div>

            <div className={`p-6 rounded-2xl border ${bgCard} flex flex-col md:flex-row md:items-center gap-4`}>
                <div className="p-3 bg-accent-sage/15 text-accent-sage rounded-full"><Icon name="database" className="w-6 h-6"/></div>
                <div className="flex-1">
                    <h3 className="font-bold">Backup Automático Ativo</h3>
                    <p className={`text-sm ${txtMut}`}>Criado automaticamente a cada 1 hora.</p>
                </div>
                <button onClick={() => criarBkp('Automático')} className="flex items-center gap-2 bg-accent-sage/15 text-accent-sage px-4 py-2 rounded-lg hover:bg-accent-sage/25 transition-colors">
                    <Icon name="refresh" className="w-4 h-4"/> Testar
                </button>
            </div>

            <div className={`p-4 md:p-6 rounded-2xl border ${bgCard} overflow-x-auto`}>
                <h3 className="text-lg font-bold mb-4">Histórico</h3>
                {bkp.length === 0 ? <p className={txtMut}>Nenhum backup.</p> : (
                    <table className="w-full text-left border-collapse min-w-[500px]">
                        <thead>
                            <tr className={`text-sm ${txtMut} border-b ${isDark ? 'border-softdark-border' : 'border-softlight-border'}`}>
                                <th className="pb-3 font-medium">Arquivo</th>
                                <th className="pb-3 font-medium">Tamanho</th>
                                <th className="pb-3 font-medium">Tipo</th>
                                <th className="pb-3 font-medium">Data</th>
                                <th className="pb-3 font-medium text-right">Ações</th>
                            </tr>
                        </thead>
                        <tbody className="text-sm">
                            {bkp.map(b => (
                                <tr key={b.id} className={`border-b ${isDark ? 'border-softdark-border/50' : 'border-softlight-border'}`}>
                                    <td className="py-3 font-mono text-xs">{b.nome_arquivo}</td>
                                    <td className="py-3">{b.tamanho}</td>
                                    <td className="py-3">
                                        <span className={`px-2 py-1 rounded-full text-xs ${b.tipo === 'Automático' ? 'bg-accent-sage/15 text-accent-sage' : 'bg-accent-teal/15 text-accent-teal'}`}>{b.tipo}</span>
                                    </td>
                                    <td className={`py-3 ${txtMut}`}>{new Date(b.created_at).toLocaleString('pt-BR')}</td>
                                    <td className="py-3 text-right space-x-2">
                                        <button onClick={() => window.open(`${API}?action=download_backup&id=${b.id}`, '_blank')} className="p-2 bg-accent-teal/15 text-accent-teal rounded-lg hover:bg-accent-teal/25 transition-colors" title="Baixar"><Icon name="download" className="w-4 h-4"/></button>
                                        <button onClick={() => delBkp(b.id)} className="p-2 bg-accent-rose/15 text-accent-rose rounded-lg hover:bg-accent-rose/25 transition-colors" title="Excluir"><Icon name="trash" className="w-4 h-4"/></button>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                )}
            </div>
        </div>
    );

    const Settings = () => (
        <div className="space-y-6 animate-in max-w-2xl">
            <div className={`p-6 rounded-2xl border ${bgCard}`}>
                <h2 className="text-xl font-bold mb-6">Configurações</h2>
                <div className="space-y-6">
                    <div className={`flex justify-between items-center border-b pb-4 ${isDark ? 'border-softdark-border' : 'border-softlight-border'}`}>
                        <div><p className="font-bold">Tema</p><p className={`text-sm ${txtMut}`}>Claro / Escuro</p></div>
                        <Theme3D isDark={isDark} toggle={toggle}/>
                    </div>
                    <div className={`flex justify-between items-center border-b pb-4 ${isDark ? 'border-softdark-border' : 'border-softlight-border'}`}>
                        <div><p className="font-bold">Sessão</p><p className={`text-sm ${txtMut}`}>Encerrar sessão</p></div>
                        <button onClick={logout} className="flex items-center gap-2 px-4 py-2 bg-accent-rose/15 text-accent-rose rounded-lg hover:bg-accent-rose/25 transition-colors"><Icon name="logout" className="w-4 h-4"/> Sair</button>
                    </div>
                </div>
            </div>

            {/* Card de versão */}
            <div className={`p-6 rounded-2xl border ${bgCard} flex items-center justify-between`}>
                <div className="flex items-center gap-4">
                    <div className="p-3 bg-gradient-to-br from-accent-teal to-accent-lavender rounded-xl">
                        <Icon name="tag" className="w-6 h-6 text-white"/>
                    </div>
                    <div>
                        <p className="font-bold">FinControl</p>
                        <p className={`text-sm ${txtMut}`}>Sistema de Controle Financeiro Pessoal</p>
                    </div>
                </div>
                <div className="text-right">
                    <p className={`text-xs ${txtMut}`}>Versão</p>
                    <p className="text-lg font-bold text-accent-teal">v{VERSAO}</p>
                </div>
            </div>
        </div>
    );

    const render = () => {
        if (loading) return <div className="flex justify-center items-center h-64 text-accent-teal font-bold">Carregando...</div>;
        if (err) return <div className="p-6 bg-accent-rose/10 text-accent-rose rounded-xl border border-accent-rose/30">{err}</div>;
        switch (tab) {
            case 'dashboard': return <Dashboard/>;
            case 'calendar': return <CalendarView isDark={isDark}/>;
            case 'installments': return <Installments/>;
            case 'reports': return <Reports/>;
            case 'backups': return <Backups/>;
            case 'settings': return <Settings/>;
            default: return null;
        }
    };

    const menu = [
        { id:'dashboard', i:'dashboard', l:'Visão Geral' },
        { id:'calendar', i:'calendar', l:'Calendário' },
        { id:'installments', i:'dollar', l:'Contas e Parcelas' },
        { id:'reports', i:'fileText', l:'Relatórios' },
        { id:'backups', i:'database', l:'Backups' },
        { id:'settings', i:'settings', l:'Configurações' }
    ];

    if (checking) return (
        <div className={`min-h-screen flex flex-col items-center justify-center ${isDark ? 'bg-[#1C1F2E] text-accent-teal' : 'bg-[#F5F6FA] text-accent-teal'} gap-3`}>
            <div className="font-bold">Verificando sessão...</div>
            <div className={`text-xs ${isDark ? 'text-[#9BA3B8]' : 'text-[#7A8095]'}`}>v{VERSAO}</div>
        </div>
    );
    if (!auth) return <LoginScreen onLogin={() => setAuth(true)} isDark={isDark} toggleTheme={toggle}/>;

    return (
        <div className="flex h-screen font-sans overflow-hidden">
            {sideOpen && <div className="fixed inset-0 bg-black/50 z-40 md:hidden" onClick={() => setSideOpen(false)}/>}
            <aside className={`fixed md:relative z-50 h-full w-64 border-r ${sideBg} p-6 flex flex-col transition-transform duration-300 ${sideOpen ? 'translate-x-0' : '-translate-x-full md:translate-x-0'}`}>
                <div className="flex items-center justify-between mb-8">
                    <div className="flex items-center gap-3 text-accent-teal font-bold text-xl">
                        <Icon name="wallet" className="w-8 h-8"/><span>FinControl</span>
                    </div>
                    <button onClick={() => setSideOpen(false)} className={`md:hidden ${txtMut}`}><Icon name="x" className="w-6 h-6"/></button>
                </div>
                <nav className="flex flex-col gap-2 flex-1">
                    {menu.map(m => (
                        <button key={m.id} onClick={() => { setTab(m.id); setSideOpen(false); }} aria-label={m.l}
                            className={`flex items-center gap-3 p-3 rounded-xl transition-all ${tab === m.id ? 'bg-gradient-to-r from-accent-teal/20 to-accent-lavender/20 text-accent-teal border border-accent-teal/30' : `${txtMut} hover:bg-accent-teal/10 hover:text-accent-teal`}`}>
                            <Icon name={m.i} className="w-5 h-5"/><span className="font-medium">{m.l}</span>
                        </button>
                    ))}
                </nav>

                {/* Versão na sidebar */}
                <div className={`pt-4 border-t ${isDark ? 'border-softdark-border' : 'border-softlight-border'}`}>
                    <div className={`flex items-center justify-between text-xs ${txtMut} mb-3`}>
                        <span className="flex items-center gap-1"><Icon name="tag" className="w-3 h-3"/> Versão</span>
                        <span className="font-mono font-bold text-accent-teal">v{VERSAO}</span>
                    </div>
                    <button onClick={logout} className={`w-full flex items-center gap-3 p-3 rounded-xl ${txtMut} hover:bg-accent-rose/10 hover:text-accent-rose transition-colors`}>
                        <Icon name="logout" className="w-5 h-5"/><span className="font-medium">Sair</span>
                    </button>
                </div>
            </aside>

            <main className="flex-1 overflow-y-auto p-4 md:p-8">
                <header className="flex justify-between items-center mb-6 md:mb-8">
                    <div className="flex items-center gap-4">
                        <button onClick={() => setSideOpen(true)} className={`md:hidden p-2 rounded-lg ${isDark ? 'bg-softdark-hover text-accent-teal' : 'bg-softlight-hover text-accent-teal'}`} aria-label="Menu">
                            <Icon name="menu" className="w-6 h-6"/>
                        </button>
                        <div>
                            <h1 className="text-lg md:text-2xl font-bold capitalize">{menu.find(m => m.id === tab)?.l || 'Painel'}</h1>
                            <p className={`text-xs md:text-sm ${txtMut}`}>Bem-vindo! <span className="ml-2 opacity-60">v{VERSAO}</span></p>
                        </div>
                    </div>
                    <Theme3D isDark={isDark} toggle={toggle}/>
                </header>
                {render()}
            </main>

            {/* Modal Nova Conta */}
            <Modal isOpen={modal} onClose={() => setModal(false)} title="Nova Conta Parcelada" isDark={isDark}>
                <form onSubmit={addInst} className="space-y-4">
                    <div>
                        <label className="block text-sm font-medium mb-1">Descrição</label>
                        <input type="text" required value={form.descricao} onChange={e => setForm({ ...form, descricao: e.target.value })}
                            className={`w-full border rounded-lg p-3 outline-none focus:border-accent-teal transition-colors ${inputBg}`} placeholder="Ex: Notebook"/>
                    </div>
                    <div className="grid grid-cols-2 gap-4">
                        <div>
                            <label className="block text-sm font-medium mb-1">Nº Parcelas</label>
                            <input type="number" min="1" required value={form.total} onChange={e => setForm({ ...form, total: e.target.value })}
                                className={`w-full border rounded-lg p-3 outline-none focus:border-accent-teal transition-colors ${inputBg}`} placeholder="12"/>
                        </div>
                        <div>
                            <label className="block text-sm font-medium mb-1">Valor Total (R$)</label>
                            <input type="number" step="0.01" min="0.01" required value={form.valorTotal} onChange={e => setForm({ ...form, valorTotal: e.target.value })}
                                className={`w-full border rounded-lg p-3 outline-none focus:border-accent-teal transition-colors ${inputBg}`} placeholder="2500.00"/>
                        </div>
                    </div>
                    <div className="pt-4 flex justify-end gap-3">
                        <button type="button" onClick={() => setModal(false)} className={`px-4 py-2 rounded-lg ${txtMut}`}>Cancelar</button>
                        <button type="submit" className="px-6 py-2 bg-gradient-to-r from-accent-teal to-accent-lavender text-white rounded-lg font-bold hover:opacity-90 transition-opacity">Salvar</button>
                    </div>
                </form>
            </Modal>

            {/* Modal Importar Backup */}
            <Modal isOpen={importModal} onClose={() => { setImportModal(false); setImportFile(null); setImportMsg(null); }} title="Importar Backup" isDark={isDark}>
                <div className="space-y-4">
                    <div className={`p-4 rounded-xl border ${isDark ? 'border-accent-amber/30 bg-accent-amber/5' : 'border-accent-amber/40 bg-accent-amber/10'}`}>
                        <p className="text-xs">
                            <strong className="text-accent-amber">⚠️ Atenção:</strong> A importação adiciona ou substitui dados no banco. Escolha o modo adequado.
                        </p>
                    </div>
                    <div>
                        <label className="block text-sm font-medium mb-2">Modo de Importação</label>
                        <div className={`flex rounded-xl p-1 ${isDark ? 'bg-softdark-bg' : 'bg-softlight-hover'}`}>
                            <button type="button" onClick={() => setImportModo('merge')}
                                className={`flex-1 py-2 rounded-lg text-sm font-bold transition-all ${importModo === 'merge' ? 'bg-gradient-to-r from-accent-teal to-accent-lavender text-white' : txtMut}`}>Mesclar</button>
                            <button type="button" onClick={() => setImportModo('replace')}
                                className={`flex-1 py-2 rounded-lg text-sm font-bold transition-all ${importModo === 'replace' ? 'bg-gradient-to-r from-accent-peach to-accent-rose text-white' : txtMut}`}>Substituir</button>
                        </div>
                        <p className={`text-xs mt-2 ${txtMut}`}>
                            {importModo === 'merge' ? '✅ Adiciona apenas registros novos, ignorando duplicados.' : '⚠️ Apaga todas as contas atuais e importa apenas as do arquivo.'}
                        </p>
                    </div>
                    <div onClick={() => fileInputRef.current?.click()}
                        onDragOver={(e) => { e.preventDefault(); setDragActive(true); }}
                        onDragLeave={() => setDragActive(false)}
                        onDrop={handleFileDrop}
                        className={`drop-zone ${dragActive ? 'active' : ''} rounded-xl p-8 text-center cursor-pointer`}>
                        <input ref={fileInputRef} type="file" accept=".json,application/json" className="hidden"
                            onChange={(e) => { if (e.target.files[0]) { setImportFile(e.target.files[0]); setImportMsg(null); } }}/>
                        <Icon name="upload" className="w-10 h-10 mx-auto mb-3 text-accent-teal"/>
                        {importFile ? (
                            <div>
                                <p className="font-bold text-accent-sage">✓ {importFile.name}</p>
                                <p className={`text-xs ${txtMut} mt-1`}>{(importFile.size / 1024).toFixed(2)} KB</p>
                            </div>
                        ) : (
                            <div>
                                <p className="font-medium">Clique ou arraste o arquivo aqui</p>
                                <p className={`text-xs ${txtMut} mt-1`}>Apenas arquivos .json</p>
                            </div>
                        )}
                    </div>
                    {importMsg && (
                        <div className={`p-3 rounded-lg text-sm border ${importMsg.tipo === 'success' ? 'bg-accent-sage/10 border-accent-sage/30 text-accent-sage' : 'bg-accent-rose/10 border-accent-rose/30 text-accent-rose'}`}>
                            {importMsg.texto}
                        </div>
                    )}
                    <div className="pt-2 flex justify-end gap-3">
                        <button type="button" onClick={() => { setImportModal(false); setImportFile(null); setImportMsg(null); }} className={`px-4 py-2 rounded-lg ${txtMut}`}>Cancelar</button>
                        <button type="button" onClick={handleImportSubmit} disabled={importLoading || !importFile}
                            className="px-6 py-2 bg-gradient-to-r from-accent-teal to-accent-lavender text-white rounded-lg font-bold disabled:opacity-50 flex items-center gap-2 transition-opacity">
                            {importLoading ? 'Importando...' : (<><Icon name="upload" className="w-4 h-4"/> Importar</>)}
                        </button>
                    </div>
                </div>
            </Modal>
        </div>
    );
}

ReactDOM.createRoot(document.getElementById('root')).render(<App/>);
</script>
</body>
</html>