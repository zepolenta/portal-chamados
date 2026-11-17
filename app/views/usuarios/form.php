<?php use App\Models\Usuario; ?>
<div class="cabecalho"><h1><?= e($titulo) ?></h1></div>
<?php if ($erros): ?><div class="alerta alerta-erro" role="alert">Corrija os campos destacados.</div><?php endif; ?>
<form class="formulario" method="post" action="?r=usuarios/form<?= $id ? '&id=' . $id : '' ?>" novalidate>
  <?= csrf_campo() ?>
  <label for="nome">Nome *</label>
  <input id="nome" name="nome" required maxlength="120" value="<?= e($d['nome']) ?>" class="<?= isset($erros['nome']) ? 'invalido' : '' ?>">
  <?= erro_campo($erros, 'nome') ?>
  <label for="email">E-mail *</label>
  <input id="email" name="email" type="email" required value="<?= e($d['email']) ?>" class="<?= isset($erros['email']) ? 'invalido' : '' ?>">
  <?= erro_campo($erros, 'email') ?>
  <div class="duas-colunas">
    <div>
      <label for="perfil">Perfil *</label>
      <select id="perfil" name="perfil">
        <?php foreach (Usuario::PERFIS as $k => $v): ?><option value="<?= $k ?>"<?= sel($d['perfil'] === $k) ?>><?= e($v) ?></option><?php endforeach; ?>
      </select>
      <?= erro_campo($erros, 'perfil') ?>
    </div>
    <div id="bloco-empresa">
      <label for="id_cliente">Empresa (para perfil Cliente)</label>
      <select id="id_cliente" name="id_cliente">
        <option value="">Selecione…</option>
        <?php foreach ($clientes as $c): ?><option value="<?= $c['id_cliente'] ?>"<?= sel($d['id_cliente'] == $c['id_cliente']) ?>><?= e($c['nome']) ?></option><?php endforeach; ?>
      </select>
      <?= erro_campo($erros, 'id_cliente') ?>
    </div>
  </div>
  <label for="senha"><?= $id ? 'Nova senha (deixe em branco para manter)' : 'Senha *' ?></label>
  <input id="senha" name="senha" type="password" autocomplete="new-password" class="<?= isset($erros['senha']) ? 'invalido' : '' ?>">
  <p class="dica">Mínimo de 8 caracteres, com letras e números.</p>
  <?= erro_campo($erros, 'senha') ?>
  <label class="inline"><input type="checkbox" name="ativo" value="1"<?= chk((bool) $d['ativo']) ?>> Usuário ativo</label>
  <div class="acoes">
    <button class="btn btn-primario" type="submit">Salvar</button>
    <a class="btn btn-link" href="?r=usuarios/index">Cancelar</a>
  </div>
</form>
