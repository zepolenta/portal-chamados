<?php use App\Core\Auth; use App\Models\Chamado; ?>
<div class="cabecalho">
  <h1><?= e($titulo) ?></h1>
  <a class="btn btn-primario" href="?r=chamados/novo">+ Novo chamado</a>
</div>

<form class="filtros" method="get" aria-label="Filtros">
  <input type="hidden" name="r" value="chamados/index">
  <input type="search" name="busca" placeholder="Protocolo ou título" value="<?= e($f['busca']) ?>" aria-label="Buscar">
  <select name="status" aria-label="Status">
    <option value="">Todos os status</option>
    <option value="ATIVOS"<?= sel($f['status'] === 'ATIVOS') ?>>Somente ativos</option>
    <?php foreach (Chamado::STATUS as $k => $v): ?><option value="<?= $k ?>"<?= sel($f['status'] === $k) ?>><?= e($v) ?></option><?php endforeach; ?>
  </select>
  <select name="id_prioridade" aria-label="Prioridade">
    <option value="">Todas as prioridades</option>
    <?php foreach ($prioridades as $p): ?><option value="<?= $p['id_prioridade'] ?>"<?= sel($f['id_prioridade'] == $p['id_prioridade']) ?>><?= e($p['nome']) ?></option><?php endforeach; ?>
  </select>
  <?php if (Auth::ehEquipe()): ?>
  <select name="id_cliente" aria-label="Cliente">
    <option value="">Todos os clientes</option>
    <?php foreach ($clientes as $cl): ?><option value="<?= $cl['id_cliente'] ?>"<?= sel($f['id_cliente'] == $cl['id_cliente']) ?>><?= e($cl['nome']) ?></option><?php endforeach; ?>
  </select>
  <?php endif; ?>
  <label class="inline">De <input type="date" name="de" value="<?= e($f['de']) ?>"></label>
  <label class="inline">Até <input type="date" name="ate" value="<?= e($f['ate']) ?>"></label>
  <button class="btn" type="submit">Filtrar</button>
  <a class="btn btn-link" href="?r=chamados/index">Limpar</a>
</form>

<?php if (!$chamados): ?>
  <p class="vazio">Nenhum chamado encontrado com esses filtros.</p>
<?php else: ?>
<div class="tabela-wrap"><table class="tabela">
  <thead><tr><th>Protocolo</th><th>Título</th><?php if (Auth::ehEquipe()): ?><th>Cliente</th><th>Técnico</th><?php endif; ?><th>Prioridade</th><th>Status</th><th>Aberto em</th><th>Prazo</th><th>SLA</th></tr></thead>
  <tbody>
  <?php foreach ($chamados as $c): ?>
    <tr>
      <td data-rot="Protocolo"><a href="?r=chamados/ver&id=<?= $c['id_chamado'] ?>"><?= e($c['protocolo']) ?></a></td>
      <td data-rot="Título"><?= e($c['titulo']) ?></td>
      <?php if (Auth::ehEquipe()): ?>
        <td data-rot="Cliente"><?= e($c['cliente']) ?> <small class="muted"><?= $c['modalidade'] === 'MENSALISTA' ? 'Mensalista' : 'Avulso' ?></small></td>
        <td data-rot="Técnico"><?= e($c['tecnico'] ?? '—') ?></td>
      <?php endif; ?>
      <td data-rot="Prioridade"><?= badge_prioridade($c['prioridade']) ?></td>
      <td data-rot="Status"><?= badge_status($c['status']) ?></td>
      <td data-rot="Aberto em"><?= data_br($c['aberto_em']) ?></td>
      <td data-rot="Prazo"><?= data_br($c['prazo_solucao']) ?></td>
      <td data-rot="SLA"><?= badge_sla($c['situacao_sla']) ?></td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table></div>
<p class="muted"><?= count($chamados) ?> chamado(s).</p>
<?php endif; ?>
