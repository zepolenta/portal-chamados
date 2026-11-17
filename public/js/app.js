// Portal de Chamados Polenta TI — comportamento da interface (feedback imediato ao usuário)
(function () {
  'use strict';

  // Menu no celular
  const botao = document.querySelector('[data-toggle="menu"]');
  const menu = document.getElementById('menu');
  if (botao && menu) {
    botao.addEventListener('click', () => {
      const aberto = menu.classList.toggle('aberto');
      botao.setAttribute('aria-expanded', aberto ? 'true' : 'false');
    });
  }

  // Validação de tamanho do anexo antes do envio (o servidor valida de novo)
  document.querySelectorAll('input[type=file][data-max]').forEach((campo) => {
    campo.addEventListener('change', () => {
      const max = Number(campo.dataset.max);
      const arq = campo.files[0];
      let aviso = campo.parentElement.querySelector('.erro-js');
      if (aviso) aviso.remove();
      if (arq && arq.size > max) {
        aviso = document.createElement('p');
        aviso.className = 'erro-campo erro-js';
        aviso.setAttribute('role', 'alert');
        aviso.textContent = 'O anexo excede o limite de ' + (max / 1048576).toLocaleString('pt-BR', { maximumFractionDigits: 1 }) + ' MB. Escolha um arquivo menor.';
        campo.insertAdjacentElement('afterend', aviso);
        campo.value = '';
      }
    });
  });

  // Descrição da prioridade selecionada
  const prioridade = document.getElementById('id_prioridade');
  const desc = document.getElementById('desc-prioridade');
  if (prioridade && desc) {
    const atualizar = () => { desc.textContent = prioridade.selectedOptions[0]?.dataset.desc || ''; };
    prioridade.addEventListener('change', atualizar);
    atualizar();
  }

  // Bloco de contrato só para mensalistas
  const contrato = document.getElementById('bloco-contrato');
  if (contrato) {
    const radios = document.querySelectorAll('input[name=modalidade]');
    const atualizar = () => {
      const mensalista = document.querySelector('input[name=modalidade]:checked')?.value === 'MENSALISTA';
      contrato.hidden = !mensalista;
    };
    radios.forEach((r) => r.addEventListener('change', atualizar));
    atualizar();
  }

  // Campo de empresa só para perfil Cliente
  const perfil = document.getElementById('perfil');
  const empresa = document.getElementById('bloco-empresa');
  if (perfil && empresa) {
    const atualizar = () => { empresa.hidden = perfil.value !== 'CLIENTE'; };
    perfil.addEventListener('change', atualizar);
    atualizar();
  }

  // Máscara de CPF/CNPJ conforme o tipo de pessoa (o servidor guarda só os dígitos)
  const doc = document.getElementById('cpf_cnpj');
  if (doc) {
    const tipo = () => document.querySelector('input[name=tipo_pessoa]:checked')?.value || 'PJ';
    const mascarar = () => {
      const max = tipo() === 'PF' ? 11 : 14;
      const d = doc.value.replace(/\D/g, '').slice(0, max);
      doc.value = tipo() === 'PF'
        ? d.replace(/^(\d{3})(\d)/, '$1.$2').replace(/^(\d{3})\.(\d{3})(\d)/, '$1.$2.$3').replace(/\.(\d{3})(\d{1,2})$/, '.$1-$2')
        : d.replace(/^(\d{2})(\d)/, '$1.$2').replace(/^(\d{2})\.(\d{3})(\d)/, '$1.$2.$3')
           .replace(/\.(\d{3})(\d)/, '.$1/$2').replace(/(\d{4})(\d{1,2})$/, '$1-$2');
      doc.placeholder = tipo() === 'PF' ? '000.000.000-00' : '00.000.000/0000-00';
    };
    doc.addEventListener('input', mascarar);
    document.querySelectorAll('input[name=tipo_pessoa]').forEach((r) => r.addEventListener('change', mascarar));
    mascarar();
  }

  // Confirmação de ações irreversíveis
  document.querySelectorAll('form[data-confirmar]').forEach((f) => {
    f.addEventListener('submit', (ev) => {
      if (!window.confirm(f.dataset.confirmar)) ev.preventDefault();
    });
  });

  // Evita envio duplo
  document.querySelectorAll('form').forEach((f) => {
    f.addEventListener('submit', (ev) => {
      if (ev.defaultPrevented) return;
      const b = f.querySelector('button[type=submit]');
      if (b) setTimeout(() => { b.disabled = true; }, 0);
    });
  });
})();
