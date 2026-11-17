<?php use App\Core\Auth; ?>
<div class="cabecalho">
  <h1>Clientes</h1>
  <?php if (Auth::perfil() === 'ADMIN'): ?><a class="btn btn-primario" href="?r=clientes/form">+ Novo cliente</a><?php endif; ?>
</div>
<form class="filtros" method="get">
  <input type="hidden" name="r" value="clientes/index">
  <input type="search" name="busca" placeholder="Nome, CPF ou CNPJ" value="<?= e($busca) ?>" aria-label="Buscar cliente">
  <button class="btn" type="submit">Buscar</button>
</form>
<div class="tabela-wrap"><table class="tabela">
  <thead><tr><th>Cliente</th><th>Tipo</th><th>CPF/CNPJ</th><th>Modalidade</th><th>Plano</th><th>Chamados ativos</th><th>Situação</th><?php if (Auth::perfil() === 'ADMIN'): ?><th></th><?php endif; ?></tr></thead>
  <tbody>
  <?php foreach ($clientes as $c): ?>
    <tr>
      <td data-rot="Cliente"><a href="?r=chamados/index&id_cliente=<?= $c['id_cliente'] ?>"><?= e($c['nome']) ?></a><br><small class="muted"><?= e($c['email']) ?></small></td>
      <td data-rot="Tipo"><?= $c['tipo_pessoa'] ?></td>
      <td data-rot="CPF/CNPJ"><?= e(doc_formatado($c['cpf_cnpj'])) ?></td>
      <td data-rot="Modalidade"><?= $c['modalidade'] === 'MENSALISTA' ? 'Mensalista' : 'Avulso' ?></td>
      <td data-rot="Plano"><?= $c['plano'] ? e($c['plano']) . ' · ' . (int) $c['horas_mes'] . ' h/mês' : '—' ?></td>
      <td data-rot="Ativos"><?= (int) $c['abertos'] ?></td>
      <td data-rot="Situação"><?= $c['ativo'] ? 'Ativo' : '<span class="muted">Inativo</span>' ?></td>
      <?php if (Auth::perfil() === 'ADMIN'): ?><td><a href="?r=clientes/form&id=<?= $c['id_cliente'] ?>">Editar</a></td><?php endif; ?>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table></div>
