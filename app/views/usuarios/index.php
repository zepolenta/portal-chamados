<?php use App\Models\Usuario; ?>
<div class="cabecalho">
  <h1>Usuários</h1>
  <a class="btn btn-primario" href="?r=usuarios/form">+ Novo usuário</a>
</div>
<div class="tabela-wrap"><table class="tabela">
  <thead><tr><th>Nome</th><th>E-mail</th><th>Perfil</th><th>Empresa</th><th>Último acesso</th><th>Situação</th><th></th></tr></thead>
  <tbody>
  <?php foreach ($usuarios as $u): ?>
    <tr>
      <td data-rot="Nome"><?= e($u['nome']) ?></td>
      <td data-rot="E-mail"><?= e($u['email']) ?></td>
      <td data-rot="Perfil"><?= e(Usuario::PERFIS[$u['perfil']]) ?></td>
      <td data-rot="Empresa"><?= e($u['cliente'] ?? 'Polenta TI') ?></td>
      <td data-rot="Último acesso"><?= data_br($u['ultimo_acesso']) ?></td>
      <td data-rot="Situação"><?= $u['ativo'] ? 'Ativo' : '<span class="muted">Inativo</span>' ?></td>
      <td><a href="?r=usuarios/form&id=<?= $u['id_usuario'] ?>">Editar</a></td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table></div>
