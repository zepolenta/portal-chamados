<div class="cabecalho"><h1><?= e($titulo) ?></h1></div>
<?php if ($erros): ?><div class="alerta alerta-erro" role="alert">Corrija os campos destacados.</div><?php endif; ?>
<form class="formulario" method="post" action="?r=clientes/form<?= $id ? '&id=' . $id : '' ?>" novalidate>
  <?= csrf_campo() ?>
  <fieldset class="opcoes">
    <legend>Tipo de pessoa *</legend>
    <label class="inline"><input type="radio" name="tipo_pessoa" value="PJ"<?= chk($d['tipo_pessoa'] === 'PJ') ?>> Pessoa jurídica</label>
    <label class="inline"><input type="radio" name="tipo_pessoa" value="PF"<?= chk($d['tipo_pessoa'] === 'PF') ?>> Pessoa física</label>
  </fieldset>
  <label for="nome">Nome / razão social *</label>
  <input id="nome" name="nome" required maxlength="150" value="<?= e($d['nome']) ?>" class="<?= isset($erros['nome']) ? 'invalido' : '' ?>">
  <?= erro_campo($erros, 'nome') ?>
  <div class="duas-colunas">
    <div>
      <label for="cpf_cnpj">CPF / CNPJ *</label>
      <input id="cpf_cnpj" name="cpf_cnpj" required inputmode="numeric" value="<?= e($d['cpf_cnpj'] ? doc_formatado($d['cpf_cnpj']) : '') ?>" class="<?= isset($erros['cpf_cnpj']) ? 'invalido' : '' ?>">
      <?= erro_campo($erros, 'cpf_cnpj') ?>
    </div>
    <div>
      <label for="telefone">Telefone</label>
      <input id="telefone" name="telefone" maxlength="20" value="<?= e($d['telefone']) ?>">
    </div>
  </div>
  <label for="email">E-mail *</label>
  <input id="email" name="email" type="email" required maxlength="150" value="<?= e($d['email']) ?>" class="<?= isset($erros['email']) ? 'invalido' : '' ?>">
  <?= erro_campo($erros, 'email') ?>

  <fieldset class="opcoes">
    <legend>Modalidade *</legend>
    <label class="inline"><input type="radio" name="modalidade" value="AVULSO"<?= chk($d['modalidade'] === 'AVULSO') ?>> Avulso</label>
    <label class="inline"><input type="radio" name="modalidade" value="MENSALISTA"<?= chk($d['modalidade'] === 'MENSALISTA') ?>> Mensalista</label>
  </fieldset>
  <fieldset class="contrato" id="bloco-contrato">
    <legend>Contrato (mensalista)</legend>
    <div class="duas-colunas">
      <div><label for="plano">Plano *</label><input id="plano" name="plano" maxlength="60" value="<?= e($d['plano'] ?? '') ?>"><?= erro_campo($erros, 'plano') ?></div>
      <div><label for="horas_mes">Horas por mês *</label><input id="horas_mes" name="horas_mes" inputmode="numeric" value="<?= e((string) ($d['horas_mes'] ?? '')) ?>"><?= erro_campo($erros, 'horas_mes') ?></div>
      <div><label for="inicio_vigencia">Início da vigência *</label><input id="inicio_vigencia" name="inicio_vigencia" type="date" value="<?= e($d['inicio_vigencia'] ?? '') ?>"><?= erro_campo($erros, 'inicio_vigencia') ?></div>
      <div><label for="fim_vigencia">Fim da vigência</label><input id="fim_vigencia" name="fim_vigencia" type="date" value="<?= e($d['fim_vigencia'] ?? '') ?>"><?= erro_campo($erros, 'fim_vigencia') ?></div>
    </div>
  </fieldset>
  <label class="inline"><input type="checkbox" name="ativo" value="1"<?= chk((bool) $d['ativo']) ?>> Cliente ativo</label>
  <div class="acoes">
    <button class="btn btn-primario" type="submit">Salvar</button>
    <a class="btn btn-link" href="?r=clientes/index">Cancelar</a>
  </div>
</form>
