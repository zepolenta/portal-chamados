<?php
use App\Core\App;
use App\Core\Auth;
$u = Auth::user();
$rotaAtual = $_GET['r'] ?? 'painel/index';
$ativo = fn(string $prefixo) => str_starts_with($rotaAtual, $prefixo) ? ' class="ativo" aria-current="page"' : '';
$flashes = $_SESSION['flash'] ?? [];
unset($_SESSION['flash']);
?><!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e($titulo ?? '') ?> · <?= e(App::config('app_nome')) ?></title>
  <link rel="stylesheet" href="css/app.css">
</head>
<body>
<?php if ($u): ?>
<header class="topo">
  <a class="marca" href="?r=painel/index"><span class="logo">P</span> Polenta <b>Chamados</b></a>
  <button class="menu-btn" type="button" aria-label="Abrir menu" data-toggle="menu">☰</button>
  <nav id="menu">
    <a href="?r=painel/index"<?= $ativo('painel') ?>>Painel</a>
    <a href="?r=chamados/index"<?= $ativo('chamados/index') ?>><?= Auth::ehEquipe() ? 'Fila' : 'Meus chamados' ?></a>
    <a href="?r=chamados/novo"<?= $ativo('chamados/novo') ?>>Novo chamado</a>
    <?php if (Auth::ehEquipe()): ?><a href="?r=clientes/index"<?= $ativo('clientes') ?>>Clientes</a><?php endif; ?>
    <?php if (Auth::perfil() === 'ADMIN'): ?><a href="?r=usuarios/index"<?= $ativo('usuarios') ?>>Usuários</a><?php endif; ?>
    <span class="usuario"><?= e($u['nome']) ?> <small><?= e(\App\Models\Usuario::PERFIS[$u['perfil']]) ?></small></span>
    <a href="?r=auth/sair" class="sair">Sair</a>
  </nav>
</header>
<?php endif; ?>
<main class="conteudo">
  <?php foreach ($flashes as $f): ?>
    <div class="alerta alerta-<?= e($f['tipo']) ?>" role="status"><?= e($f['msg']) ?></div>
  <?php endforeach; ?>
  <?= $conteudo ?>
</main>
<footer class="rodape">Polenta TI · Portal de Chamados · <?= date('Y') ?></footer>
<script src="js/app.js"></script>
</body>
</html>
